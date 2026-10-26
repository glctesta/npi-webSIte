<?php
/**
 * license_lib.php — GTMC-NPI license server core.
 *
 * Same architecture as the gtmc-angajati.eu license server: RSA-signed
 * tokens, plans/licenses/users/installation_keys tables, offline tokens.
 *
 * PROTOCOL (must stay byte-compatible with the GTMC-NPI web app license
 * verification, backend/services/license_service.py)
 * ------------------------------------------------------------------------
 *   POST /api.php?action=license_validate
 *        {license_key, fingerprint, hostname, app_version, protocol, projects, users}
 *     -> 200 {success:true, token}
 *     -> 4xx {success:false, error: bad_request|invalid_key|revoked|expired|
 *                                    activation_limit|machine_deactivated|server_error}
 *   POST /api.php?action=license_deactivate {license_key, fingerprint}
 *     -> 200 {success:true}
 *
 *   token = base64url(json payload) . "." . base64url(RSA-PKCS1v15-SHA256(first part))
 *   payload = {v, key, company, level, max_users, max_projects, features,
 *              valid_until (YYYY-MM-DD), fingerprint, iat, grace_days, offline}
 *
 * The app verifies the signature with the PUBLIC key configured on its side
 * (GTMC_SECRETS_DIR), checks that payload.fingerprint equals its
 * own machine fingerprint and that payload.key equals the key it sent.
 *
 * The private key is NEVER stored in the repository: see LICENSE_RSA_PRIVATE_KEY_PATH
 * / LICENSE_RSA_PRIVATE_KEY in .env.
 *
 * All DB access goes through db_fetch / db_query / db_insert (includes/db.php),
 * always with prepared statements. Functions return values instead of
 * printing, so tests/test_license.php can exercise them without a web server.
 */

const LIC_LEVELS = ['trial', 'professional', 'enterprise'];
const LIC_KEY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const LIC_KEY_PREFIX = 'NPI';
const LIC_OFFLINE_GRACE_DAYS = 36500;   // "no heartbeat required" (same as angajati)
const LIC_NO_EXPIRY_DATE = '2099-12-31';

// ── Encoding ────────────────────────────────────────────────────────────

