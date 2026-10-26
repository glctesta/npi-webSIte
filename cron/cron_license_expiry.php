<?php
/**
 * cron_license_expiry.php — Notifiche automatiche pre-scadenza licenze.
 *
 * Invia email ai clienti a 30, 15 e 7 giorni dalla scadenza.
 * Da schedulare via cron: una volta al giorno alle 09:00.
 *
 * Esempio crontab:
 *   0 9 * * * php /home/USER/public_html/cron/cron_license_expiry.php >> /tmp/cron_expiry.log 2>&1
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$intervals = [30, 15, 7];
$sent = 0;
$errors = 0;

foreach ($intervals as $days) {
    // Trova licenze che scadono esattamente in $days giorni (±12 ore)
    $licenses = db_fetch_all(
        "SELECT l.id, l.expires_at, l.grace_days,
                u.email, u.first_name, u.last_name, u.company,
                p.name as plan_name, p.slug as plan_slug
         FROM licenses l
         JOIN users u ON u.id = l.user_id
         JOIN plans p ON p.id = l.plan_id
         WHERE l.status = 'active'
           AND l.expires_at BETWEEN
               DATE_ADD(NOW(), INTERVAL ? DAY) - INTERVAL 12 HOUR
               AND DATE_ADD(NOW(), INTERVAL ? DAY) + INTERVAL 12 HOUR",
        [$days, $days]
    );

    foreach ($licenses as $lic) {
        $name = trim(($lic['first_name'] ?? '') . ' ' . ($lic['last_name'] ?? '')) ?: 'Customer';
        $expires = date('d/m/Y', strtotime($lic['expires_at']));
        $grace = (int)($lic['grace_days'] ?? LICENSE_DEFAULT_GRACE_DAYS);
        $plan = $lic['plan_name'];

        // Scegli tono urgenza
        if ($days <= 7) {
            $urgency_color = '#ef4444';
            $urgency_icon = '🔴';
            $urgency_text = "Your license expires in <strong>{$days} days</strong>!";
        } elseif ($days <= 15) {
            $urgency_color = '#f59e0b';
            $urgency_icon = '🟡';
            $urgency_text = "Your license expires in <strong>{$days} days</strong>.";
        } else {
            $urgency_color = '#3b82f6';
            $urgency_icon = '🔵';
            $urgency_text = "Your license expires in <strong>{$days} days</strong>.";
        }

        $subject = "GTMC-NPI — {$urgency_icon} License expiring in {$days} days";

        $body = <<<HTML
<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto;">
  <div style="background: linear-gradient(135deg, #1e293b, #334155); padding: 20px 30px; border-radius: 12px 12px 0 0;">
    <h1 style="margin: 0; color: #fff; font-size: 22px; letter-spacing: 1px;">🔔 GTMC-NPI</h1>
    <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 14px;">License Expiry Notice</p>
  </div>
  <div style="background: #f8fafc; padding: 25px 30px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px;">
    <p>Hi <strong>{$name}</strong>,</p>
    <div style="padding: 15px; background: #fff; border-left: 4px solid {$urgency_color}; border-radius: 4px; margin: 15px 0;">
      <p style="margin: 0; font-size: 16px;">{$urgency_text}</p>
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin: 15px 0;">
      <tr><td style="padding: 6px 0; color: #64748b; width: 130px;">Plan</td><td style="padding: 6px 0; font-weight: 600;">{$plan}</td></tr>
      <tr><td style="padding: 6px 0; color: #64748b;">Expires</td><td style="padding: 6px 0;">{$expires}</td></tr>
      <tr><td style="padding: 6px 0; color: #64748b;">Grace period</td><td style="padding: 6px 0;">{$grace} days after expiry</td></tr>
    </table>
    <p style="color: #475569; font-size: 14px; line-height: 1.6;">
      After the grace period, your license features will be restricted to Basic level.
      Renew now to ensure uninterrupted access to all your features.
    </p>
    <div style="text-align: center; margin: 25px 0;">
      <a href="https://gtmc-npi.eu/portal/licenses.php"
         style="display: inline-block; padding: 12px 30px; background: #6366f1; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600;">
        Renew License →
      </a>
    </div>
    <p style="color: #94a3b8; font-size: 12px; text-align: center; margin-top: 25px;">
      Questions? Contact us at <a href="mailto:info@gtmc-npi.eu" style="color: #6366f1;">info@gtmc-npi.eu</a><br>
      © 2026 GTMC-NPI · <a href="https://gtmc-npi.eu" style="color: #6366f1;">gtmc-npi.eu</a>
    </p>
  </div>
</div>
HTML;

        try {
            $mailer = new SmtpMailer();
            $ok = $mailer->sendMail($lic['email'], $subject, $body);
            if ($ok) {
                $sent++;
                echo date('Y-m-d H:i:s') . " SENT [{$days}d] → {$lic['email']} (License #{$lic['id']})\n";
            } else {
                $errors++;
                echo date('Y-m-d H:i:s') . " FAIL [{$days}d] → {$lic['email']}: " . $mailer->getLog() . "\n";
            }
        } catch (Exception $e) {
            $errors++;
            echo date('Y-m-d H:i:s') . " ERROR [{$days}d] → {$lic['email']}: {$e->getMessage()}\n";
        }
    }
}

echo date('Y-m-d H:i:s') . " Done. Sent: {$sent}, Errors: {$errors}\n";
