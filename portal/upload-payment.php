<?php
// Portal: Upload payment proof for a proforma invoice
// Accessible with ?ref=PORyyyy-030n (no login required for this specific action)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$ref = trim($_GET['ref'] ?? $_POST['ref'] ?? '');
$proforma = null;
$error = null;
$success = false;

if ($ref) {
    $proforma = db_fetch("SELECT * FROM proforma_invoices WHERE proforma_number = ?", [$ref]);
    if (!$proforma) {
        $error = "Proforma invoice not found.";
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $proforma && !$error) {
    csrf_require();
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a file to upload.";
    } else {
        $file = $_FILES['payment_proof'];
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed)) {
            $error = "Allowed formats: PDF, JPG, PNG, WebP.";
        } elseif ($file['size'] > $max_size) {
            $error = "Maximum file size: 5MB.";
        } else {
            $upload_dir = __DIR__ . '/../uploads/payments/' . $proforma['proforma_number'];
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safe_name = 'payment_' . date('Ymd_His') . '.' . $ext;
            $dest = $upload_dir . '/' . $safe_name;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $rel_path = 'uploads/payments/' . $proforma['proforma_number'] . '/' . $safe_name;
                db_query("UPDATE proforma_invoices SET payment_proof_path = ? WHERE id = ?",
                    [$rel_path, $proforma['id']]);
                $success = true;

                // Notify staff
                $mailer = new SmtpMailer();
                $staff_body = "<p>Payment proof uploaded for proforma <strong>{$proforma['proforma_number']}</strong> by {$proforma['contact_name']} ({$proforma['contact_email']}).</p>";
                $staff_body .= "<p><a href='" . SITE_URL . "/staff/proforma-detail.php?id={$proforma['id']}'>Review in Staff Portal</a></p>";
                $mailer->sendMail(defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'info@gtmc-npi.eu',
                    "Payment Proof Uploaded: {$proforma['proforma_number']}", $staff_body);
            } else {
                $error = "Failed to save file.";
            }
        }
    }
}

$page_title = t('pricing.upload_payment_title');
$meta_description = '';
require __DIR__ . '/../includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 4rem;">
    <div style="max-width: 550px; margin: 0 auto;">
        <div class="glass-card" style="padding: 2rem;">
            <h2 style="margin-bottom: 0.5rem;">📎 <?= htmlspecialchars(t('pricing.upload_payment_title')) ?></h2>

            <?php if (!$ref): ?>
                <p style="color: var(--text-muted);"><?= htmlspecialchars(t('pricing.upload_enter_ref')) ?></p>
                <form method="GET" style="margin-top: 1rem;">
                    <input type="text" name="ref" placeholder="POR2026-0301" required
                           style="width:100%; padding:0.5rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main); margin-bottom:0.8rem;">
                    <button type="submit" class="btn btn-primary" style="width:100%;"><?= htmlspecialchars(t('pricing.upload_find')) ?></button>
                </form>

            <?php elseif ($error): ?>
                <div style="background:rgba(239,68,68,0.1); border-left:4px solid #ef4444; padding:12px 16px; border-radius:4px; margin-bottom:1rem;">
                    <p style="margin:0; color:#ef4444;"><?= htmlspecialchars($error) ?></p>
                </div>
                <a href="/portal/upload-payment.php" class="btn btn-outline"><?= htmlspecialchars(t('pricing.upload_try_again')) ?></a>

            <?php elseif ($success): ?>
                <div style="text-align:center; padding:1.5rem 0;">
                    <div style="font-size:3rem;">✅</div>
                    <h3 style="margin:0.5rem 0;"><?= htmlspecialchars(t('pricing.upload_success')) ?></h3>
                    <p style="color:var(--text-muted);"><?= htmlspecialchars(t('pricing.upload_success_msg')) ?></p>
                    <p style="color:var(--text-muted); font-size:0.9rem;"><strong><?= htmlspecialchars($proforma['proforma_number']) ?></strong></p>
                </div>

            <?php else: ?>
                <div style="background:rgba(99,102,241,0.08); border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
                    <p style="margin:0.2rem 0;"><strong>Reference:</strong> <?= htmlspecialchars($proforma['proforma_number']) ?></p>
                    <p style="margin:0.2rem 0;"><strong>Plan:</strong> <?= htmlspecialchars(ucfirst($proforma['plan_slug'])) ?></p>
                    <p style="margin:0.2rem 0;"><strong>Amount:</strong> €<?= number_format($proforma['amount'], 2, ',', '.') ?></p>
                    <?php if ($proforma['payment_proof_path']): ?>
                        <p style="margin:0.5rem 0 0; color:#10b981;">✅ <?= htmlspecialchars(t('pricing.upload_already')) ?></p>
                    <?php endif; ?>
                </div>

                <form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>
                    <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
                    <div style="margin-bottom:1rem;">
                        <label style="font-size:0.9rem;"><?= htmlspecialchars(t('pricing.upload_file_label')) ?></label>
                        <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" required
                               style="width:100%; padding:0.5rem; margin-top:0.3rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main);">
                        <small style="color:var(--text-muted);">PDF, JPG, PNG — max 5MB</small>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">📤 <?= htmlspecialchars(t('pricing.upload_submit')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
