<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$token = $_GET['token'] ?? '';
$valid = false;
$error = '';

if (!$token) {
    header('Location: /auth/forgot-password.php');
    exit;
}

// Verify token is valid and not expired
$user = db_fetch("SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()", [$token]);
$valid = (bool)$user;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    csrf_require();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) {
        $error = t('auth.password_min');
    } elseif ($password !== $confirm) {
        $error = t('auth.passwords_mismatch');
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        db_query("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?", 
            [$hashed, $user['id']]);
        
        flash(t('auth.password_changed'), 'success');
        header('Location: /auth/login.php');
        exit;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="glass-card auth-card reveal">
        <?php if ($valid): ?>
            <div class="auth-header">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔑</div>
                <h2><?= htmlspecialchars(t('auth.reset_title')) ?></h2>
                <p><?= htmlspecialchars(t('auth.reset_desc')) ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="flash-message flash-error" style="margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form action="/auth/reset-password.php?token=<?= htmlspecialchars($token) ?>" method="POST">
<?= csrf_field() ?>
                <div class="form-group">
                    <label for="password"><?= htmlspecialchars(t('auth.new_password')) ?></label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><?= htmlspecialchars(t('auth.confirm_password')) ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <?= htmlspecialchars(t('auth.reset_btn')) ?>
                </button>
            </form>
        <?php else: ?>
            <div class="auth-header" style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
                <h2><?= htmlspecialchars(t('auth.reset_invalid')) ?></h2>
                <p><?= htmlspecialchars(t('auth.reset_invalid_desc')) ?></p>
                <a href="/auth/forgot-password.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                    <?= htmlspecialchars(t('auth.request_new_link')) ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
