<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$page_title = "Proforma Detail";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /staff/proformas.php'); exit; }

$proforma = db_fetch("SELECT * FROM proforma_invoices WHERE id = ?", [$id]);
if (!$proforma) { header('Location: /staff/proformas.php'); exit; }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_paid') {
        db_query("UPDATE proforma_invoices SET status = 'paid', paid_at = NOW() WHERE id = ?", [$id]);
        flash("Proforma {$proforma['proforma_number']} marked as paid.");
        header("Location: /staff/proforma-detail.php?id=$id");
        exit;
    }

    if ($action === 'cancel') {
        db_query("UPDATE proforma_invoices SET status = 'cancelled' WHERE id = ?", [$id]);
        flash("Proforma {$proforma['proforma_number']} cancelled.");
        header("Location: /staff/proforma-detail.php?id=$id");
        exit;
    }

    if ($action === 'resend') {
        // Re-trigger the proforma email by redirecting to the API
        // For simplicity, just send a notification
        $mailer = new SmtpMailer();
        $subject = "GTMC-NPI — Proforma Invoice {$proforma['proforma_number']} (Reminder)";
        $upload_url = SITE_URL . '/portal/upload-payment.php?ref=' . urlencode($proforma['proforma_number']);
        $body = "<p>Dear {$proforma['contact_name']},</p>";
        $body .= "<p>This is a reminder about your proforma invoice <strong>{$proforma['proforma_number']}</strong>.</p>";
        $body .= "<p>Amount: <strong>€" . number_format($proforma['amount'], 2, ',', '.') . " EUR</strong></p>";
        $body .= "<p>IBAN: <strong>RO88INGB0000999903390932</strong> — ING Bank, Timișoara</p>";
        $body .= "<p>Reference: <strong>{$proforma['proforma_number']}</strong></p>";
        $body .= "<p>Once paid, please upload your payment proof at: <a href='{$upload_url}'>{$upload_url}</a></p>";
        $body .= "<p>Best regards,<br>GTMC CONSULTING SRL</p>";

        $sent = $mailer->sendMail($proforma['contact_email'], $subject, $body);
        flash($sent ? "Reminder sent to {$proforma['contact_email']}." : "Failed to send reminder.", $sent ? 'success' : 'error');
        header("Location: /staff/proforma-detail.php?id=$id");
        exit;
    }

    if ($action === 'update_notes') {
        $notes = trim($_POST['notes'] ?? '');
        db_query("UPDATE proforma_invoices SET notes = ? WHERE id = ?", [$notes, $id]);
        flash("Notes updated.");
        header("Location: /staff/proforma-detail.php?id=$id");
        exit;
    }
}

// Refresh after actions
$proforma = db_fetch("SELECT * FROM proforma_invoices WHERE id = ?", [$id]);

include __DIR__ . '/includes/staff_header.php';
?>

