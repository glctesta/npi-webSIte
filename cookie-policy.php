<?php
// ============================================================================
// GTMC-NPI Cookie Policy Page
// ============================================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(get_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('cookies.title')) ?> — GTMC-NPI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.7;
        }
        .header {
            background: rgba(30, 41, 59, 0.9);
            border-bottom: 1px solid rgba(99, 102, 241, 0.2);
            padding: 1.5rem 2rem;
            text-align: center;
        }
        .header a { color: #818cf8; text-decoration: none; font-weight: 700; font-size: 1.25rem; }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }
        h1 { font-size: 2rem; margin-bottom: 0.5rem; color: #f1f5f9; }
        h2 { font-size: 1.25rem; margin: 2rem 0 0.75rem; color: #818cf8; }
        h3 { font-size: 1rem; margin: 1.5rem 0 0.5rem; color: #a5b4fc; }
        p { margin-bottom: 1rem; color: #94a3b8; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(99, 102, 241, 0.1);
            font-size: 0.875rem;
        }
        th { color: #818cf8; font-weight: 600; background: rgba(99, 102, 241, 0.08); }
        td { color: #94a3b8; }
        .badge-essential { background: #065f46; color: #6ee7b7; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
        .badge-analytics { background: #92400e; color: #fcd34d; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
        .badge-functional { background: #1e3a5f; color: #93c5fd; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
        .btn-manage {
            display: inline-block;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
        .btn-manage:hover { opacity: 0.9; }
        .footer {
            text-align: center;
            padding: 2rem;
            color: #475569;
            font-size: 0.8rem;
            border-top: 1px solid rgba(99, 102, 241, 0.1);
            margin-top: 3rem;
        }
        .footer a { color: #6366f1; text-decoration: none; }
    </style>
</head>
<body>

<div class="header">
    <a href="/portal/downloads">GTMC-NPI</a>
</div>

<div class="container">
    <h1>🍪 <?= htmlspecialchars(t('cookies.title')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('cookies.last_updated')) ?> — GTMC S.r.l.</p>

    <h2><?= htmlspecialchars(t('cookies.what_are')) ?></h2>
    <p><?= htmlspecialchars(t('cookies.what_are_text')) ?></p>

    <h2><?= htmlspecialchars(t('cookies.how_we_use')) ?></h2>
    <p><?= htmlspecialchars(t('cookies.how_we_use_text')) ?></p>

    <h2><?= htmlspecialchars(t('cookies.types')) ?></h2>

    <table>
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('cookies.th_name')) ?></th>
                <th><?= htmlspecialchars(t('cookies.th_type')) ?></th>
                <th><?= htmlspecialchars(t('cookies.th_purpose')) ?></th>
                <th><?= htmlspecialchars(t('cookies.th_duration')) ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>gtmc_cookie_consent</code></td>
                <td><span class="badge-essential"><?= htmlspecialchars(t('cookies.essential')) ?></span></td>
                <td><?= htmlspecialchars(t('cookies.desc_gtmc_cookie_consent')) ?></td>
                <td><?= htmlspecialchars(t('cookies.dur_365')) ?></td>
            </tr>
            <tr>
                <td><code>PHPSESSID</code></td>
                <td><span class="badge-essential"><?= htmlspecialchars(t('cookies.essential')) ?></span></td>
                <td><?= htmlspecialchars(t('cookies.desc_phpsessid')) ?></td>
                <td><?= htmlspecialchars(t('cookies.dur_session')) ?></td>
            </tr>
            <tr>
                <td><code>gtmc_lang</code></td>
                <td><span class="badge-functional"><?= htmlspecialchars(t('cookies.functional')) ?></span></td>
                <td><?= htmlspecialchars(t('cookies.desc_gtmc_lang')) ?></td>
                <td><?= htmlspecialchars(t('cookies.dur_180')) ?></td>
            </tr>
            <tr>
                <td><code>gtmc_customer_id</code></td>
                <td><span class="badge-functional"><?= htmlspecialchars(t('cookies.functional')) ?></span></td>
                <td><?= htmlspecialchars(t('cookies.desc_gtmc_customer_id')) ?></td>
                <td><?= htmlspecialchars(t('cookies.dur_90')) ?></td>
            </tr>
            <tr>
                <td><code>gtmc_license_tier</code></td>
                <td><span class="badge-functional"><?= htmlspecialchars(t('cookies.functional')) ?></span></td>
                <td><?= htmlspecialchars(t('cookies.desc_gtmc_license_tier')) ?></td>
                <td><?= htmlspecialchars(t('cookies.dur_30')) ?></td>
            </tr>
            <tr>
                <td><code>gtmc_last_version_check</code></td>
                <td><span class="badge-functional"><?= htmlspecialchars(t('cookies.functional')) ?></span></td>
                <td><?= htmlspecialchars(t('cookies.desc_gtmc_last_version_check')) ?></td>
                <td><?= htmlspecialchars(t('cookies.dur_24h')) ?></td>
            </tr>
            <tr>
                <td><code>gtmc_analytics</code></td>
                <td><span class="badge-analytics"><?= htmlspecialchars(t('cookies.analytics')) ?></span></td>
                <td><?= htmlspecialchars(t('cookies.desc_gtmc_analytics')) ?></td>
                <td><?= htmlspecialchars(t('cookies.dur_365')) ?></td>
            </tr>
        </tbody>
    </table>

    <h3><?= htmlspecialchars(t('cookies.essential_title')) ?></h3>
    <p><?= htmlspecialchars(t('cookies.essential_text')) ?></p>

    <h3><?= htmlspecialchars(t('cookies.functional_title')) ?></h3>
    <p><?= htmlspecialchars(t('cookies.functional_text')) ?></p>

    <h3><?= htmlspecialchars(t('cookies.analytics_title')) ?></h3>
    <p><?= htmlspecialchars(t('cookies.analytics_text')) ?></p>

    <h2><?= htmlspecialchars(t('cookies.managing')) ?></h2>
    <p><?= htmlspecialchars(t('cookies.managing_text')) ?></p>
    <button class="btn-manage" onclick="resetCookieConsent()">🔧 <?= htmlspecialchars(t('cookies.manage_btn')) ?></button>

    <p style="margin-top: 1.5rem;"><?= htmlspecialchars(t('cookies.managing_browser')) ?></p>

    <h2><?= htmlspecialchars(t('cookies.third_party')) ?></h2>
    <p><?= htmlspecialchars(t('cookies.third_party_text')) ?></p>

    <h2><?= htmlspecialchars(t('cookies.data_protection')) ?></h2>
    <p><?= htmlspecialchars(t('cookies.data_protection_text')) ?> <a href="/terms-of-use" style="color: #818cf8;"><?= htmlspecialchars(t('cookies.terms_of_use')) ?></a>.</p>

    <h2><?= htmlspecialchars(t('cookies.contact')) ?></h2>
    <p><?= htmlspecialchars(t('cookies.contact_text')) ?></p>
    <p>
        <strong>GTMC S.r.l.</strong><br>
        Email: <a href="mailto:info@gtmc-npi.eu" style="color: #818cf8;">info@gtmc-npi.eu</a><br>
        Website: <a href="https://gtmc-npi.eu" style="color: #818cf8;">gtmc-npi.eu</a>
    </p>
</div>

<div class="footer">
    © <?= date('Y') ?> <a href="https://gtmc-npi.eu">GTMC S.r.l.</a> — All rights reserved
</div>

<script>
function resetCookieConsent() {
    document.cookie = 'gtmc_cookie_consent=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    alert('<?= addslashes(t('cookies.reset_msg')) ?>');
    location.reload();
}
</script>
<script src="static/js/cookie-consent.js"></script>
</body>
</html>
