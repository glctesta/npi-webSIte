<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

require __DIR__ . '/includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 2rem; background: radial-gradient(circle at 82% 8%, rgba(14, 165, 233, 0.14), transparent 45%);">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <h1><?= htmlspecialchars(t('gdpr_page.title')) ?></h1>
        <p style="max-width: 980px; margin: 0.8rem auto 0; color: var(--text-main); line-height: 1.85;">
            <?= htmlspecialchars(t('gdpr_page.subtitle')) ?>
        </p>
    </div>

    <div style="max-width: 1100px; margin: 0 auto; display: grid; gap: 1.5rem;">
        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('gdpr_page.controller_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.controller_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('gdpr_page.data_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.data_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('gdpr_page.purpose_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.purpose_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('gdpr_page.basis_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.basis_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('gdpr_page.retention_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.retention_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('gdpr_page.recipients_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.recipients_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal" style="border-color: rgba(99, 102, 241, 0.36); background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(14, 165, 233, 0.1));">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('gdpr_page.rights_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.rights_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.25rem; margin-bottom: 0.7rem;"><?= htmlspecialchars(t('gdpr_page.note_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('gdpr_page.note_body')) ?>
            </p>
            <p style="line-height: 1.8; color: var(--text-muted); margin-top: 0.7rem;"><?= htmlspecialchars(t('gdpr_page.last_updated')) ?>: <?= date('d/m/Y') ?></p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
