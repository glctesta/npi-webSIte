<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = trim($_POST['email'] ?? '');
    
    if ($email) {
        $user = db_fetch("SELECT id, first_name, last_name, email FROM users WHERE email = ?", [$email]);
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            db_query("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?", 
                [$token, $expiry, $user['id']]);
            
            $reset_url = SITE_URL . "/auth/reset-password.php?token=" . $token;
            send_reset_email($user['email'], trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), $reset_url);
        }
        
        // Always show success (security: don't reveal if email exists)
        flash(t('auth.reset_sent'), 'success');
        header('Location: /auth/forgot-password.php');
        exit;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="glass-card auth-card reveal">
        <div class="auth-header">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
            <h2><?= htmlspecialchars(t('auth.forgot_title')) ?></h2>
            <p><?= htmlspecialchars(t('auth.forgot_desc')) ?></p>
        </div>
        
        <form action="/auth/forgot-password.php" method="POST">
<?= csrf_field() ?>
            <div class="form-group">
                <label for="email"><?= htmlspecialchars(t('auth.email')) ?></label>
                <input type="email" id="email" name="email" class="form-control" required 
                       placeholder="<?= htmlspecialchars(t('auth.email_placeholder')) ?>">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                <?= htmlspecialchars(t('auth.send_reset')) ?>
            </button>
        </form>
        
        <div class="auth-footer" style="margin-top: 2rem;">
            <p><a href="/auth/login.php" style="color: var(--primary);">← <?= htmlspecialchars(t('auth.back_to_login')) ?></a></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
