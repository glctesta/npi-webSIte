<?php
// Newsletter helpers — tables are auto-created on first use (no manual migration needed)
require_once __DIR__ . '/db.php';

function newsletter_ensure_table() {
    static $done = false;
    if ($done) return;
    $db = get_db();
    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(200) NOT NULL,
        lang VARCHAR(5) NOT NULL DEFAULT 'en',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        token VARCHAR(64) NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        unsubscribed_at DATETIME DEFAULT NULL,
        UNIQUE KEY uq_newsletter_email (email),
        INDEX ix_newsletter_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject VARCHAR(255) NOT NULL,
        preheader VARCHAR(255) DEFAULT NULL,
        body_html MEDIUMTEXT NOT NULL,
        target_lang VARCHAR(5) NOT NULL DEFAULT 'all',
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        total_recipients INT NOT NULL DEFAULT 0,
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        started_at DATETIME DEFAULT NULL,
        sent_at DATETIME DEFAULT NULL,
        INDEX ix_nc_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_deliveries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        subscriber_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        claim_token VARCHAR(32) DEFAULT NULL,
        claimed_at DATETIME DEFAULT NULL,
        sent_at DATETIME DEFAULT NULL,
        error VARCHAR(255) DEFAULT NULL,
        UNIQUE KEY uq_nd (campaign_id, subscriber_id),
        INDEX ix_nd_status (campaign_id, status),
        INDEX ix_nd_claim (claim_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function newsletter_languages() {
    return ['all' => 'All languages', 'en' => 'English', 'it' => 'Italiano', 'de' => 'Deutsch',
            'ro' => 'Română', 'fr' => 'Français', 'es' => 'Español'];
}

/** Delivery counters for a campaign: pending / processing / sent / failed / skipped / total */
function newsletter_campaign_progress($campaign_id) {
    $rows = db_fetch_all("SELECT status, COUNT(*) c FROM newsletter_deliveries WHERE campaign_id = ? GROUP BY status", [$campaign_id]);
    $p = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
    foreach ($rows as $r) $p[$r['status']] = (int)$r['c'];
    $p['total'] = array_sum($p);
    return $p;
}

/** Wraps editor content in the branded, email-client-safe GTMC template */
function newsletter_render_email($subject, $preheader, $body_html, $unsubscribe_url) {
    // Email clients ignore external CSS: make images responsive inline
    $body_html = preg_replace('/<img(?![^>]*style=)/i', '<img style="max-width:100%;height:auto;border:0;"', $body_html);
    $site = defined('SITE_URL') ? SITE_URL : 'https://gtmc-npi.eu';
    $year = date('Y');
    $s = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $ph = htmlspecialchars((string)$preheader, ENT_QUOTES, 'UTF-8');
    $u = htmlspecialchars($unsubscribe_url, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$s}</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$ph}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">
    <tr><td style="padding:22px 32px;border-bottom:3px solid #4f46e5;">
      <a href="{$site}"><img src="{$site}/static/images/logo.png" alt="GTMC-NPI" height="36" style="height:36px;border:0;"></a>
    </td></tr>
    <tr><td style="padding:30px 32px;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.65;color:#1e293b;">
      {$body_html}
    </td></tr>
    <tr><td style="padding:20px 32px;background:#f8fafc;font-family:Arial,sans-serif;font-size:11px;line-height:1.6;color:#94a3b8;text-align:center;">
      &copy; {$year} GTMC CONSULTING SRL &middot; Bdul. G-Ral Vasile Milea 3, Et. 3, Ap. 8A, Arad 310131, Romania<br>
      You receive this email because you subscribed to the GTMC-NPI newsletter on gtmc-npi.eu.<br>
      <a href="{$u}" style="color:#4f46e5;">Unsubscribe</a>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;
}
