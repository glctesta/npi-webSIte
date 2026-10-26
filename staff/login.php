<?php
require_once __DIR__ . '/includes/staff_auth.php';

// No default admin is seeded: create staff users with `php tools/create_staff.php`.

if (is_staff_logged_in()) {
    header('Location: /staff/');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            try {
                $user = db_fetch('SELECT * FROM staff WHERE email = ?', [$email]);
                if ($user && password_verify($password, $user['password_hash'])) {
                    login_staff($user);
                    header('Location: /staff/');
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                // Table might not exist yet
                $error = "Database error. Please contact administrator.";
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - GTMC-NPI</title>
    <link rel="stylesheet" href="/static/css/style.css">
    <link rel="stylesheet" href="/staff/css/staff.css">
</head>
<body>
    <div class="auth-page">
        <div class="glass-card auth-card">
            <div class="auth-header">
                <img src="/static/images/logo.png" alt="GTMC-NPI" style="height:40px; filter: invert(1); margin-bottom: 1rem;">
                <h2>Staff Portal</h2>
                <p>Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="flash-message flash-error" style="position: static; margin-bottom: 1rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.6rem;">
                <a href="/staff/forgot-password.php" style="color: var(--accent-primary); text-decoration: none; font-size: 0.9rem;">Forgot your password?</a>
                <span style="color: var(--text-muted); font-size: 0.85rem;">Don't remember your login email? Contact <a href="mailto:info@gtmc-npi.eu" style="color: var(--accent-primary);">info@gtmc-npi.eu</a></span>
            </div>
        </div>
    </div>
</body>
</html>
