<?php
/**
 * api.php — GTMC-NPI license server endpoint (router on ?action=).
 *
 * This is the URL configured in the application as
 *     LICENSE_SERVER_URL=https://gtmc-npi.eu/api.php
 *
 * Actions:
 *   POST ?action=license_validate    activation + heartbeat, returns a signed token
 *   POST ?action=license_deactivate  frees this machine's activation slot
 *   GET  ?action=health              liveness probe (no secrets)
 *
 * Contract and token format: see includes/license_lib.php.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/license_lib.php';

function api_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'license_validate':
    case 'license_deactivate':
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            api_json(['success' => false, 'error' => 'method'], 405);
        }
        $raw = file_get_contents('php://input');
        if (strlen($raw) > 16384) {
            api_json(['success' => false, 'error' => 'bad_request'], 413);
        }
        $in = json_decode($raw, true);
        if (!is_array($in)) {
            $in = $_POST ?: [];
        }
        try {
            [$code, $body] = $action === 'license_validate'
                ? lic_handle_validate($in)
                : lic_handle_deactivate($in);
            api_json($body, $code);
        } catch (Throwable $e) {
            error_log('License server error: ' . $e->getMessage());
            api_json(['success' => false, 'error' => 'server_error'], 500);
        }
        break;

    case 'health':
        $db_ok = true;
        try {
            db_fetch('SELECT 1 AS ok');
        } catch (Throwable $e) {
            $db_ok = false;
        }
        api_json([
            'status'  => ($db_ok && lic_signing_available()) ? 'ok' : 'degraded',
            'service' => 'gtmc-npi-license',
            'db'      => $db_ok,
            'signing' => lic_signing_available(),
            'time'    => gmdate('c'),
        ]);
        break;

    default:
        api_json(['success' => false, 'error' => 'invalid_action'], 400);
}
