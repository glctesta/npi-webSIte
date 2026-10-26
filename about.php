<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

// Load stats for counters
require_once __DIR__ . '/includes/tracking.php';
$stats = get_site_stats();

$page_title = t('about_page.title');
$meta_description = t('seo.about_desc');
require __DIR__ . '/includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 2rem; background: radial-gradient(circle at 20% 10%, rgba(99, 102, 241, 0.15), transparent 45%);">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <h1><?= htmlspecialchars(t('about_page.title')) ?></h1>
        <p style="max-width: 980px; margin: 0.8rem auto 0; color: var(--text-main); line-height: 1.8;">
            <?= htmlspecialchars(t('about_page.subtitle')) ?>
        </p>
    </div>

    <!-- Counters Section -->
    <div class="about-counters reveal" style="max-width: 900px; margin: 0 auto 2.5rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 0 1.5rem;">
        <div class="glass-card about-counter-card">
            <div class="about-counter-icon">👥</div>
            <div class="about-counter-value counter" data-target="<?= $stats['unique_visitors'] ?>"><?= number_format($stats['unique_visitors']) ?></div>
            <div class="about-counter-label"><?= htmlspecialchars(t('about_page.counter_unique')) ?></div>
        </div>
        <div class="glass-card about-counter-card">
            <div class="about-counter-icon">📊</div>
            <div class="about-counter-value counter" data-target="<?= $stats['total_visits'] ?>"><?= number_format($stats['total_visits']) ?></div>
            <div class="about-counter-label"><?= htmlspecialchars(t('about_page.counter_visits')) ?></div>
        </div>
        <div class="glass-card about-counter-card">
            <div class="about-counter-icon">⬇️</div>
            <div class="about-counter-value counter" data-target="<?= $stats['total_downloads'] ?>"><?= number_format($stats['total_downloads']) ?></div>
            <div class="about-counter-label"><?= htmlspecialchars(t('about_page.counter_downloads')) ?></div>
        </div>
    </div>

    <div style="max-width: 1100px; margin: 0 auto; display: grid; gap: 1.5rem;">
        <article class="glass-card reveal">
            <h2 style="font-size: 1.45rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('about_page.story_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.8rem;"><?= htmlspecialchars(t('about_page.story_p1')) ?></p>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.8rem;"><?= htmlspecialchars(t('about_page.story_p2')) ?></p>
            <p style="line-height: 1.8; color: var(--text-main);"><?= htmlspecialchars(t('about_page.story_p3')) ?></p>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.45rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('about_page.aoi_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.9rem;"><?= htmlspecialchars(t('about_page.aoi_body')) ?></p>
            <a href="https://aoisystems.eu" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:0.45rem;">
                <?= htmlspecialchars(t('about_page.aoi_link')) ?>
            </a>
        </article>

        <article class="glass-card reveal">
            <h2 style="font-size: 1.45rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('about_page.team_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main);"><?= htmlspecialchars(t('about_page.team_body')) ?></p>
        </article>

        <article class="glass-card reveal" style="border-color: rgba(245, 158, 11, 0.35); background: linear-gradient(135deg, rgba(245, 158, 11, 0.09), rgba(99, 102, 241, 0.1));">
            <h2 style="font-size: 1.45rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('about_page.territory_title')) ?></h2>
            <p style="line-height: 1.8; color: var(--text-main); margin-bottom: 0.8rem;"><?= htmlspecialchars(t('about_page.territory_body')) ?></p>
            <p style="line-height: 1.8; color: var(--text-main); font-weight: 600;"><?= htmlspecialchars(t('about_page.closing')) ?></p>
        </article>
    </div>
</section>

<style>
.about-counter-card {
    text-align: center;
    padding: 1.8rem 1rem;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
}

.about-counter-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.12);
}

.about-counter-icon {
    font-size: 2rem;
    margin-bottom: 0.6rem;
}

.about-counter-value {
    font-size: 2.2rem;
    font-weight: 700;
    font-family: var(--font-display);
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.3rem;
}

.about-counter-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 500;
}

@media (max-width: 640px) {
    .about-counters {
        grid-template-columns: 1fr !important;
    }
    .about-counter-value {
        font-size: 1.8rem;
    }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
