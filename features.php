<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/feature_list.php';

$page_title = t('nav.features');
$meta_description = t('seo.features_desc');
require __DIR__ . '/includes/header.php';

$tiers = [
    'trial'        => ['dashboard', 'projects', 'tasks', 'documents'],
    'professional' => ['gantt', 'gates', 'budget', 'checklist', 'notifications', 'change'],
    'enterprise'   => ['multi_company', 'iatf', 'client_bridge', 'nre', 'api', 'capacity', 'kpi'],
];
?>

<section style="padding-top: 9rem; padding-bottom: 2rem; background: radial-gradient(circle at 30% 10%, rgba(99, 102, 241, 0.12), transparent 45%);">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <h1><?= htmlspecialchars(t('features_page.title')) ?></h1>
        <p style="font-size: 1.15rem; color: var(--text-main); margin-top: 0.8rem;"><?= htmlspecialchars(t('features_page.intro')) ?></p>
    </div>

    <div style="max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        <?php foreach (npi_feature_pages() as $slug => [$icon, $sec]): ?>
        <article class="glass-card reveal">
            <h2 style="font-size: 1.35rem; margin-bottom: 0.9rem;"><?= $icon ?> <?= htmlspecialchars(t($sec . '.title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);"><?= htmlspecialchars(t($sec . '.claim')) ?></p>
            <a href="/<?= $slug ?>" class="btn btn-outline" style="margin-top: 1rem; display: inline-flex;"><?= htmlspecialchars(t('features_page.learn_more')) ?> →</a>
        </article>
        <?php endforeach; ?>

        <article class="glass-card reveal" style="border-color: rgba(16, 185, 129, 0.32); background: linear-gradient(135deg, rgba(16, 185, 129, 0.11), rgba(14, 165, 233, 0.11));">
            <h2 style="font-size: 1.35rem; margin-bottom: 0.9rem;">🔐 <?= htmlspecialchars(t('features_page.security_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);"><?= htmlspecialchars(t('features_page.security_desc')) ?></p>
            <a href="/security" class="btn btn-outline" style="margin-top: 1rem; display: inline-flex;"><?= htmlspecialchars(t('features_page.learn_more')) ?> →</a>
        </article>
    </div>

    <!-- What each license tier unlocks (mirrors app/helpers/features.py) -->
    <div class="reveal" style="max-width: 1100px; margin: 3rem auto 0;">
        <h2 style="text-align:center; margin-bottom: 1.5rem;"><?= htmlspecialchars(t('features_page.tiers_title')) ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
            <?php foreach ($tiers as $tier => $items): ?>
            <div class="glass-card">
                <h3 style="margin-bottom: .3rem;"><?= htmlspecialchars(t('pricing.' . $tier . '_name')) ?></h3>
                <p style="color: var(--text-muted); font-size: .9rem; margin-bottom: .8rem;"><?= htmlspecialchars(t('features_page.tier_' . $tier . '_limit')) ?></p>
                <?php if ($tier !== 'trial'): ?><p style="font-size:.85rem; color: var(--text-secondary); margin-bottom:.4rem;"><?= htmlspecialchars(t('features_page.tier_includes_previous')) ?></p><?php endif; ?>
                <ul style="padding-left: 1.1rem; line-height: 1.8; font-size: .92rem;">
                    <?php foreach ($items as $item): ?>
                    <li><?= htmlspecialchars(t('features_page.f_' . $item)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="reveal" style="max-width: 1100px; margin: 2rem auto 0;">
        <div class="glass-card" style="text-align: center; background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.12));">
            <h3 style="font-size: 1.4rem; margin-bottom: 0.75rem;"><?= htmlspecialchars(t('features_page.cta_title')) ?></h3>
            <p style="color: var(--text-muted); margin-bottom: 1.2rem;"><?= htmlspecialchars(t('features_page.cta_subtitle')) ?></p>
            <a href="/demo" class="btn btn-primary"><?= htmlspecialchars(t('features_page.cta_button')) ?></a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
