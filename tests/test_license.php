<?php
/**
 * Tests for the license server (includes/license_lib.php) — no MySQL needed.
 *
 *   php tests/test_license.php
 *
 * db_fetch / db_query / db_insert are replaced by a tiny in-memory fake that
 * understands exactly the statements used by license_lib.php, and a throw-away
 * RSA key is generated for the run. Skipped (exit 0) without the openssl extension.
 */

if (!function_exists('openssl_pkey_new')) {
    echo "  SKIP     openssl extension not loaded\n";
    exit(0);
}

$cfg = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
$tmp = @openssl_pkey_new($cfg);
if (!$tmp) {
    // Windows builds need an explicit openssl.cnf (shipped in PHP's extras/ssl)
    $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
    if (is_file($cnf)) $tmp = @openssl_pkey_new($cfg + ['config' => $cnf]);
}
if (!$tmp || !openssl_pkey_export($tmp, $TEST_PEM, null, isset($cnf) ? ['config' => $cnf] : [])) {
    echo "  SKIP     cannot generate an RSA key (openssl.cnf not found)\n";
    exit(0);
}
define('LICENSE_RSA_PRIVATE_KEY_PATH', '');
define('LICENSE_RSA_PRIVATE_KEY', $TEST_PEM);
define('LICENSE_DEFAULT_GRACE_DAYS', 7);

// ── In-memory DB fake ───────────────────────────────────────────────────
$DB = ['licenses' => [], 'activations' => [], 'ikeys' => [], 'audit' => [], 'next' => 1];

function fake_license_row($l) {
    return $l + ['plan_slug' => $l['plan'], 'plan_name' => ucfirst($l['plan']), 'user_email' => 'hr@example.ro',
                 'user_company' => 'Firma Test SRL', 'first_name' => 'Ana', 'last_name' => 'Pop'];
}

function db_fetch($sql, $p = []) {
    global $DB;
    $sql = preg_replace('/\s+/', ' ', $sql);
    if (strpos($sql, 'WHERE l.license_key = ?') !== false) {
        foreach ($DB['licenses'] as $l) if ($l['license_key'] === $p[0]) return fake_license_row($l);
        return false;
    }
    if (strpos($sql, 'WHERE l.id = ?') !== false) {
        return isset($DB['licenses'][$p[0]]) ? fake_license_row($DB['licenses'][$p[0]]) : false;
    }
    if (strpos($sql, 'FROM installation_keys WHERE key_value = ?') !== false) {
        foreach ($DB['ikeys'] as $k) if ($k['key_value'] === $p[0]) return $k;
        return false;
    }
    if (strpos($sql, 'COUNT(*) AS c FROM license_activations') !== false) {
        $c = 0;
        foreach ($DB['activations'] as $a) if ($a['license_id'] == $p[0] && $a['is_active']) $c++;
        return ['c' => $c];
    }
    if (strpos($sql, 'FROM license_activations WHERE license_id = ? AND machine_id = ?') !== false) {
        foreach ($DB['activations'] as $a) if ($a['license_id'] == $p[0] && $a['machine_id'] === $p[1]) return $a;
        return false;
    }
    throw new RuntimeException("fake db_fetch: unexpected SQL: $sql");
}

function db_insert($sql, $p = []) {
    global $DB;
    $id = $DB['next']++;
    if (strpos($sql, 'INSERT INTO license_audit') !== false) {
        $DB['audit'][] = ['license_id' => $p[0], 'key' => $p[1], 'action' => $p[2], 'fp' => $p[3]];
    } elseif (strpos($sql, 'INSERT INTO license_activations') !== false) {
        $DB['activations'][$id] = ['id' => $id, 'license_id' => $p[0], 'machine_id' => $p[1], 'machine_name' => $p[2], 'is_active' => 1];
    } else {
        throw new RuntimeException("fake db_insert: unexpected SQL: $sql");
    }
    return $id;
}

function db_query($sql, $p = []) {
    global $DB;
    if (strpos($sql, 'UPDATE license_activations SET last_seen') === 0) return true;
    if (strpos($sql, 'UPDATE licenses SET last_check') === 0) {
        $DB['licenses'][$p[4]]['app_version'] = $p[1];
        $DB['licenses'][$p[4]]['reported_projects'] = $p[2];
        return true;
    }
    if (strpos($sql, 'UPDATE installation_keys SET used_at') === 0) {
        foreach ($DB['ikeys'] as $i => $k) if ($k['id'] == $p[0]) $DB['ikeys'][$i]['used_at'] = date('Y-m-d H:i:s');
        return true;
    }
    if (strpos($sql, 'DELETE FROM license_activations') === 0) {
        foreach ($DB['activations'] as $i => $a) if ($a['license_id'] == $p[0] && $a['machine_id'] === $p[1] && $a['is_active']) unset($DB['activations'][$i]);
        return true;
    }
    throw new RuntimeException("fake db_query: unexpected SQL: $sql");
}

require_once __DIR__ . '/../includes/license_lib.php';

