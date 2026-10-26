<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/../includes/staff_functions.php';
require_once __DIR__ . '/../../includes/license_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /staff/licenses/');
    exit;
}

if (!staff_csrf_ok()) {
    http_response_code(400);
    die('CSRF token validation failed.');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /staff/licenses/');
    exit;
}

try {
    $lic = db_fetch("SELECT license_key FROM licenses WHERE id = ?", [$id]);
    // Revoke license: the next online check of every machine returns "revoked"
    db_query("UPDATE licenses SET status = 'revoked', updated_at = NOW() WHERE id = ?", [$id]);

    // Delete all associated API tokens
    db_query("DELETE FROM api_tokens WHERE license_id = ?", [$id]);

    $staff = get_staff_user();
    lic_audit($id, $lic['license_key'] ?? null, 'admin:revoke', null, 'by ' . ($staff['email'] ?? 'staff'));
    flash("License #$id has been revoked. Installed apps stop at their next online check (permanent offline tokens cannot be revoked remotely).");
} catch (Exception $e) {
    error_log('Revoke failed: ' . $e->getMessage());
    flash("Error revoking license.", "error");
}

header('Location: /staff/licenses/');
exit;
