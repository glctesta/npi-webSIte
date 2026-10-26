<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/../includes/config.php';

if (is_staff_logged_in()) {
    header('Location: /staff/');
    exit;
}

$token = $_GET['token'] ?? '';
$valid = false;
$error = '';
$success = false;

if (!$token) {
    header('Location: /staff/forgot-password.php');
    exit;
}

// Verify token is valid and not expired
$user = db_fetch("SELECT id, email, full_name FROM staff WHERE reset_token = ? AND reset_token_expiry > NOW() AND is_active = 1", [$token]);
$valid = (bool)$user;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    csrf_require();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        db_query("UPDATE staff SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?",
            [$hashed, $user['id']]);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Staff Portal - GTMC-NPI</title>
    <link rel="stylesheet" href="/static/css/style.css">
    <link rel="stylesheet" href="/staff/css/staff.css">
</head>
<body>
    <div class="auth-page">
        <div class="glass-card auth-card">
            <?php if ($success): ?>
                <div class="auth-header">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                    <h2>Password Changed</h2>
                    <p>Your password has been reset successfully. You can now sign in.</p>
                </div>
                <a href="/staff/login.php" class="btn btn-primary" style="width: 100%; display: block; text-align: center; margin-top: 1.5rem;">Sign In</a>

            <?php elseif ($valid): ?>
                <div class="auth-header">
                    <img src="/static/images/logo.png" alt="GTMC-NPI" style="height:40px; filter: invert(1); margin-bottom: 1rem;">
                    <h2>Reset Password</h2>
                    <p>Enter your new password for <strong><?= htmlspecialchars($user['email']) ?></strong></p>
                </div>

                <?php if ($error): ?>
                    <div class="flash-message flash-error" style="position: static; margin-bottom: 1rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/staff/reset-password.php?token=<?= htmlspecialchars($token) ?>">
<?= csrf_field() ?>
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="8">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Reset Password</button>
                </form>

            <?php else: ?>
                <div class="auth-header" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
                    <h2>Invalid or Expired Link</h2>
                    <p>This reset link is no longer valid. It may have expired or already been used.</p>
                    <a href="/staff/forgot-password.php" class="btn btn-primary" style="margin-top: 1.5rem;">Request New Link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
