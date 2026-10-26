<?php
// ============================================================
// GTMC-NPI Blog — Article Listing Page
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Load all articles from JSON files, sorted by date descending.
 */
function load_articles() {
    $articles_dir = __DIR__ . '/articles/';
    $articles = [];

    if (!is_dir($articles_dir)) return $articles;

    foreach (glob($articles_dir . '*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['slug'])) {
            $articles[] = $data;
        }
    }

    // Sort by date descending
    usort($articles, function($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });

    return $articles;
}

$articles = load_articles();
$lang = get_lang();

$page_title = t('blog.title');
$meta_description = t('blog.subtitle');
require __DIR__ . '/../includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 3rem; background: radial-gradient(circle at 30% 15%, rgba(99, 102, 241, 0.12), transparent 50%);">
    <div class="section-heading reveal" style="margin-bottom: 2.5rem;">
        <h1><?= htmlspecialchars(t('blog.title')) ?></h1>
        <p style="max-width: 720px; margin: 0.8rem auto 0; color: var(--text-muted); line-height: 1.8;">
            <?= htmlspecialchars(t('blog.subtitle')) ?>
        </p>
    </div>

    <div style="max-width: 1100px; margin: 0 auto; padding: 0 1.5rem;">
        <?php if (empty($articles)): ?>
            <div class="glass-card" style="text-align: center; padding: 3rem;">
                <p style="color: var(--text-muted);"><?= htmlspecialchars(t('blog.no_articles')) ?></p>
            </div>
        <?php else: ?>
            <div class="blog-grid">
                <?php foreach ($articles as $article):
                    $tr = $article['translations'][$lang] ?? $article['translations']['en'] ?? null;
                    if (!$tr) continue;
                    $date = date('d M Y', strtotime($article['date']));
                    $reading_time = $article['reading_time'] ?? 5;
                ?>
                <article class="blog-card glass-card reveal">
                    <div class="blog-card-body">
                        <div class="blog-card-meta">
                            <span class="blog-card-category"><?= htmlspecialchars($tr['category_label'] ?? '') ?></span>
                            <span class="blog-card-date"><?= $date ?></span>
                        </div>
                        <h2 class="blog-card-title">
                            <a href="/blog/<?= htmlspecialchars($article['slug']) ?>">
                                <?= htmlspecialchars($tr['title']) ?>
                            </a>
                        </h2>
                        <p class="blog-card-excerpt"><?= htmlspecialchars($tr['excerpt']) ?></p>
                        <div class="blog-card-footer">
                            <span class="blog-card-reading"><?= $reading_time ?> min <?= htmlspecialchars(t('blog.read_time')) ?></span>
                            <a href="/blog/<?= htmlspecialchars($article['slug']) ?>" class="blog-card-link">
                                <?= htmlspecialchars(t('blog.read_more')) ?> →
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.blog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}

.blog-card {
    display: flex;
    flex-direction: column;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
}

.blog-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.12);
}

.blog-card-body {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.blog-card-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}

.blog-card-category {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--accent-primary);
    background: rgba(99, 102, 241, 0.12);
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
}

.blog-card-date {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.blog-card-title {
    font-size: 1.25rem;
    line-height: 1.35;
    margin-bottom: 0.7rem;
    font-family: var(--font-display);
}

.blog-card-title a {
    color: var(--text-main);
    transition: color 0.2s ease;
}

.blog-card-title a:hover {
    color: var(--accent-primary);
}

.blog-card-excerpt {
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--text-muted);
    flex: 1;
    margin-bottom: 1.2rem;
}

.blog-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border-glass);
}

.blog-card-reading {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.blog-card-link {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--accent-primary);
}

.blog-card-link:hover {
    color: var(--accent-secondary);
}

@media (max-width: 480px) {
    .blog-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>
