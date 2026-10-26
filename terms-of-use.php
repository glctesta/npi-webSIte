<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

// HTML version of the Terms of Use / EULA (replaces the Skills PDFs).
// TODO(legal): have the final text reviewed and optionally publish signed PDFs.
$page_title = t('terms_page.title');
$meta_description = t('terms_page.subtitle');
require __DIR__ . '/includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 2rem; background: radial-gradient(circle at 18% 8%, rgba(99, 102, 241, 0.14), transparent 45%);">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <h1><?= htmlspecialchars(t('terms_page.title')) ?></h1>
        <p style="max-width: 980px; margin: 0.8rem auto 0; color: var(--text-main); line-height: 1.85;">
            <?= htmlspecialchars(t('terms_page.subtitle')) ?>
        </p>
    </div>

    <div style="max-width: 1000px; margin: 0 auto; display: grid; gap: 1.25rem;">
        <?php for ($i = 1; $i <= 10; $i++): ?>
        <article class="glass-card reveal" id="s<?= $i ?>">
            <h2 style="font-size: 1.25rem; margin-bottom: 0.7rem;"><?= $i ?>. <?= htmlspecialchars(t("terms_page.s{$i}_title")) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);"><?= htmlspecialchars(t("terms_page.s{$i}_body")) ?></p>
        </article>
        <?php endfor; ?>

        <article class="glass-card reveal" style="border-color: rgba(99, 102, 241, 0.36);">
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('terms_page.contact')) ?>
                <a href="mailto:info@gtmc-npi.eu" style="text-decoration: underline;">info@gtmc-npi.eu</a>
            </p>
            <p style="color: var(--text-muted); margin-top: .6rem;"><?= htmlspecialchars(t('terms_page.version')) ?></p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
