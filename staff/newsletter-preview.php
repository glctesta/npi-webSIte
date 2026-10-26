<?php
// staff/newsletter-preview.php — renders the email exactly as subscribers will see it
// POST (subject, preheader, body_html, csrf) for unsaved content, or GET ?id= for saved campaigns
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/../includes/newsletter.php';

newsletter_ensure_table();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) { http_response_code(403); exit('Session expired'); }
    $subject = $_POST['subject'] ?? '';
    $pre = $_POST['preheader'] ?? '';
    $body = $_POST['body_html'] ?? '';
} else {
    $c = db_fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
    if (!$c) { http_response_code(404); exit('Not found'); }
    [$subject, $pre, $body] = [$c['subject'], $c['preheader'], $c['body_html']];
}
// Preview runs on our domain: strip scripts and inline handlers
$body = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body);
$body = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $body);

header('Content-Security-Policy: script-src \'none\'');
echo newsletter_render_email($subject, $pre, $body, '#');