// ── Harness ─────────────────────────────────────────────────────────────
$falliti = 0;
$eseguiti = 0;
function prova($descrizione, $condizione) {
    global $falliti, $eseguiti;
    $eseguiti++;
    echo ($condizione ? "  ok       " : "  FALLITO  ") . "$descrizione\n";
    if (!$condizione) $falliti++;
}

function add_license($key, $plan, array $extra = []) {
    global $DB;
    $id = $DB['next']++;
    $DB['licenses'][$id] = $extra + ['id' => $id, 'license_key' => $key, 'plan' => $plan, 'plan_id' => 1, 'user_id' => 1,
        'status' => 'active', 'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')), 'max_activations' => 1,
        'grace_days' => 7, 'max_projects' => 25, 'max_users' => 5, 'extra_features' => null, 'company_name' => 'Firma Test SRL'];
    return $id;
}

$FP1 = hash('sha256', 'machine-1');
$FP2 = hash('sha256', 'machine-2');

echo "\n── Keys and fingerprints ──\n";
$k = lic_generate_key();
prova('generated key has the NPI-XXXXX-XXXXX-XXXXX-XXXXX format', preg_match('/^NPI(-[A-HJ-NP-Z2-9]{5}){4}$/', $k) === 1);
prova('NPI key format is accepted', lic_key_valid('NPI-ABCDE-FGHJK-LMNPQ-RSTUV'));
prova('dashed upper-case fingerprint is normalized', lic_normalize_fp(strtoupper(implode('-', str_split($FP1, 8)))) === $FP1);
prova('short fingerprint rejected', !lic_fp_valid('abc'));

echo "\n── license_validate ──\n";
$prof = add_license('NPI-AAAAA-BBBBB-CCCCC-DDDDD', 'professional', ['extra_features' => json_encode(['iatf_profile'])]);
[$code, $body] = lic_handle_validate(['license_key' => 'npi-aaaaa-bbbbb-ccccc-ddddd', 'fingerprint' => $FP1, 'hostname' => 'PC-NPI', 'app_version' => '1.0.0', 'projects' => 12]);
prova('valid key -> 200 + token', $code === 200 && !empty($body['success']) && !empty($body['token']));
$payload = lic_verify($body['token'] ?? '');
prova('token signature verifies with the public key', is_array($payload));
prova('payload: key, level, fingerprint', ($payload['key'] ?? '') === 'NPI-AAAAA-BBBBB-CCCCC-DDDDD' && ($payload['level'] ?? '') === 'professional' && ($payload['fingerprint'] ?? '') === $FP1);
prova('payload: company, limits, add-ons, grace 7, online', ($payload['company'] ?? '') === 'Firma Test SRL' && $payload['max_projects'] === 25
      && $payload['max_users'] === 5 && $payload['features'] === ["iatf_profile"] && $payload['grace_days'] === 7 && $payload['offline'] === false && $payload['v'] === 1);
prova('payload: valid_until = expiry date', $payload['valid_until'] === date('Y-m-d', strtotime($DB['licenses'][$prof]['expires_at'])));
prova('telemetry stored (app_version, projects)', $DB['licenses'][$prof]['app_version'] === '1.0.0' && $DB['licenses'][$prof]['reported_projects'] === 12);
prova('activation recorded', count($DB['activations']) === 1);
[$code] = lic_handle_validate(['license_key' => 'NPI-AAAAA-BBBBB-CCCCC-DDDDD', 'fingerprint' => $FP1]);
prova('same machine re-validates (heartbeat) -> 200', $code === 200 && count($DB['activations']) === 1);
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-AAAAA-BBBBB-CCCCC-DDDDD', 'fingerprint' => $FP2]);
prova('second machine over max_activations -> 403 activation_limit', $code === 403 && $body['error'] === 'activation_limit');
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ', 'fingerprint' => $FP1]);
prova('unknown key -> 404 invalid_key', $code === 404 && $body['error'] === 'invalid_key');
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-AAAAA-BBBBB-CCCCC-DDDDD', 'fingerprint' => 'nothex']);
prova('bad fingerprint -> 400 bad_request', $code === 400 && $body['error'] === 'bad_request');
[$code, $body] = lic_handle_validate([]);
prova('empty body -> 400 bad_request', $code === 400 && $body['error'] === 'bad_request');

add_license('NPI-REVOK-EDREV-OKEDR-EVOKE', 'professional', ['status' => 'revoked']);
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-REVOK-EDREV-OKEDR-EVOKE', 'fingerprint' => $FP1]);
prova('revoked -> 403 revoked', $code === 403 && $body['error'] === 'revoked');
add_license('NPI-EXPIR-EDEXP-IREDE-XPIRE', 'professional', ['expires_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-EXPIR-EDEXP-IREDE-XPIRE', 'fingerprint' => $FP1]);
prova('expired -> 403 expired', $code === 403 && $body['error'] === 'expired');
$ent = add_license('NPI-ENTER-PRISE-NOEXP-IRYXX', 'enterprise', ['expires_at' => null, 'max_projects' => null, 'max_activations' => 3]);
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-ENTER-PRISE-NOEXP-IRYXX', 'fingerprint' => $FP2]);
$p = lic_verify($body['token'] ?? '');
prova('enterprise without expiry -> valid_until 2099-12-31, unlimited projects', $code === 200 && $p['valid_until'] === '2099-12-31' && $p['max_projects'] === null);

