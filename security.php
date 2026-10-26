<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = t('nav.features');
$meta_description = t('seo.security_desc');
require __DIR__ . '/includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 2rem; background: radial-gradient(circle at 75% 8%, rgba(14, 165, 233, 0.16), transparent 45%);">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <h1><?= htmlspecialchars(t('security_page.title')) ?></h1>
        <p style="font-size: 1.15rem; color: var(--text-main); margin-top: 0.8rem;"><?= htmlspecialchars(t('security_page.claim')) ?></p>
    </div>

    <div style="max-width: 1100px; margin: 0 auto; display: grid; gap: 1.5rem;">
        <article class="glass-card reveal">
            <h2 style="font-size: 1.45rem; margin-bottom: 0.9rem;"><?= htmlspecialchars(t('security_page.benefits_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.8rem;"><?= htmlspecialchars(t('security_page.benefits_p1')) ?></p>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.8rem;"><?= htmlspecialchars(t('security_page.benefits_p2')) ?></p>
            <p style="line-height: 1.8; color: var(--text-main);"><?= htmlspecialchars(t('security_page.benefits_p3')) ?></p>
        </article>

        <article class="glass-card reveal" style="border-color: rgba(16, 185, 129, 0.32); background: linear-gradient(135deg, rgba(16, 185, 129, 0.11), rgba(14, 165, 233, 0.11));">
            <h2 style="font-size: 1.3rem; margin-bottom: 0.7rem;"><?= htmlspecialchars(t('security_page.summary_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);"><?= htmlspecialchars(t('security_page.summary_text')) ?></p>
        </article>
    </div>

    <div class="reveal" style="max-width: 1100px; margin: 2rem auto 0;">
        <div class="glass-card" style="text-align: center; background: linear-gradient(135deg, rgba(14, 165, 233, 0.12), rgba(99, 102, 241, 0.12));">
            <h3 style="font-size: 1.4rem; margin-bottom: 0.75rem;"><?= htmlspecialchars(t('security_page.cta_title')) ?></h3>
            <p style="color: var(--text-muted); margin-bottom: 1.2rem;"><?= htmlspecialchars(t('security_page.cta_subtitle')) ?></p>
            <a href="/demo" class="btn btn-primary"><?= htmlspecialchars(t('security_page.cta_button')) ?></a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
