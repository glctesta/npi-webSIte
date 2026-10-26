<?php
/**
 * Offline activation token generator (port of bridge_site admin "Offline token"
 * and license_tools/issue_token.py).
 *
 * The customer opens GTMC-NPI → Configurare → Licență on a server with no
 * internet access, reads the machine fingerprint and sends it to us. We paste
 * it here, the token is signed with the production RSA key and the customer
 * pastes it into the app's "Activare offline" box.
 */
require_once __DIR__ . '/../includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/../includes/staff_functions.php';
require_once __DIR__ . '/../../includes/license_lib.php';

$page_title = "Offline token";
$staff = get_staff_user();

$licenses = [];
try {
    $licenses = db_fetch_all("SELECT l.id, l.license_key, l.status, l.expires_at, p.slug AS tier,
                                     COALESCE(l.company_name, u.company, u.email) AS company
                              FROM licenses l JOIN plans p ON p.id = l.plan_id LEFT JOIN users u ON u.id = l.user_id
                              WHERE l.license_key IS NOT NULL
                              ORDER BY company ASC");
} catch (Exception $e) {}

$selected_id = (int)($_POST['license_id'] ?? $_GET['id'] ?? 0);
$fingerprint = trim($_POST['fingerprint'] ?? '');
$permanent   = ($_POST['mode'] ?? 'permanent') === 'permanent';
$token = null;
$error = null;
$lic = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_csrf_ok()) {
        http_response_code(400);
        die('CSRF token validation failed.');
    }
    $lic = $selected_id ? lic_load($selected_id) : null;
    $fp  = lic_normalize_fp($fingerprint);
    if (!$lic || empty($lic['license_key'])) {
        $error = 'Select a license.';
    } elseif (!lic_fp_valid($fp)) {
        $error = 'The fingerprint must contain 64 hexadecimal characters (dashes and spaces are ignored).';
    } elseif ($state = lic_state_error($lic)) {
        $error = "This license is $state: renew / reactivate it first.";
    } elseif (!lic_signing_available()) {
        $error = 'The RSA private key is not configured on this server (LICENSE_RSA_PRIVATE_KEY_PATH).';
    } else {
        try {
            $token = lic_offline_token($lic, $fp, $permanent, $staff['email'] ?? 'staff');
        } catch (Throwable $e) {
            error_log('offline token: ' . $e->getMessage());
            $error = 'Token generation failed: see the PHP error log.';
        }
    }

    if ($token && ($_POST['download'] ?? '') === '1') {
        $fname = 'GTMC-NPI-offline-token-' . preg_replace('/[^A-Z0-9-]/', '', $lic['license_key']) . '-' . substr($fp, 0, 8) . '.txt';
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('X-Content-Type-Options: nosniff');
        echo "GTMC-NPI — offline activation token\r\n";
        echo "License: {$lic['license_key']}\r\nCompany: " . lic_company($lic) . "\r\n";
        echo "Machine fingerprint: $fp\r\nValid until: " . lic_valid_until($lic) . "\r\n";
        echo "Mode: " . ($permanent ? 'permanent (no internet required)' : 'grace ' . (int)$lic['grace_days'] . ' days') . "\r\n\r\n";
        echo "Paste the line below in GTMC-NPI → Configurare → Licență → Activare offline:\r\n\r\n";
        echo $token . "\r\n";
        exit;
    }
}

include __DIR__ . '/../includes/staff_header.php';
$field = 'width: 100%; padding: 0.5rem; margin-top: 0.4rem; border-radius: 4px; border: 1px solid var(--border-glass); background: var(--bg-glass); color: var(--text-main);';
$csrf = htmlspecialchars($_SESSION['csrf_token']);
?>

<div class="glass-card" style="max-width: 820px; margin: 0 auto; padding: 1.5rem;">
    <h3 style="margin-bottom: .5rem;">📴 Offline activation token</h3>
    <p style="color: var(--text-muted); margin-bottom: 1.25rem;">
        For servers without internet access. The customer reads the <strong>fingerprint</strong> in
        GTMC-NPI → Configurare → Licență and sends it to us; the token is bound to that machine only.
    </p>

    <?php if (!lic_signing_available()): ?>
        <div class="alert alert-warning" style="margin-bottom:1rem;">⚠️ RSA private key not configured: set LICENSE_RSA_PRIVATE_KEY_PATH in .env (see DEPLOY.md).</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div style="margin-bottom: 1rem;">
            <label>🔑 License</label>
            <select name="license_id" required style="<?= $field ?>">
                <option value="">— Select license —</option>
                <?php foreach ($licenses as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= (int)$l['id'] === $selected_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['company'] . ' — ' . $l['license_key'] . ' — ' . $l['tier'] . ' — ' . $l['status'] . ' — ' . ($l['expires_at'] ? substr($l['expires_at'], 0, 10) : 'no expiry')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom: 1rem;">
            <label>🖥️ Machine fingerprint</label>
            <input type="text" name="fingerprint" required value="<?= htmlspecialchars($fingerprint) ?>"
                   placeholder="e.g. 3F2A9C1B-… (64 hex characters, dashes allowed)" style="<?= $field ?> font-family: monospace;">
        </div>
        <div style="margin-bottom: 1rem;">
            <label><input type="radio" name="mode" value="permanent" <?= $permanent ? 'checked' : '' ?>>
                <strong>Permanent</strong> — works until the license expiry without ever contacting the server
                (cannot be revoked remotely)</label><br>
            <label><input type="radio" name="mode" value="grace" <?= $permanent ? '' : 'checked' ?>>
                <strong>Grace</strong> — normal token: the app must reach the server within the license grace days</label>
        </div>
        <button type="submit" class="btn btn-primary">Generate token</button>
    </form>

    <?php if ($token): ?>
    <div style="margin-top: 1.5rem; background: rgba(16,185,129,.08); border-left: 4px solid #10b981; padding: 1rem; border-radius: 4px;">
        <p style="margin:0 0 .5rem;"><strong>Token for <?= htmlspecialchars(lic_company($lic)) ?></strong>
            · valid until <?= htmlspecialchars(lic_valid_until($lic)) ?> · <?= $permanent ? 'permanent' : 'grace ' . (int)$lic['grace_days'] . ' days' ?></p>
        <textarea id="offline-token" readonly rows="6" style="<?= $field ?> font-family: monospace; font-size: .75rem;"><?= htmlspecialchars($token) ?></textarea>
        <div style="display:flex; gap:.5rem; margin-top:.6rem;">
            <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('offline-token').value);this.textContent='✔ Copied';">📋 Copy</button>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="license_id" value="<?= (int)$selected_id ?>">
                <input type="hidden" name="fingerprint" value="<?= htmlspecialchars($fingerprint) ?>">
                <input type="hidden" name="mode" value="<?= $permanent ? 'permanent' : 'grace' ?>">
                <input type="hidden" name="download" value="1">
                <button type="submit" class="btn btn-secondary">⬇️ Download .txt</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/staff_footer.php'; ?>
