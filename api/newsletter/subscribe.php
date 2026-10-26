<?php
// POST /api/newsletter/subscribe.php  { email, lang, website (honeypot) }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/newsletter.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'code' => 'error']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = strtolower(trim($input['email'] ?? ''));
$lang  = in_array($input['lang'] ?? '', LANGUAGES, true) ? $input['lang'] : DEFAULT_LANG;

// Honeypot: bots fill hidden fields — pretend success
if (!empty($input['website'])) {
    echo json_encode(['ok' => true, 'code' => 'ok']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
    echo json_encode(['ok' => false, 'code' => 'invalid']);
    exit;
}

// Basic rate limit: max 5 attempts per session per 10 minutes
$now = time();
$_SESSION['nl_attempts'] = array_filter($_SESSION['nl_attempts'] ?? [], fn($t) => $t > $now - 600);
if (count($_SESSION['nl_attempts']) >= 5) {
    echo json_encode(['ok' => false, 'code' => 'error']);
    exit;
}
$_SESSION['nl_attempts'][] = $now;

try {
    newsletter_ensure_table();
    $existing = db_fetch("SELECT id, status, token FROM newsletter_subscribers WHERE email = ?", [$email]);

    if ($existing && $existing['status'] === 'active') {
        echo json_encode(['ok' => true, 'code' => 'already']);
        exit;
    }

    if ($existing) {
        // Re-subscribe a previously unsubscribed address
        $token = $existing['token'];
        db_query("UPDATE newsletter_subscribers SET status = 'active', lang = ?, unsubscribed_at = NULL WHERE id = ?",
            [$lang, $existing['id']]);
    } else {
        $token = bin2hex(random_bytes(32));
        db_insert("INSERT INTO newsletter_subscribers (email, lang, status, token, ip_address) VALUES (?, ?, 'active', ?, ?)",
            [$email, $lang, $token, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
} catch (Throwable $e) {
    error_log('Newsletter subscribe failed: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'code' => 'error']);
    exit;
}

// Welcome email with unsubscribe link (failure does not block the subscription)
$unsub_url = SITE_URL . '/api/newsletter/unsubscribe.php?token=' . $token;
$body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:30px;border:1px solid #e2e8f0;border-radius:8px;">'
      . '<h2 style="color:#4f46e5;margin-top:0;">GTMC-NPI Newsletter</h2>'
      . '<p>Thank you for subscribing! You will receive product updates, new features and NPI process tips (readiness gates, change management, IATF 16949 readiness).</p>'
      . '<p style="font-size:12px;color:#94a3b8;margin-top:30px;">If you did not request this, or want to stop receiving emails: '
      . '<a href="' . htmlspecialchars($unsub_url) . '" style="color:#4f46e5;">unsubscribe</a>.</p>'
      . '</div>';
try {
    $mailer = new SmtpMailer();
    if (!$mailer->sendMail($email, 'GTMC-NPI - Newsletter subscription confirmed', $body)) {
        error_log("Newsletter welcome email failed for {$email}:\n" . $mailer->getLog());
    }
} catch (Throwable $e) {
    error_log('Newsletter welcome email error: ' . $e->getMessage());
}

echo json_encode(['ok' => true, 'code' => 'ok']);
