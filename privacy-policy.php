<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

require __DIR__ . '/includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 2rem; background: radial-gradient(circle at 82% 8%, rgba(14, 165, 233, 0.14), transparent 45%);">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <h1><?= htmlspecialchars(t('privacy_page.title')) ?></h1>
        <p style="max-width: 980px; margin: 0.8rem auto 0; color: var(--text-main); line-height: 1.85;">
            <?= htmlspecialchars(t('privacy_page.subtitle')) ?>
        </p>
    </div>

    <div style="max-width: 1100px; margin: 0 auto; display: grid; gap: 1.5rem;">
        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('privacy_page.principles_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('privacy_page.principles_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('privacy_page.purposes_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.7rem;">
                <?= htmlspecialchars(t('privacy_page.purposes_intro')) ?>
            </p>
            <ul style="list-style: none; display: grid; gap: 0.55rem; color: var(--text-main); line-height: 1.7;">
                <li>✅ <?= htmlspecialchars(t('privacy_page.purposes_1')) ?></li>
                <li>✅ <?= htmlspecialchars(t('privacy_page.purposes_2')) ?></li>
                <li>✅ <?= htmlspecialchars(t('privacy_page.purposes_3')) ?></li>
                <li>✅ <?= htmlspecialchars(t('privacy_page.purposes_4')) ?></li>
                <li>✅ <?= htmlspecialchars(t('privacy_page.purposes_5')) ?></li>
            </ul>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('privacy_page.measures_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('privacy_page.measures_body')) ?>
            </p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('privacy_page.rights_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.7rem;">
                <?= htmlspecialchars(t('privacy_page.rights_p1')) ?>
            </p>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('privacy_page.rights_p2')) ?>
            </p>
        </article>

        <article class="glass-card reveal" style="border-color: rgba(99, 102, 241, 0.36); background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(14, 165, 233, 0.1));">
            <h2 style="font-size: 1.4rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('privacy_page.legal_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.8rem;">
                <?= htmlspecialchars(t('privacy_page.legal_intro')) ?>
            </p>

            <h3 style="font-size: 1.05rem; margin: 0.8rem 0 0.5rem;"><?= htmlspecialchars(t('privacy_page.eu_title')) ?></h3>
            <ul style="list-style: none; display: grid; gap: 0.5rem; line-height: 1.7;">
                <li>• <?= htmlspecialchars(t('privacy_page.eu_gdpr')) ?>: <a href="https://eur-lex.europa.eu/eli/reg/2016/679/oj" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.official_link')) ?></a></li>
                <li>• <?= htmlspecialchars(t('privacy_page.eu_eprivacy')) ?>: <a href="https://eur-lex.europa.eu/legal-content/IT/TXT/?uri=CELEX:32002L0058" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.official_link')) ?></a></li>
                <li>• <?= htmlspecialchars(t('privacy_page.eu_nis2')) ?>: <a href="https://eur-lex.europa.eu/legal-content/IT/TXT/?uri=CELEX:32022L2555" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.official_link')) ?></a></li>
                <li>• <?= htmlspecialchars(t('privacy_page.eu_charter')) ?>: <a href="https://eur-lex.europa.eu/legal-content/IT/TXT/?uri=celex:12012P/TXT" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.official_link')) ?></a></li>
            </ul>

            <h3 style="font-size: 1.05rem; margin: 1rem 0 0.5rem;"><?= htmlspecialchars(t('privacy_page.ro_title')) ?></h3>
            <ul style="list-style: none; display: grid; gap: 0.5rem; line-height: 1.7;">
                <li>• <?= htmlspecialchars(t('privacy_page.ro_law190')) ?>: <a href="https://legislatie.just.ro/Public/DetaliiDocument/203151" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.official_link')) ?></a></li>
                <li>• <?= htmlspecialchars(t('privacy_page.ro_law506')) ?>: <a href="https://legislatie.just.ro/Public/DetaliiDocument/56973" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.official_link')) ?></a></li>
                <li>• <?= htmlspecialchars(t('privacy_page.ro_constitution')) ?>: <a href="https://legislatie.just.ro/Public/DetaliiDocument/47355" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.official_link')) ?></a></li>
                <li>• <?= htmlspecialchars(t('privacy_page.ro_anspdcp')) ?>: <a href="https://www.dataprotection.ro/" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;"><?= htmlspecialchars(t('privacy_page.authority_link')) ?></a></li>
            </ul>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.25rem; margin-bottom: 0.7rem;"><?= htmlspecialchars(t('privacy_page.note_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);">
                <?= htmlspecialchars(t('privacy_page.note_body')) ?>
            </p>
            <p style="line-height: 1.8; color: var(--text-muted); margin-top: 0.7rem;"><?= htmlspecialchars(t('privacy_page.last_updated')) ?>: <?= date('d/m/Y') ?></p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
