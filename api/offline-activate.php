<?php
/**
 * offline-activate.php — self-service offline activation via QR code + OTP.
 *
 * Flow (installer / offline server without Internet):
 * 1. The installer builds a challenge = base64url(JSON {install_key|license_key,
 *    machine_id (64-hex fingerprint), hostname}) and shows it as a QR code.
 * 2. The operator scans the QR with a smartphone, which opens
 *    GET /api/offline-activate.php?challenge=<base64url>
 * 3. The server checks the license and shows an 8-char one-time code (30 min).
 * 4. The operator types the code in the installer, which sends
 *    POST {install_key|license_key, machine_id, response_code}
 * 5. The server registers the activation (respecting max_activations) and
 *    returns an RSA token with offline=true, accepted by
 *    license_service.activate_offline() in the app.
 *
 * Staff can also issue offline tokens manually from /staff/licenses/offline.php.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_lib.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function offline_fail($code, $error) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'success' => false, 'error' => $error]);
    exit;
}

function generate_otp($length = 8) {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= LIC_KEY_ALPHABET[random_int(0, strlen(LIC_KEY_ALPHABET) - 1)];
    }
    return $code;
}

/** Installation key row used to hold the OTP (created for plain license keys). */
function offline_install_key_row($lic, $ik) {
    if ($ik) return db_fetch('SELECT * FROM installation_keys WHERE id = ?', [$ik['id']]);
    $row = db_fetch('SELECT * FROM installation_keys WHERE license_id = ? ORDER BY id DESC LIMIT 1', [$lic['id']]);
    if ($row) return $row;
    $id = db_insert('INSERT INTO installation_keys (license_id, key_value) VALUES (?, ?)', [$lic['id'], lic_generate_key()]);
    return db_fetch('SELECT * FROM installation_keys WHERE id = ?', [$id]);
}

// ── STEP 1: GET with challenge → OTP page ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $challenge = json_decode((string)lic_b64u_decode($_GET['challenge'] ?? ''), true);
    if (!is_array($challenge)) offline_fail(400, 'Invalid challenge');

    $key = lic_normalize_key($challenge['install_key'] ?? ($challenge['license_key'] ?? ''));
    $fp  = lic_normalize_fp($challenge['machine_id'] ?? ($challenge['fingerprint'] ?? ''));
    if (!lic_key_valid($key) || !lic_fp_valid($fp)) offline_fail(400, 'bad_request');

    [$lic, $ik] = lic_find($key);
    if (!$lic) offline_fail(404, 'invalid_key');
    $err = lic_state_error($lic);
    if ($err) offline_fail(403, $err);

    $row = offline_install_key_row($lic, $ik);
    $otp = generate_otp(8);
    db_query(
        "UPDATE installation_keys SET offline_code = ?, machine_id_pending = ?, offline_code_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?",
        [$otp, $fp, $row['id']]
    );
    lic_audit($lic['id'], $key, 'offline_challenge', $fp, substr((string)($challenge['hostname'] ?? ''), 0, 100));

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="robots" content="noindex"><title>GTMC-NPI Activation Code</title>';
    echo '<style>body{font-family:Arial,sans-serif;text-align:center;padding:40px;background:#0f172a;color:#e2e8f0}';
    echo '.code{font-size:2.6em;letter-spacing:8px;color:#34d399;font-weight:bold;padding:20px;';
    echo 'background:#020617;border-radius:12px;margin:30px auto;display:inline-block}</style></head><body>';
    echo '<h2>&#128273; GTMC-NPI</h2>';
    echo '<p>Enter this code in the installer / Introduceți acest cod în aplicație:</p>';
    echo '<div class="code">' . htmlspecialchars($otp) . '</div>';
    echo '<p style="color:#94a3b8">Valid for 30 minutes.</p>';
    echo '</body></html>';
    exit;
}

// ── STEP 2: POST with response_code → offline token ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST ?: [];

    $key  = lic_normalize_key($input['install_key'] ?? ($input['license_key'] ?? ''));
    $fp   = lic_normalize_fp($input['machine_id'] ?? ($input['fingerprint'] ?? ''));
    $code = strtoupper(trim((string)($input['response_code'] ?? '')));
    if (!lic_key_valid($key) || !lic_fp_valid($fp) || !preg_match('/^[A-Z0-9]{8}$/', $code)) {
        offline_fail(400, 'bad_request');
    }

    [$lic, $ik] = lic_find($key);
    if (!$lic) offline_fail(404, 'invalid_key');
    $err = lic_state_error($lic);
    if ($err) offline_fail(403, $err);

    $row = db_fetch(
        "SELECT * FROM installation_keys
          WHERE license_id = ? AND offline_code = ? AND machine_id_pending = ?
            AND (offline_code_expires IS NULL OR offline_code_expires > NOW())",
        [$lic['id'], $code, $fp]
    );
    if (!$row) {
        lic_audit($lic['id'], $key, 'rejected:offline_code', $fp);
        offline_fail(403, 'Invalid or expired activation code');
    }

    $act = db_fetch('SELECT * FROM license_activations WHERE license_id = ? AND machine_id = ?', [$lic['id'], $fp]);
    if ($act && !(int)$act['is_active']) offline_fail(403, 'machine_deactivated');
    if (!$act) {
        $used = db_fetch('SELECT COUNT(*) AS c FROM license_activations WHERE license_id = ? AND is_active = 1', [$lic['id']]);
        if ((int)($used['c'] ?? 0) >= max(1, (int)$lic['max_activations'])) {
            offline_fail(403, 'activation_limit');
        }
        db_insert(
            'INSERT INTO license_activations (license_id, machine_id, machine_name, activated_at, last_seen, last_ip, is_active) VALUES (?, ?, ?, NOW(), NOW(), ?, 1)',
            [$lic['id'], $fp, substr((string)($input['hostname'] ?? 'Offline'), 0, 100), $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }

    db_query(
        'UPDATE installation_keys SET offline_code = NULL, machine_id_pending = NULL, offline_code_expires = NULL, used_at = IFNULL(used_at, NOW()) WHERE id = ?',
        [$row['id']]
    );

    try {
        $token = lic_sign(lic_payload($lic, $key, $fp, true));
    } catch (Throwable $e) {
        error_log('offline-activate.php: ' . $e->getMessage());
        offline_fail(500, 'server_error');
    }
    lic_audit($lic['id'], $key, 'offline_activate', $fp);

    echo json_encode([
        'ok'            => true,
        'success'       => true,
        'token'         => $token,
        'license_token' => $token,
        'plan'          => lic_tier($lic),
        'expires_at'    => lic_valid_until($lic),
        'offline'       => true,
    ]);
    exit;
}

offline_fail(405, 'method');
