<?php
/**
 * validate.php — verify a GTMC-NPI license token.
 *
 * GET|POST /api/licenses/validate.php
 *   license_token  the RSA token stored by the app (instance/license.json)
 *   machine_id     the 64-hex machine fingerprint
 *
 * Response:
 * {
 *   "valid": true, "ok": true,
 *   "status": "active|expiring_soon|expired|revoked",
 *   "plan": "professional", "license_key": "NPI-…", "company": "…",
 *   "valid_until": "2027-12-31", "days_remaining": 365,
 *   "offline": false, "machine_active": true
 * }
 *
 * Used by includes/releases.php (update check / update download) and by
 * support tools. The signature is checked with the public key derived from
 * the server private key, exactly like the application does.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/license_lib.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    $input = is_array($json) ? $json : ($_POST ?: []);
} else {
    $input = $_GET;
}

$token = trim((string)($input['license_token'] ?? ($input['token'] ?? '')));
$fp    = lic_normalize_fp($input['machine_id'] ?? ($input['fingerprint'] ?? ''));

if ($token === '') {
    http_response_code(400);
    echo json_encode(['valid' => false, 'ok' => false, 'error' => 'Missing license_token']);
    exit;
}

try {
    $payload = lic_verify($token);
} catch (Throwable $e) {
    error_log('validate.php: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['valid' => false, 'ok' => false, 'error' => 'License verification unavailable']);
    exit;
}

if (!$payload) {
    http_response_code(403);
    echo json_encode(['valid' => false, 'ok' => false, 'error' => 'Invalid license token']);
    exit;
}

if ($fp !== '' && ($payload['fingerprint'] ?? '') !== $fp) {
    http_response_code(403);
    echo json_encode(['valid' => false, 'ok' => false, 'error' => 'Machine ID mismatch']);
    exit;
}
$fp = $payload['fingerprint'] ?? $fp;

[$lic] = lic_find(lic_normalize_key($payload['key'] ?? ''));
if (!$lic) {
    http_response_code(404);
    echo json_encode(['valid' => false, 'ok' => false, 'error' => 'License not found']);
    exit;
}

$valid_until = lic_valid_until($lic);
$days_remaining = (int)floor((strtotime($valid_until . ' 23:59:59') - time()) / 86400);
$err = lic_state_error($lic);
$status = $err ?: ($days_remaining <= 30 ? 'expiring_soon' : 'active');

$act = db_fetch('SELECT id, is_active FROM license_activations WHERE license_id = ? AND machine_id = ?', [$lic['id'], $fp]);
$machine_active = $act && (int)$act['is_active'] === 1;
if ($act) {
    db_query('UPDATE license_activations SET last_seen = NOW() WHERE id = ?', [$act['id']]);
}

$valid = !$err && ($machine_active || !empty($payload['offline']));

echo json_encode([
    'valid'          => $valid,
    'ok'             => $valid,
    'status'         => $status,
    'error'          => $valid ? null : ($err ?: 'Machine not activated for this license'),
    'license_id'     => (int)$lic['id'],
    'license_key'    => $payload['key'] ?? null,
    'plan'           => lic_tier($lic),
    'plan_name'      => $lic['plan_name'] ?? null,
    'company'        => lic_company($lic),
    'valid_until'    => $valid_until,
    'expires_at'     => $lic['expires_at'] ?? null,
    'days_remaining' => $days_remaining,
    'offline'        => !empty($payload['offline']),
    'machine_active' => $machine_active,
    'features'       => lic_features($lic),
    'limits' => [
        'max_projects' => isset($lic['max_projects']) && $lic['max_projects'] !== null ? (int)$lic['max_projects'] : null,
        'max_users'    => isset($lic['max_users']) && $lic['max_users'] !== null ? (int)$lic['max_users'] : null,
    ],
]);
