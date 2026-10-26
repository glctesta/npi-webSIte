<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

if (is_staff_logged_in()) {
    header('Location: /staff/');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $email = trim($_POST['email'] ?? '');

    if ($email) {
        $user = db_fetch("SELECT id, full_name, email FROM staff WHERE email = ? AND is_active = 1", [$email]);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            db_query("UPDATE staff SET reset_token = ?, reset_token_expiry = ? WHERE id = ?",
                [$token, $expiry, $user['id']]);

            $reset_url = "https://" . $_SERVER['HTTP_HOST'] . "/staff/reset-password.php?token=" . $token;

            // Send reset email
            $subject = 'GTMC-NPI — Staff Password Reset';

            $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0a0a1a;color:#e0e0e0;padding:40px;">';
            $body .= '<div style="max-width:500px;margin:0 auto;background:#1a1a2e;border-radius:16px;padding:40px;border:1px solid rgba(99,102,241,0.3);">';
            $body .= '<div style="text-align:center;margin-bottom:30px;"><h2 style="color:#6366f1;">GTMC-NPI — Staff Portal</h2></div>';
            $body .= '<p style="font-size:1.1em;">Hello <strong>' . htmlspecialchars($user['full_name']) . '</strong>,</p>';
            $body .= '<p style="line-height:1.6;">We received a request to reset your staff account password. Click the button below to set a new password:</p>';
            $body .= '<div style="text-align:center;margin:35px 0;">';
            $body .= '<a href="' . $reset_url . '" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Reset Password</a>';
            $body .= '</div>';
            $body .= '<p style="color:#888;font-size:0.85em;text-align:center;">This link expires in 1 hour.</p>';
            $body .= '<p style="color:#666;font-size:0.8em;">If you did not request this reset, you can safely ignore this email.</p>';
            $body .= '<hr style="border:none;border-top:1px solid rgba(99,102,241,0.2);margin:25px 0;">';
            $body .= '<p style="color:#666;font-size:0.8em;text-align:center;">&copy; 2026 gtmc-npi.eu | info@gtmc-npi.eu</p>';
            $body .= '</div></body></html>';

            $mailer = new SmtpMailer();
            $result = $mailer->sendMail($user['email'], $subject, $body);

            if (!$result) {
                error_log("Staff password reset email failed. Log:\n" . $mailer->getLog());
            }
        }

        // Always show success (security: don't reveal if email exists)
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Staff Portal - GTMC-NPI</title>
    <link rel="stylesheet" href="/static/css/style.css">
    <link rel="stylesheet" href="/staff/css/staff.css">
</head>
<body>
    <div class="auth-page">
        <div class="glass-card auth-card">
            <div class="auth-header">
                <img src="/static/images/logo.png" alt="GTMC-NPI" style="height:40px; filter: invert(1); margin-bottom: 1rem;">
                <h2>Forgot Password</h2>
                <p>Enter your staff email and we'll send you a reset link</p>
            </div>

            <?php if ($success): ?>
                <div class="flash-message flash-success" style="position: static; margin-bottom: 1rem;">
                    If an account with that email exists, a reset link has been sent. Check your inbox.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required autofocus
                           placeholder="your.email@company.com">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Send Reset Link</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="/staff/login.php" style="color: var(--accent-primary); text-decoration: none; font-size: 0.9rem;">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