function lic_b64u($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function lic_b64u_decode($s) {
    $s = strtr((string)$s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode($s, true);
}

// ── Keys ────────────────────────────────────────────────────────────────

/** PEM text of the RSA private key, from .env (path preferred) or null. */
function lic_private_key_pem() {
    if (defined('LICENSE_RSA_PRIVATE_KEY_PATH') && LICENSE_RSA_PRIVATE_KEY_PATH !== '') {
        $path = LICENSE_RSA_PRIVATE_KEY_PATH;
        if (is_readable($path)) {
            return file_get_contents($path);
        }
        error_log('GTMC-NPI license: private key not readable at ' . $path);
    }
    if (defined('LICENSE_RSA_PRIVATE_KEY') && LICENSE_RSA_PRIVATE_KEY !== '') {
        return LICENSE_RSA_PRIVATE_KEY;
    }
    return null;
}

function lic_private_key() {
    static $key = null;
    if ($key !== null) return $key;
    if (!function_exists('openssl_pkey_get_private')) {
        throw new RuntimeException('PHP openssl extension not available');
    }
    $pem = lic_private_key_pem();
    $res = $pem ? @openssl_pkey_get_private($pem) : false;
    if (!$res) {
        throw new RuntimeException('License private key not configured or unreadable');
    }
    return $key = $res;
}

/** Public key (PEM) derived from the private key: paste it into license_pubkey.py. */
function lic_public_key_pem() {
    $details = openssl_pkey_get_details(lic_private_key());
    return $details['key'] ?? null;
}

/** True when the signing key is configured (used by staff UI / health check). */
function lic_signing_available() {
    try {
        lic_private_key();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// ── Tokens ──────────────────────────────────────────────────────────────

/** Token = base64url(json) . "." . base64url(RSA-SHA256 signature of the first part) */
function lic_sign(array $payload) {
    $body = lic_b64u(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    if (!openssl_sign($body, $sig, lic_private_key(), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('openssl_sign failed');
    }
    return $body . '.' . lic_b64u($sig);
}

/** Payload if the RSA signature is valid (same check as the app), else null. */
function lic_verify($token) {
    $parts = explode('.', trim((string)$token));
    if (count($parts) !== 2) return null;
    [$body, $sig] = $parts;
    $raw_sig = lic_b64u_decode($sig);
    if ($raw_sig === false) return null;
    $pub = openssl_pkey_get_public(lic_public_key_pem());
    if (!$pub || openssl_verify($body, $raw_sig, $pub, OPENSSL_ALGO_SHA256) !== 1) {
        return null;
    }
    $payload = json_decode((string)lic_b64u_decode($body), true);
    return is_array($payload) ? $payload : null;
}

/** Decode the payload WITHOUT verifying (only for display / lookups). */
function lic_peek($token) {
    $parts = explode('.', trim((string)$token));
    if (count($parts) !== 2) return null;
    $payload = json_decode((string)lic_b64u_decode($parts[0]), true);
    return is_array($payload) ? $payload : null;
}

// ── Helpers ─────────────────────────────────────────────────────────────

/** New key NPI-XXXXX-XXXXX-XXXXX-XXXXX (no ambiguous chars 0/O/1/I). */
function lic_generate_key($prefix = LIC_KEY_PREFIX) {
    $parts = [];
    for ($p = 0; $p < 4; $p++) {
        $s = '';
        for ($i = 0; $i < 5; $i++) {
            $s .= LIC_KEY_ALPHABET[random_int(0, strlen(LIC_KEY_ALPHABET) - 1)];
        }
        $parts[] = $s;
    }
    return $prefix . '-' . implode('-', $parts);
}

function lic_normalize_key($key) {
    return strtoupper(trim((string)$key));
}

/** Same format the bridge accepted (also legacy NPI-… keys). */
function lic_key_valid($key) {
    return preg_match('/^[A-Z0-9-]{10,40}$/', (string)$key) === 1;
}

/** Accepts the raw SHA-256 hex or the dashed/upper-case form shown by the app. */
function lic_normalize_fp($fp) {
    return strtolower(preg_replace('/[^0-9A-Fa-f]/', '', (string)$fp));
}

function lic_fp_valid($fp) {
    return preg_match('/^[a-f0-9]{64}$/', (string)$fp) === 1;
}

function lic_tier($lic) {
    $slug = $lic['plan_slug'] ?? ($lic['license_level'] ?? 'trial');
    return in_array($slug, LIC_LEVELS, true) ? $slug : 'trial';
}

function lic_valid_until($lic) {
    return !empty($lic['expires_at']) ? date('Y-m-d', strtotime($lic['expires_at'])) : LIC_NO_EXPIRY_DATE;
}

function lic_company($lic) {
    foreach (['company_name', 'user_company'] as $k) {
        if (!empty($lic[$k])) return $lic[$k];
    }
    $name = trim(($lic['first_name'] ?? '') . ' ' . ($lic['last_name'] ?? ''));
    return $name !== '' ? $name : ($lic['user_email'] ?? '');
}

function lic_features($lic) {
    if (empty($lic['extra_features'])) return [];
    $f = json_decode($lic['extra_features'], true);
    return is_array($f) ? array_values($f) : [];
}

/** Comma separated text -> JSON array (or null) for licenses.extra_features. */
function lic_features_json($text) {
    $list = array_values(array_filter(array_map('trim', explode(',', (string)$text)), 'strlen'));
    return $list ? json_encode($list) : null;
}

/** null if the license may be used, else the protocol error code. */
function lic_state_error($lic, $today = null) {
    $today = $today ?: date('Y-m-d');
    $status = $lic['status'] ?? 'active';
    if ($status === 'revoked' || $status === 'suspended') return 'revoked';
    if ($status === 'expired') return 'expired';
    if (lic_valid_until($lic) < $today) return 'expired';
    return null;
}

/** Signed payload, identical in shape to the angajati one (fields adapted to NPI). */
function lic_payload(array $lic, $key, $fingerprint, $offline = false) {
    return [
        'v'            => 1,
        'key'          => $key,
        'company'      => lic_company($lic),
        'level'        => lic_tier($lic),
        'max_users'    => isset($lic['max_users']) && $lic['max_users'] !== '' && $lic['max_users'] !== null ? (int)$lic['max_users'] : null,
        'max_projects' => isset($lic['max_projects']) && $lic['max_projects'] !== '' && $lic['max_projects'] !== null ? (int)$lic['max_projects'] : null,
        'features'     => lic_features($lic),
        'valid_until'  => lic_valid_until($lic),
        'fingerprint'  => strtolower($fingerprint),
        'iat'          => time(),
        'grace_days'   => $offline ? LIC_OFFLINE_GRACE_DAYS : max(1, (int)($lic['grace_days'] ?? (defined('LICENSE_DEFAULT_GRACE_DAYS') ? LICENSE_DEFAULT_GRACE_DAYS : 7))),
        'offline'      => (bool)$offline,
    ];
}

// ── DB access ───────────────────────────────────────────────────────────

const LIC_SELECT = "SELECT l.*, p.slug AS plan_slug, p.name AS plan_name,
                           u.email AS user_email, u.company AS user_company,
                           u.first_name, u.last_name
                      FROM licenses l
                      JOIN plans p ON p.id = l.plan_id
                 LEFT JOIN users u ON u.id = l.user_id";

function lic_load($license_id) {
    return db_fetch(LIC_SELECT . ' WHERE l.id = ?', [(int)$license_id]) ?: null;
}

/**
 * License for a key: either the license key itself or one of its
 * installation keys (portal "Generate install key"). Returns
 * [license row|null, installation_key row|null].
 */
function lic_find($key) {
    $lic = db_fetch(LIC_SELECT . ' WHERE l.license_key = ?', [$key]);
    if ($lic) return [$lic, null];
    $ik = db_fetch('SELECT id, license_id, used_at FROM installation_keys WHERE key_value = ?', [$key]);
    if ($ik) {
        $lic = lic_load($ik['license_id']);
        if ($lic) return [$lic, $ik];
    }
    return [null, null];
}

function lic_audit($licId, $key, $action, $fp = null, $details = null) {
    try {
        db_insert('INSERT INTO license_audit (license_id, license_key, action, fingerprint, ip_address, details) VALUES (?, ?, ?, ?, ?, ?)',
            [$licId, $key, substr((string)$action, 0, 60), $fp, $_SERVER['REMOTE_ADDR'] ?? null, $details]);
    } catch (Throwable $e) {
        error_log('license_audit insert failed: ' . $e->getMessage());
    }
}

// ── Protocol handlers (return [http_code, body]) ────────────────────────

function lic_reject($lic, $key, $fp, $error, $code = 403) {
    lic_audit($lic['id'] ?? null, $key, 'rejected:' . $error, $fp);
    return [$code, ['success' => false, 'error' => $error]];
}

/** POST {license_key, fingerprint, hostname, app_version, protocol, projects, users} */
function lic_handle_validate(array $in) {
    $key = lic_normalize_key($in['license_key'] ?? '');
    $fp  = strtolower(trim((string)($in['fingerprint'] ?? '')));
    if (!lic_key_valid($key) || !lic_fp_valid($fp)) {
        return [400, ['success' => false, 'error' => 'bad_request']];
    }

    [$lic, $ik] = lic_find($key);
    if (!$lic) return lic_reject(null, $key, $fp, 'invalid_key', 404);
    $err = lic_state_error($lic);
    if ($err) return lic_reject($lic, $key, $fp, $err);

    $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
    $host = substr((string)($in['hostname'] ?? ''), 0, 100);
    $act  = db_fetch('SELECT * FROM license_activations WHERE license_id = ? AND machine_id = ?', [$lic['id'], $fp]);
    if ($act) {
        if (isset($act['is_active']) && !(int)$act['is_active']) {
            return lic_reject($lic, $key, $fp, 'machine_deactivated');
        }
        $action = 'validate';
    } else {
        $used = db_fetch('SELECT COUNT(*) AS c FROM license_activations WHERE license_id = ? AND is_active = 1', [$lic['id']]);
        $max  = max(1, (int)($lic['max_activations'] ?? 1));
        if ((int)($used['c'] ?? 0) >= $max) {
            return lic_reject($lic, $key, $fp, 'activation_limit');
        }
        $action = 'activate';
    }

    // Sign BEFORE touching the database: if the RSA key is missing/unreadable
    // lic_sign() throws and this returns 500 — the activation slot must NOT
    // stay consumed by a request that never delivered a token (a customer with
    // max_activations = 1 would be locked out of a working key).
    try {
        $token = lic_sign(lic_payload($lic, $key, $fp));
    } catch (Throwable $e) {
        error_log('lic_handle_validate: signing failed for ' . $key . ': ' . $e->getMessage());
        return [500, ['success' => false, 'error' => 'server_error']];
    }

    if ($act) {
        db_query('UPDATE license_activations SET last_seen = NOW(), last_ip = ?, machine_name = ? WHERE id = ?', [$ip, $host, $act['id']]);
    } else {
        db_insert('INSERT INTO license_activations (license_id, machine_id, machine_name, activated_at, last_seen, last_ip, is_active) VALUES (?, ?, ?, NOW(), NOW(), ?, 1)',
            [$lic['id'], $fp, $host, $ip]);
        if ($ik && empty($ik['used_at'])) {
            db_query('UPDATE installation_keys SET used_at = NOW() WHERE id = ?', [$ik['id']]);
        }
    }

    db_query('UPDATE licenses SET last_check = NOW(), last_check_ip = ?, app_version = ?, reported_projects = ?, reported_users = ? WHERE id = ?', [
        $ip,
        substr((string)($in['app_version'] ?? ''), 0, 20),
        isset($in['projects']) && is_numeric($in['projects']) ? (int)$in['projects'] : null,
        isset($in['users']) && is_numeric($in['users']) ? (int)$in['users'] : null,
        $lic['id'],
    ]);
    lic_audit($lic['id'], $key, $action, $fp, $host);

    return [200, ['success' => true, 'token' => $token]];
}

/** POST {license_key, fingerprint} — frees the activation slot */
function lic_handle_deactivate(array $in) {
    $key = lic_normalize_key($in['license_key'] ?? '');
    $fp  = strtolower(trim((string)($in['fingerprint'] ?? '')));
    if (lic_key_valid($key) && lic_fp_valid($fp)) {
        [$lic] = lic_find($key);
        if ($lic) {
            // Only active slots are removed: a machine blocked by the vendor stays blocked
            db_query('DELETE FROM license_activations WHERE license_id = ? AND machine_id = ? AND is_active = 1', [$lic['id'], $fp]);
            lic_audit($lic['id'], $key, 'deactivate', $fp);
        }
    }
    return [200, ['success' => true]];
}

/**
 * Offline token for the app's "offline activation" form (activate_offline()).
 * $permanent = true -> no heartbeat required (grace 36500 days, offline=true),
 * valid until the license expiry date and NOT revocable remotely.
 * $permanent = false -> normal token: the app must reach the server within grace_days.
 */
function lic_offline_token(array $lic, $fingerprint, $permanent = true, $actor = 'staff') {
    $fp = lic_normalize_fp($fingerprint);
    if (!lic_fp_valid($fp)) {
        throw new InvalidArgumentException('Invalid fingerprint (64 hex chars expected)');
    }
    $token = lic_sign(lic_payload($lic, $lic['license_key'], $fp, (bool)$permanent));
    lic_audit($lic['id'], $lic['license_key'], 'admin:' . $actor . ':offline_token', $fp, $permanent ? 'permanent' : 'grace');
    return $token;
}

/**
 * Verify a token presented by an installed app (update check / download).
 * Returns [ok, plan_slug|null, error|null].
 */
function lic_check_token($token, $fingerprint) {
    if (!$token || !$fingerprint) return [false, null, 'Missing license'];
    try {
        $p = lic_verify($token);
    } catch (Throwable $e) {
        error_log('lic_check_token: ' . $e->getMessage());
        return [false, null, 'License verification unavailable'];
    }
    if (!$p) return [false, null, 'Invalid license token'];
    $fp = lic_normalize_fp($fingerprint);
    if (($p['fingerprint'] ?? '') !== $fp) return [false, null, 'Machine mismatch'];
    [$lic] = lic_find(lic_normalize_key($p['key'] ?? ''));
    if (!$lic) return [false, null, 'License not found'];
    $err = lic_state_error($lic);
    if ($err) return [false, null, 'License ' . $err];
    $act = db_fetch('SELECT is_active FROM license_activations WHERE license_id = ? AND machine_id = ?', [$lic['id'], $fp]);
    if (empty($p['offline']) && (!$act || !(int)$act['is_active'])) {
        return [false, null, 'Machine not activated for this license'];
    }
    return [true, lic_tier($lic), null];
}