<div style="margin-bottom: 1rem;">
    <a href="/staff/proformas.php" class="btn btn-outline btn-sm">← Back to Proformas</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Left: Proforma Info -->
    <div class="glass-card" style="padding: 1.5rem;">
        <h3 style="margin-bottom: 1rem;">📄 <?= htmlspecialchars($proforma['proforma_number']) ?></h3>

        <?php
        $status_colors = ['sent' => 'warning', 'paid' => 'success', 'cancelled' => 'secondary'];
        $sc = $status_colors[$proforma['status']] ?? 'secondary';
        ?>
        <div style="margin-bottom: 1.5rem;">
            <?= badge_html(ucfirst($proforma['status']), $sc) ?>
            <?= badge_html(ucfirst($proforma['plan_slug']), 'info') ?>
        </div>

        <table style="width:100%; border-collapse:collapse; font-size:0.95rem;">
            <tr><td style="padding:6px 0; color:var(--text-muted); width:40%;">Customer</td><td style="padding:6px 0; font-weight:600;"><?= htmlspecialchars($proforma['contact_name']) ?></td></tr>
            <tr><td style="padding:6px 0; color:var(--text-muted);">Email</td><td style="padding:6px 0;"><?= htmlspecialchars($proforma['contact_email']) ?></td></tr>
            <?php if ($proforma['company_name']): ?>
            <tr><td style="padding:6px 0; color:var(--text-muted);">Company</td><td style="padding:6px 0;"><?= htmlspecialchars($proforma['company_name']) ?></td></tr>
            <?php endif; ?>
            <?php if ($proforma['vat_id']): ?>
            <tr><td style="padding:6px 0; color:var(--text-muted);">VAT ID</td><td style="padding:6px 0;"><?= htmlspecialchars($proforma['vat_id']) ?></td></tr>
            <?php endif; ?>
            <?php if ($proforma['billing_address']): ?>
            <tr><td style="padding:6px 0; color:var(--text-muted);">Address</td><td style="padding:6px 0;"><?= nl2br(htmlspecialchars($proforma['billing_address'])) ?></td></tr>
            <?php endif; ?>
            <tr><td style="padding:6px 0; color:var(--text-muted);">Amount</td><td style="padding:6px 0; font-weight:700; font-size:1.1rem; color:var(--accent);">€<?= number_format($proforma['amount'], 2, ',', '.') ?> EUR</td></tr>
            <tr><td style="padding:6px 0; color:var(--text-muted);">Issued</td><td style="padding:6px 0;"><?= date('Y-m-d H:i', strtotime($proforma['issued_at'])) ?></td></tr>
            <?php if ($proforma['paid_at']): ?>
            <tr><td style="padding:6px 0; color:var(--text-muted);">Paid at</td><td style="padding:6px 0; color:#10b981; font-weight:600;"><?= date('Y-m-d H:i', strtotime($proforma['paid_at'])) ?></td></tr>
            <?php endif; ?>
            <tr><td style="padding:6px 0; color:var(--text-muted);">Language</td><td style="padding:6px 0;"><?= strtoupper($proforma['lang']) ?></td></tr>
        </table>

        <!-- Actions -->
        <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap;">
            <?php if ($proforma['status'] === 'sent'): ?>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="mark_paid">
                <button type="submit" class="btn btn-primary" style="width:100%;" onclick="return confirm('Mark as paid?');">✅ Mark as Paid</button>
            </form>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn btn-outline" style="width:100%;">📧 Resend Email</button>
            </form>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-sm btn-danger" style="width:100%; background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#ef4444;" onclick="return confirm('Cancel this proforma?');">❌ Cancel</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Payment Proof & Notes -->
    <div>
        <!-- Payment Proof -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 1rem;">📎 Payment Proof</h3>
            <?php if ($proforma['payment_proof_path']): ?>
                <?php
                // Served via staff/payment-proof.php: uploads/payments/ is denied by .htaccess
                $proof_url = '/staff/payment-proof.php?id=' . (int)$proforma['id'];
                $proof_ext = strtolower(pathinfo($proforma['payment_proof_path'], PATHINFO_EXTENSION));
                $is_image = in_array($proof_ext, ['jpg', 'jpeg', 'png', 'webp']);
                ?>
                <?php if ($is_image): ?>
                    <img src="<?= htmlspecialchars($proof_url) ?>" alt="Payment Proof"
                         style="max-width: 100%; border-radius: 6px; border: 1px solid var(--border-glass);">
                <?php else: ?>
                    <a href="<?= htmlspecialchars($proof_url) ?>" target="_blank" class="btn btn-outline" style="width:100%;">
                        📥 Download PDF
                    </a>
                <?php endif; ?>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">
                    File: <?= htmlspecialchars(basename($proforma['payment_proof_path'])) ?>
                </p>
            <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">
                    No payment proof uploaded yet.
                </p>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <div class="glass-card" style="padding: 1.5rem;">
            <h3 style="margin-bottom: 1rem;">📝 Notes</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update_notes">
                <textarea name="notes" rows="4" style="width:100%; padding:0.5rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main); margin-bottom:0.5rem;"><?= htmlspecialchars($proforma['notes'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-secondary btn-sm">Save Notes</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/staff_footer.php'; ?>
