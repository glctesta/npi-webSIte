<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/auth.php';

require __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="glass-card auth-card reveal" style="text-align: center;">
        <div class="step-icon" style="margin: 0 auto 1rem;">📧</div>
        <h2><?= htmlspecialchars(t('auth.check_email_title')) ?></h2>
        <p><?= htmlspecialchars(t('auth.check_email_desc')) ?></p>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
