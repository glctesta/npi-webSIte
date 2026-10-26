<?php
// staff/newsletter-api.php — JSON actions for newsletter campaigns
// POST JSON: { action, csrf, ... }
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

function out($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if (!is_staff_logged_in()) out(['ok' => false, 'error' => 'Not logged in'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'Method not allowed'], 405);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (empty($in['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$in['csrf'])) {
    out(['ok' => false, 'error' => 'Session expired, reload the page.'], 403);
}

newsletter_ensure_table();
$action = $in['action'] ?? '';
$id = (int)($in['id'] ?? 0);
$can_send = in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'], true);
$langs = newsletter_languages();

function load_campaign($id) {
    $c = $id ? db_fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$id]) : null;
    if (!$c) out(['ok' => false, 'error' => 'Campaign not found'], 404);
    return $c;
}

function send_one($campaign, $email, $token, $subject_prefix = '') {
    $unsub = SITE_URL . '/api/newsletter/unsubscribe.php?token=' . $token;
    $html = newsletter_render_email($campaign['subject'], $campaign['preheader'], $campaign['body_html'], $unsub);
    $headers = [
        'List-Unsubscribe' => '<' . $unsub . '>',
        'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
    ];
    $m = new SmtpMailer();
    $ok = $m->sendMail($email, $subject_prefix . $campaign['subject'], $html, $headers);
    if (!$ok) error_log("Newsletter send failed to {$email}:\n" . $m->getLog());
    return $ok;
}

switch ($action) {

    // ── Save draft (create or update) ───────────────────────────────
    case 'save':
        $subject = trim($in['subject'] ?? '');
        $pre = trim($in['preheader'] ?? '');
        $body = (string)($in['body_html'] ?? '');
        $lang = array_key_exists($in['target_lang'] ?? '', $langs) ? $in['target_lang'] : 'all';
        if ($subject === '' || mb_strlen($subject) > 255) out(['ok' => false, 'error' => 'Subject is required (max 255 chars).']);
        if (trim(strip_tags($body, '<img>')) === '') out(['ok' => false, 'error' => 'Content is empty.']);
        // Remove scripts / inline event handlers — email clients block them anyway
        $body = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body);
        $body = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $body);

        if ($id) {
            $c = load_campaign($id);
            if ($c['status'] !== 'draft') out(['ok' => false, 'error' => 'Only drafts can be edited.']);
            db_query("UPDATE newsletter_campaigns SET subject = ?, preheader = ?, body_html = ?, target_lang = ? WHERE id = ?",
                [$subject, $pre ?: null, $body, $lang, $id]);
        } else {
            $id = (int)db_insert("INSERT INTO newsletter_campaigns (subject, preheader, body_html, target_lang, created_by) VALUES (?, ?, ?, ?, ?)",
                [$subject, $pre ?: null, $body, $lang, $_SESSION['staff_id']]);
        }
        out(['ok' => true, 'id' => $id]);

    // ── Send a test copy ────────────────────────────────────────────
    case 'test':
        $c = load_campaign($id);
        $to = trim($in['email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) out(['ok' => false, 'error' => 'Invalid test email address.']);
        $ok = send_one($c, $to, str_repeat('0', 64), '[TEST] ');
        out(['ok' => $ok, 'error' => $ok ? null : 'SMTP error, see server error_log.']);

    // ── Start sending: freeze recipients list ───────────────────────
    case 'start':
        if (!$can_send) out(['ok' => false, 'error' => 'Only admin/manager can send newsletters.'], 403);
        $c = load_campaign($id);
        if ($c['status'] !== 'draft') out(['ok' => false, 'error' => 'Campaign already started.']);
        if ($c['target_lang'] === 'all') {
            db_query("INSERT IGNORE INTO newsletter_deliveries (campaign_id, subscriber_id)
                      SELECT ?, id FROM newsletter_subscribers WHERE status = 'active'", [$id]);
        } else {
            db_query("INSERT IGNORE INTO newsletter_deliveries (campaign_id, subscriber_id)
                      SELECT ?, id FROM newsletter_subscribers WHERE status = 'active' AND lang = ?", [$id, $c['target_lang']]);
        }
        $p = newsletter_campaign_progress($id);
        if ($p['total'] === 0) out(['ok' => false, 'error' => 'No active subscribers for this language.']);
        db_query("UPDATE newsletter_campaigns SET status = 'sending', total_recipients = ?, started_at = NOW() WHERE id = ?", [$p['total'], $id]);
        out(['ok' => true, 'progress' => $p]);

    // ── Send next batch (called repeatedly by the browser) ──────────
    case 'batch':
        if (!$can_send) out(['ok' => false, 'error' => 'Only admin/manager can send newsletters.'], 403);
        $c = load_campaign($id);
        if ($c['status'] !== 'sending') out(['ok' => true, 'done' => true, 'progress' => newsletter_campaign_progress($id)]);
        @set_time_limit(120);

        // Release rows stuck in "processing" (e.g. closed tab mid-batch) after 10 minutes
        db_query("UPDATE newsletter_deliveries SET status = 'pending', claim_token = NULL
                  WHERE campaign_id = ? AND status = 'processing' AND claimed_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)", [$id]);

        // Atomically claim a batch so two open tabs never send the same email twice
        $claim = bin2hex(random_bytes(16));
        $size = max(1, min(25, (int)($in['size'] ?? 10)));
        db_query("UPDATE newsletter_deliveries SET status = 'processing', claim_token = ?, claimed_at = NOW()
                  WHERE campaign_id = ? AND status = 'pending' ORDER BY id LIMIT $size", [$claim, $id]);
        $rows = db_fetch_all("SELECT d.id, s.email, s.token, s.status AS sub_status
                              FROM newsletter_deliveries d JOIN newsletter_subscribers s ON s.id = d.subscriber_id
                              WHERE d.claim_token = ?", [$claim]);

        $sent = $failed = 0;
        foreach ($rows as $r) {
            if ($r['sub_status'] !== 'active') { // unsubscribed while sending
                db_query("UPDATE newsletter_deliveries SET status = 'skipped', claim_token = NULL WHERE id = ?", [$r['id']]);
                continue;
            }
            if (send_one($c, $r['email'], $r['token'])) {
                db_query("UPDATE newsletter_deliveries SET status = 'sent', sent_at = NOW(), claim_token = NULL, error = NULL WHERE id = ?", [$r['id']]);
                $sent++;
            } else {
                db_query("UPDATE newsletter_deliveries SET status = 'failed', claim_token = NULL, error = 'SMTP error' WHERE id = ?", [$r['id']]);
                $failed++;
            }
            usleep(300000); // 0.3s between emails: gentler on hosting SMTP limits
        }

        $p = newsletter_campaign_progress($id);
        $done = ($p['pending'] + $p['processing']) === 0;
        if ($done) db_query("UPDATE newsletter_campaigns SET status = 'sent', sent_at = NOW() WHERE id = ?", [$id]);
        out(['ok' => true, 'done' => $done, 'batch_sent' => $sent, 'batch_failed' => $failed, 'progress' => $p]);

    // ── Re-queue failed deliveries ──────────────────────────────────
    case 'retry_failed':
        if (!$can_send) out(['ok' => false, 'error' => 'Only admin/manager can send newsletters.'], 403);
        $c = load_campaign($id);
        db_query("UPDATE newsletter_deliveries SET status = 'pending', error = NULL WHERE campaign_id = ? AND status = 'failed'", [$id]);
        db_query("UPDATE newsletter_campaigns SET status = 'sending', sent_at = NULL WHERE id = ?", [$id]);
        out(['ok' => true, 'progress' => newsletter_campaign_progress($id)]);

    // ── Duplicate any campaign into a new draft ─────────────────────
    case 'duplicate':
        $c = load_campaign($id);
        $new = (int)db_insert("INSERT INTO newsletter_campaigns (subject, preheader, body_html, target_lang, created_by) VALUES (?, ?, ?, ?, ?)",
            ['Copy of ' . mb_substr($c['subject'], 0, 240), $c['preheader'], $c['body_html'], $c['target_lang'], $_SESSION['staff_id']]);
        out(['ok' => true, 'id' => $new]);

    // ── Delete (not while sending) ──────────────────────────────────
    case 'delete':
        $c = load_campaign($id);
        if ($c['status'] === 'sending') out(['ok' => false, 'error' => 'Cannot delete a campaign while it is sending.']);
        db_query("DELETE FROM newsletter_deliveries WHERE campaign_id = ?", [$id]);
        db_query("DELETE FROM newsletter_campaigns WHERE id = ?", [$id]);
        out(['ok' => true]);

    default:
        out(['ok' => false, 'error' => 'Unknown action'], 400);
}
