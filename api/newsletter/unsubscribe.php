<?php
// GET /api/newsletter/unsubscribe.php?token=...
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/newsletter.php';

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
$ok = false;
if (strlen($token) === 64) {
    try {
        newsletter_ensure_table();
        $stmt = db_query("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE token = ?", [$token]);
        $ok = db_fetch("SELECT id FROM newsletter_subscribers WHERE token = ?", [$token]) !== false;
    } catch (Throwable $e) {
        error_log('Newsletter unsubscribe failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="<?= get_lang() ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex">
<title>Newsletter — GTMC-NPI</title></head>
<body style="font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;">
<div style="text-align:center;padding:2rem;">
    <h2 style="color:<?= $ok ? '#34d399' : '#f87171' ?>;"><?= htmlspecialchars($ok ? t('footer.unsubscribed') : t('footer.subscribe_error')) ?></h2>
    <p><a href="/" style="color:#818cf8;">gtmc-npi.eu</a></p>
</div>
</body>
</html>
