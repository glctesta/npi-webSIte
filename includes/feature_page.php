<?php
/**
 * Shared template for the module pages (/projects, /gantt, …).
 *
 * Usage in a page file:
 *     $fp_slug = 'gantt';
 *     require __DIR__ . '/includes/feature_page.php';
 *
 * Reads the i18n section configured in includes/feature_list.php:
 *   <sec>.title, .claim, .intro, .tier, .s1_title/.s1_body … .s4_*,
 *   optional .example_title/.example_body, optional legal rows .l1_ref/.l1_desc … .l6_*,
 *   .summary_title, .summary_text
 * plus the shared fp_common.* keys (CTA, labels).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/feature_list.php';

$fp_pages = npi_feature_pages();
if (!isset($fp_slug, $fp_pages[$fp_slug])) {
    http_response_code(404);
    exit('Not found');
}
[$fp_icon, $fp_sec] = $fp_pages[$fp_slug];

/** True when the key exists in the current (or fallback) language. */
$fp_has = function ($key) use ($fp_sec) {
    $k = $fp_sec . '.' . $key;
    return t($k) !== $k && trim(t($k)) !== '';
};
$fp_t = function ($key) use ($fp_sec) {
    return htmlspecialchars(t($fp_sec . '.' . $key));
};

$page_title = t($fp_sec . '.title');
$meta_description = t($fp_sec . '.claim');
$json_ld = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => t($fp_sec . '.title') . ' — GTMC-NPI',
    'description' => t($fp_sec . '.claim'),
    'url' => SITE_URL . '/' . $fp_slug,
    'isPartOf' => ['@type' => 'WebSite', 'name' => 'GTMC-NPI', 'url' => SITE_URL],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
require __DIR__ . '/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 2rem; background: radial-gradient(circle at 75% 8%, rgba(99, 102, 241, 0.12), transparent 45%);">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <div style="font-size: 3rem; margin-bottom: .5rem;"><?= $fp_icon ?></div>
        <h1><?= $fp_t('title') ?></h1>
        <p style="font-size: 1.15rem; color: var(--text-main); margin-top: 0.8rem;"><?= $fp_t('claim') ?></p>
        <?php if ($fp_has('tier')): ?>
        <p style="margin-top: .8rem;"><span style="background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.35); padding: .25rem .8rem; border-radius: 999px; font-size: .85rem;">
            <?= htmlspecialchars(t('fp_common.available_in')) ?>: <strong><?= $fp_t('tier') ?></strong>
        </span></p>
        <?php endif; ?>
    </div>

    <div style="max-width: 1100px; margin: 0 auto; display: grid; gap: 1.5rem;">
        <?php if ($fp_has('intro')): ?>
        <article class="glass-card reveal">
            <p style="line-height: 1.8; color: var(--text-main); font-size: 1.05rem;"><?= $fp_t('intro') ?></p>
        </article>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
            <?php for ($i = 1; $i <= 4; $i++): if (!$fp_has("s{$i}_title")) continue; ?>
            <article class="glass-card reveal">
                <h2 style="font-size: 1.3rem; margin-bottom: 0.9rem;"><?= $fp_t("s{$i}_title") ?></h2>
                <p style="line-height: 1.8; color: var(--text-main);"><?= $fp_t("s{$i}_body") ?></p>
            </article>
            <?php endfor; ?>
        </div>

        <?php if ($fp_has('l1_ref')): ?>
        <article class="glass-card reveal">
            <h2 style="font-size: 1.3rem; margin-bottom: 0.9rem;">⚖️ <?= htmlspecialchars(t('fp_common.legal_title')) ?></h2>
            <table style="width: 100%; border-collapse: collapse;">
                <?php for ($i = 1; $i <= 6; $i++): if (!$fp_has("l{$i}_ref")) continue; ?>
                <tr style="border-bottom: 1px solid rgba(255,255,255,.06);">
                    <td style="padding: .6rem .8rem .6rem 0; white-space: nowrap; font-weight: 600; color: var(--primary-light, #818cf8); vertical-align: top;"><?= $fp_t("l{$i}_ref") ?></td>
                    <td style="padding: .6rem 0; color: var(--text-main); line-height: 1.6;"><?= $fp_t("l{$i}_desc") ?></td>
                </tr>
                <?php endfor; ?>
            </table>
            <p style="color: var(--text-muted); font-size: .8rem; margin-top: .8rem;"><?= htmlspecialchars(t('fp_common.legal_disclaimer')) ?></p>
        </article>
        <?php endif; ?>

        <?php if ($fp_has('example_title')): ?>
        <article class="glass-card reveal" style="border-left: 4px solid var(--primary, #6366f1);">
            <h2 style="font-size: 1.2rem; margin-bottom: 0.7rem;">💡 <?= $fp_t('example_title') ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);"><?= $fp_t('example_body') ?></p>
        </article>
        <?php endif; ?>

        <article class="glass-card reveal" style="border-color: rgba(16, 185, 129, 0.32); background: linear-gradient(135deg, rgba(16, 185, 129, 0.11), rgba(14, 165, 233, 0.11));">
            <h2 style="font-size: 1.3rem; margin-bottom: 0.7rem;"><?= $fp_t('summary_title') ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);"><?= $fp_t('summary_text') ?></p>
        </article>
    </div>

    <div class="reveal" style="max-width: 1100px; margin: 2rem auto 0;">
        <div class="glass-card" style="text-align: center; background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.12));">
            <h3 style="font-size: 1.4rem; margin-bottom: 0.75rem;"><?= htmlspecialchars(t('fp_common.cta_title')) ?></h3>
            <p style="color: var(--text-muted); margin-bottom: 1.2rem;"><?= htmlspecialchars(t('fp_common.cta_subtitle')) ?></p>
            <a href="/demo" class="btn btn-primary"><?= htmlspecialchars(t('fp_common.cta_button')) ?></a>
            <a href="/features" class="btn btn-outline" style="margin-left: .5rem;"><?= htmlspecialchars(t('fp_common.all_modules')) ?></a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
