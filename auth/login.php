<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $user = db_fetch("SELECT * FROM users WHERE email = ?", [$email]);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['email_verified']) {
            login_user($user);
            header('Location: /portal/index.php');
            exit;
        } else {
            flash(t('auth.not_verified'), 'error');
        }
    } else {
        flash(t('auth.invalid_credentials'), 'error');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="glass-card auth-card reveal">
        <div class="auth-header">
            <h2><?= htmlspecialchars(t('auth.login_title')) ?></h2>
            <p><?= htmlspecialchars(t('auth.login_subtitle')) ?></p>
        </div>
        
        <form action="/auth/login.php" method="POST">
<?= csrf_field() ?>
            <div class="form-group">
                <label for="email"><?= htmlspecialchars(t('auth.email')) ?></label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password"><?= htmlspecialchars(t('auth.password')) ?></label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;"><?= htmlspecialchars(t('auth.login_btn')) ?></button>
            
            <div style="text-align: right; margin-top: 0.75rem;">
                <a href="/auth/forgot-password.php" style="color: var(--primary); font-size: 0.9rem;"><?= htmlspecialchars(t('auth.forgot_password')) ?></a>
            </div>
        </form>
        
        <div class="auth-footer">
            <p><?= htmlspecialchars(t('auth.no_account')) ?> <a href="/auth/register.php"><?= htmlspecialchars(t('auth.register_link')) ?></a></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