// machine blocked by the vendor
$DB['activations'][] = ['id' => 999, 'license_id' => $ent, 'machine_id' => $FP1, 'machine_name' => 'X', 'is_active' => 0];
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-ENTER-PRISE-NOEXP-IRYXX', 'fingerprint' => $FP1]);
prova('machine blocked by staff -> 403 machine_deactivated', $code === 403 && $body['error'] === 'machine_deactivated');

echo "\n── Installation keys ──\n";
$DB['ikeys'][] = ['id' => 77, 'license_id' => $ent, 'key_value' => 'NPI-INSTA-LLKEY-ABCDE-FGHJK', 'used_at' => null];
$FP3 = hash('sha256', 'machine-3');
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-INSTA-LLKEY-ABCDE-FGHJK', 'fingerprint' => $FP3]);
$p = lic_verify($body['token'] ?? '');
prova('installation key activates its parent license', $code === 200 && $p['level'] === 'enterprise' && $p['key'] === 'NPI-INSTA-LLKEY-ABCDE-FGHJK');
prova('installation key marked as used', !empty($DB['ikeys'][0]['used_at']));

echo "\n── license_deactivate ──\n";
[$code, $body] = lic_handle_deactivate(['license_key' => 'NPI-AAAAA-BBBBB-CCCCC-DDDDD', 'fingerprint' => $FP1]);
prova('deactivate -> 200 success', $code === 200 && $body['success'] === true);
[$code] = lic_handle_validate(['license_key' => 'NPI-AAAAA-BBBBB-CCCCC-DDDDD', 'fingerprint' => $FP2]);
prova('freed slot can be used by another machine', $code === 200);
lic_handle_deactivate(['license_key' => 'NPI-ENTER-PRISE-NOEXP-IRYXX', 'fingerprint' => $FP1]);
[$code, $body] = lic_handle_validate(['license_key' => 'NPI-ENTER-PRISE-NOEXP-IRYXX', 'fingerprint' => $FP1]);
prova('client deactivate does NOT unblock a vendor-blocked machine', $code === 403 && $body['error'] === 'machine_deactivated');
[$code, $body] = lic_handle_deactivate(['license_key' => 'garbage!', 'fingerprint' => 'x']);
prova('deactivate with bad input still answers 200 (no oracle)', $code === 200 && $body['success'] === true);

echo "\n── Offline token ──\n";
$lic = lic_load($prof);
$dashed = strtoupper(implode('-', str_split($FP1, 8)));
$tok = lic_offline_token($lic, $dashed, true, 'test');
$p = lic_verify($tok);
prova('permanent offline token: grace 36500, offline=true', $p['grace_days'] === 36500 && $p['offline'] === true && $p['fingerprint'] === $FP1);
$p = lic_verify(lic_offline_token($lic, $FP1, false, 'test'));
prova('grace offline token: grace = license grace_days', $p['grace_days'] === 7 && $p['offline'] === false);
$thrown = false;
try { lic_offline_token($lic, 'bad', true); } catch (InvalidArgumentException $e) { $thrown = true; }
prova('offline token with invalid fingerprint throws', $thrown);
prova('offline issue is audited', (bool)array_filter($DB['audit'], function ($a) { return $a['action'] === 'admin:test:offline_token'; }));

echo "\n── Token checks (updates/downloads) ──\n";
[$ok, $plan] = lic_check_token($tok, $FP1);
prova('offline token accepted for updates without activation row', $ok && $plan === 'professional');
[$ok, , $err] = lic_check_token($tok, $FP2);
prova('token on another machine -> Machine mismatch', !$ok && $err === 'Machine mismatch');
$forged = explode('.', $tok);
$fp = lic_peek($tok); $fp['level'] = 'enterprise';
$forged = lic_b64u(json_encode($fp)) . '.' . $forged[1];
prova('tampered payload rejected', lic_verify($forged) === null);
[$ok, , $err] = lic_check_token('', $FP1);
prova('missing token rejected', !$ok && $err === 'Missing license');
$DB['licenses'][$prof]['status'] = 'revoked';
[$ok, , $err] = lic_check_token($tok, $FP1);
prova('token of a revoked license rejected', !$ok && $err === 'License revoked');

echo "\n── Audit ──\n";
$actions = array_column($DB['audit'], 'action');
prova('activate / validate / rejected / deactivate audited', in_array('activate', $actions) && in_array('validate', $actions)
      && in_array('rejected:invalid_key', $actions) && in_array('deactivate', $actions));

echo "\n";
if ($falliti) {
    echo "FALLITE $falliti prove su $eseguiti.\n";
    exit(1);
}
echo "Tutte le $eseguiti prove sono passate.\n";
exit(0);
