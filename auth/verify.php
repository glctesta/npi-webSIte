<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$token = $_GET['token'] ?? '';
$success = false;

if ($token) {
    /* Lookup idempotente: i link delle email vengono PRE-CLICCATI dagli
       scanner anti-phishing (Outlook Safe Links, Google) prima che arrivi
       il clic umano. Il primo hit verificava l'email e azzerava il token:
       il clic reale dell'utente trovava il token azzerato e vedeva
       "Verification Failed". Ora il token NON viene azzerato (scade da
       solo per data) e un utente gia' verificato vede di nuovo successo. */
    $user = db_fetch(
        "SELECT id, email_verified, (verification_token_expiry > NOW()) AS still_valid
         FROM users WHERE verification_token = ?", [$token]);
    if ($user && ($user['email_verified'] || $user['still_valid'])) {
        if (!$user['email_verified']) {
            db_query("UPDATE users SET email_verified = 1 WHERE id = ?", [$user['id']]);
        }
        $success = true;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="glass-card auth-card reveal" style="text-align: center;">
        <?php if ($success): ?>
            <div class="step-icon" style="margin: 0 auto 1rem;">✅</div>
            <h2><?= htmlspecialchars(t('auth.verify_success')) ?></h2>
            <p><?= htmlspecialchars(t('auth.verify_success_desc')) ?></p>
            <a href="/auth/login.php" class="btn btn-primary" style="margin-top: 2rem;"><?= htmlspecialchars(t('auth.login_btn')) ?></a>
        <?php else: ?>
            <div class="step-icon" style="margin: 0 auto 1rem;">❌</div>
            <h2><?= htmlspecialchars(t('auth.verify_error')) ?></h2>
            <p><?= htmlspecialchars(t('auth.verify_error_desc')) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
