<?php
/**
 * activate.php — installer / legacy-style activation endpoint.
 *
 * POST /api/activate.php  {install_key|license_key, machine_id, machine_name}
 *
 * For GTMC-NPI the activation protocol is the RSA one of /api.php
 * (?action=license_validate): this endpoint is a thin adapter for the
 * Windows installer and for older clients that still send
 * install_key/machine_id. `machine_id` MUST be the 64-hex machine
 * fingerprint computed by the app (license_service.get_fingerprint()),
 * otherwise the token would not be accepted by the application.
 *
 * Response (superset, for both client styles):
 *   {success, ok, token, license_token, plan, expires_at, status}
 *   or {success:false, ok:false, error}
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_lib.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'ok' => false, 'error' => 'method']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    // Fallback: form-urlencoded
    $input = $_POST ?: [];
}

$key = $input['install_key'] ?? ($input['license_key'] ?? '');
$fp  = lic_normalize_fp($input['machine_id'] ?? ($input['fingerprint'] ?? ''));

try {
    [$code, $body] = lic_handle_validate([
        'license_key' => $key,
        'fingerprint' => $fp,
        'hostname'    => $input['machine_name'] ?? ($input['hostname'] ?? ''),
        'app_version' => $input['app_version'] ?? '',
        'projects'   => $input['projects'] ?? null,
        'users'       => $input['users'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('activate.php: ' . $e->getMessage());
    $code = 500;
    $body = ['success' => false, 'error' => 'server_error'];
}

http_response_code($code);
if (!empty($body['success'])) {
    $p = lic_peek($body['token']);
    echo json_encode([
        'success'       => true,
        'ok'            => true,
        'token'         => $body['token'],
        'license_token' => $body['token'],
        'plan'          => $p['level'] ?? null,
        'expires_at'    => $p['valid_until'] ?? null,
        'grace_days'    => $p['grace_days'] ?? null,
        'status'        => 'active',
    ]);
} else {
    echo json_encode(['success' => false, 'ok' => false, 'error' => $body['error'] ?? 'error']);
}
