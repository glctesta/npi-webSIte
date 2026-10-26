<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

// Homepage = pagina prodotto GTMC-NPI: il titolo resta quello del sito.
$meta_description = t('seo.home_desc');
$json_ld = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'GTMC-NPI',
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Windows 10, Windows 11, Windows Server',
    'url' => SITE_URL,
    'description' => t('seo.home_desc'),
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR', 'description' => t('hero.btn_download')],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
require __DIR__ . '/includes/header.php';

// Le sei coppie "ce lăsăm / face sistemul" sono un array in i18n, fuori da t().
function sv_leave_rows() {
    global $_translations;
    $arr = $_translations['sv']['leave'] ?? null;
    if (!is_array($arr)) {
        $en = json_decode(file_get_contents(__DIR__ . '/i18n/en.json'), true) ?: [];
        $arr = $en['sv']['leave'] ?? [];
    }
    return $arr;
}

$eco_style = 'display: flex; align-items: center; gap: 1rem; padding: 1.2rem; border-radius: 12px; background: rgba(99, 102, 241, 0.06); border: 1px solid rgba(99, 102, 241, 0.15); text-decoration: none; transition: all 0.3s ease;';
$ecosystem = [
    ['https://gtmc.ro', 'gtmc.ro', '🏢', 'ecosystem.gtmc_ro'],
    ['https://gtmc-mes.eu', 'gtmc-mes.eu', '🏭', 'ecosystem.gtmc_mes'],
    ['https://gtmc-skills.eu', 'gtmc-skills.eu', '🎓', 'ecosystem.gtmc_skills'],
    ['https://gtmc-meeting.eu', 'gtmc-meeting.eu', '📅', 'ecosystem.gtmc_meeting'],
    ['https://gtmc-hsb.eu', 'gtmc-hsb.eu', '🛡️', 'ecosystem.gtmc_hsb'],
    ['https://gtmc-angajati.eu', 'gtmc-angajati.eu', '👥', 'ecosystem.gtmc_angajati'],
];
?>

<!-- Hero: promessa di quiete per il cabinet -->
<section class="hero">
    <canvas id="particles"></canvas>
    <div class="hero-content">
        <h1><?= htmlspecialchars(t('hero.title')) ?></h1>
        <p><?= htmlspecialchars(t('hero.subtitle')) ?></p>
        <div class="hero-btns">
            <a href="/demo" class="btn btn-primary"><?= htmlspecialchars(t('hero.btn_demo')) ?></a>
            <a href="/auth/register.php" class="btn btn-secondary"><?= htmlspecialchars(t('hero.btn_download')) ?></a>
        </div>
    </div>
</section>

<!-- Contrasto: la giornata di oggi e la giornata con GTMC-NPI -->
<section class="sv-contrast reveal" aria-label="Before / After">
    <figure class="sv-photo-wrap">
        <img class="sv-photo sv-photo-before" src="/static/images/npi-chaos.svg"
             alt="<?= htmlspecialchars(t('sv.contrast_before')) ?>" loading="lazy" width="1024" height="1024">
        <figcaption class="sv-photo-label sv-photo-label-before"><?= htmlspecialchars(t('sv.contrast_before')) ?></figcaption>
    </figure>
    <figure class="sv-photo-wrap">
        <img class="sv-photo" src="/static/images/npi-calm.svg"
             alt="<?= htmlspecialchars(t('sv.contrast_after')) ?>" loading="lazy" width="1000" height="1000">
        <figcaption class="sv-photo-label sv-photo-label-after"><?= htmlspecialchars(t('sv.contrast_after')) ?></figcaption>
    </figure>
</section>

<!-- Registro: ce lăsați în urmă -->
<section id="ramuri" class="reveal">
    <div class="section-heading">
        <h2><?= htmlspecialchars(t('sv.leave_title')) ?></h2>
    </div>
    <div class="sv-ledger">
        <div class="sv-ledger-head" aria-hidden="true">
            <span><?= htmlspecialchars(t('sv.leave_col_before')) ?></span>
            <span><?= htmlspecialchars(t('sv.leave_col_now')) ?></span>
        </div>
        <?php foreach (sv_leave_rows() as [$leave, $now]): ?>
        <div class="sv-ledger-row">
            <p class="sv-ledger-before"><?= htmlspecialchars($leave) ?></p>
            <p class="sv-ledger-now"><?= htmlspecialchars($now) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Ce câștigați: prosa + assistente AI -->
<section id="castiga" class="reveal">
    <div class="sv-gain">
        <div class="sv-gain-text">
            <h2><?= htmlspecialchars(t('sv.gain_title')) ?></h2>
            <p><?= htmlspecialchars(t('sv.gain_p1')) ?></p>
            <p><?= htmlspecialchars(t('sv.gain_p2')) ?></p>
        </div>
        <aside class="sv-chat" aria-label="<?= htmlspecialchars(t('sv.gain_ai_label')) ?>">
            <p class="sv-chat-label"><?= htmlspecialchars(t('sv.gain_ai_label')) ?></p>
            <p class="sv-chat-q"><?= htmlspecialchars(t('sv.gain_ai_q')) ?></p>
            <p class="sv-chat-a"><?= htmlspecialchars(t('sv.gain_ai_a')) ?></p>
        </aside>
    </div>
</section>

<!-- Un partener fidel: dichiarazione, prezzo, firma -->
<section id="partener" class="sv-close reveal">
    <h2><?= htmlspecialchars(t('sv.partner_title')) ?></h2>
    <p class="sv-partner-text"><?= htmlspecialchars(t('sv.partner_text')) ?></p>
    <p class="sv-price"><?= htmlspecialchars(t('sv.price_hook')) ?></p>
    <div class="hero-btns">
        <a href="/demo" class="btn btn-primary"><?= htmlspecialchars(t('hero.btn_demo')) ?></a>
    </div>
    <p class="sv-tagline"><?= htmlspecialchars(t('sv.tagline')) ?></p>
</section>

<!-- Famiglia GTMC -->
<section id="ecosystem" class="reveal" style="padding: 4rem 2rem;">
    <div class="section-heading" style="text-align: center; margin-bottom: 3rem;">
        <h2 style="font-size: 2rem;">🌐 <?= htmlspecialchars(t('ecosystem.title')) ?></h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; max-width: 1000px; margin: 0 auto;">
        <?php foreach ($ecosystem as [$url, $label, $icon, $key]): ?>
        <a href="<?= $url ?>" target="_blank" rel="noopener" style="<?= $eco_style ?>">
            <span style="font-size: 1.6rem;"><?= $icon ?></span>
            <div>
                <strong style="color: var(--accent-primary); font-size: 1.05rem;"><?= $label ?></strong>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0.2rem 0 0;"><?= htmlspecialchars(t($key)) ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
