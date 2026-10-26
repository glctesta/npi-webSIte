<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $first_name = mb_substr(trim($_POST['first_name'] ?? ''), 0, 100);
    $last_name = mb_substr(trim($_POST['last_name'] ?? ''), 0, 100);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = mb_substr(trim($_POST['phone'] ?? ''), 0, 50);
    $company = mb_substr(trim($_POST['company'] ?? ''), 0, 200);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $first_name === '' || $last_name === '') {
        flash(t('demo.error_fields'), 'error');
    } elseif (strlen($password) < 8) {
        flash(t('auth.password_min'), 'error');
    } elseif ($password !== $confirm_password) {
        flash(t('auth.passwords_mismatch'), 'error');
    } else {
        $existing = db_fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            flash(t('auth.email_exists'), 'error');
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16));
            $expiry = date('Y-m-d H:i:s', strtotime('+48 hours'));
            
            db_insert("INSERT INTO users (first_name, last_name, company, email, password_hash, phone, email_verified, verification_token, verification_token_expiry) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)",
                [$first_name, $last_name, $company !== '' ? $company : null, $email, $hashed, $phone, $token, $expiry]);
                
            $url = SITE_URL . "/auth/verify.php?token=" . $token;
            send_verify_email($email, trim($first_name . ' ' . $last_name), $url);
            
            header('Location: /auth/check-email.php');
            exit;
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="glass-card auth-card reveal">
        <div class="auth-header">
            <h2><?= htmlspecialchars(t('auth.register_title')) ?></h2>
            <p><?= htmlspecialchars(t('auth.register_subtitle')) ?></p>
        </div>
        
        <form action="/auth/register.php" method="POST">
<?= csrf_field() ?>
            <div class="grid-3" style="gap: 1rem; grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="first_name"><?= htmlspecialchars(t('auth.first_name')) ?></label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="last_name"><?= htmlspecialchars(t('auth.last_name')) ?></label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email"><?= htmlspecialchars(t('auth.email')) ?></label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="grid-3" style="gap: 1rem; grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="password"><?= htmlspecialchars(t('auth.password')) ?></label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password"><?= htmlspecialchars(t('auth.confirm_password')) ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                </div>
            </div>
            
            <div class="form-group">
                <label for="company"><?= htmlspecialchars(t('auth.company')) ?></label>
                <input type="text" id="company" name="company" class="form-control" maxlength="200">
            </div>

            <div class="form-group">
                <label for="phone"><?= htmlspecialchars(t('auth.phone')) ?></label>
                <input type="tel" id="phone" name="phone" class="form-control">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;"><?= htmlspecialchars(t('auth.register_btn')) ?></button>
        </form>
        
        <div class="auth-footer">
            <p><?= htmlspecialchars(t('auth.has_account')) ?> <a href="/auth/login.php"><?= htmlspecialchars(t('auth.login_link')) ?></a></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
