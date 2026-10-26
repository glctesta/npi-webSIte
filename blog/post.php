<?php
// ============================================================
// GTMC-NPI Blog — Single Article Page
// URL: /blog/{slug}
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Load a single article by slug.
 */
function load_article($slug) {
    // Sanitize slug
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $file = __DIR__ . '/articles/' . $slug . '.json';
    
    if (!file_exists($file)) return null;
    
    $data = json_decode(file_get_contents($file), true);
    return ($data && isset($data['slug'])) ? $data : null;
}

// Get slug from query param (set by .htaccess rewrite) or URL path
if (isset($_GET['slug']) && $_GET['slug'] !== '') {
    $slug = $_GET['slug'];
} else {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $slug = basename($path);
}

$article = load_article($slug);

if (!$article) {
    http_response_code(404);
    require __DIR__ . '/../includes/header.php';
    echo '<section style="padding-top: 12rem; padding-bottom: 4rem; text-align: center;">
        <div class="glass-card" style="max-width: 600px; margin: 0 auto;">
            <h1 style="font-size: 1.5rem; margin-bottom: 1rem;">' . htmlspecialchars(t('blog.not_found_title')) . '</h1>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">' . htmlspecialchars(t('blog.not_found_text')) . '</p>
            <a href="/blog" class="btn btn-primary">' . htmlspecialchars(t('blog.back_to_blog')) . '</a>
        </div>
    </section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$lang = get_lang();
$tr = $article['translations'][$lang] ?? $article['translations']['en'] ?? null;

if (!$tr) {
    // Fallback to first available translation
    $tr = reset($article['translations']);
}

$date = date('d M Y', strtotime($article['date']));
$reading_time = $article['reading_time'] ?? 5;
$author = $article['author'] ?? 'GTMC Team';

$page_title = $tr['title'];
$meta_description = $tr['excerpt'];
$og_type = 'article';
$canonical_url = 'https://gtmc-npi.eu/blog/' . $article['slug'];
$json_ld = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $tr['title'],
    'description' => $tr['excerpt'],
    'datePublished' => $article['date'],
    'author' => ['@type' => 'Organization', 'name' => $author],
    'publisher' => ['@type' => 'Organization', 'name' => 'GTMC-NPI', 'url' => 'https://gtmc-npi.eu']
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
require __DIR__ . '/../includes/header.php';
?>

<article class="blog-article" itemscope itemtype="https://schema.org/BlogPosting">
    <meta itemprop="datePublished" content="<?= htmlspecialchars($article['date']) ?>">
    <meta itemprop="author" content="<?= htmlspecialchars($author) ?>">
    
    <header class="blog-article-header">
        <div class="blog-article-header-inner">
            <a href="/blog" class="blog-back-link">← <?= htmlspecialchars(t('blog.back_to_blog')) ?></a>
            <div class="blog-article-meta">
                <span class="blog-article-category"><?= htmlspecialchars($tr['category_label'] ?? '') ?></span>
                <span class="blog-article-date"><?= $date ?></span>
                <span class="blog-article-reading"><?= $reading_time ?> min <?= htmlspecialchars(t('blog.read_time')) ?></span>
            </div>
            <h1 itemprop="headline"><?= htmlspecialchars($tr['title']) ?></h1>
            <p class="blog-article-excerpt"><?= htmlspecialchars($tr['excerpt']) ?></p>
            <div class="blog-article-author">
                <span><?= htmlspecialchars($author) ?></span>
            </div>
        </div>
    </header>

    <div class="blog-article-content" itemprop="articleBody">
        <?= $tr['content'] ?>
    </div>

    <footer class="blog-article-footer">
        <a href="/blog" class="btn btn-outline">← <?= htmlspecialchars(t('blog.back_to_blog')) ?></a>
    </footer>
</article>

<style>
.blog-article {
    padding-top: 7rem;
    padding-bottom: 3rem;
}

.blog-article-header {
    background: radial-gradient(circle at 25% 20%, rgba(99, 102, 241, 0.1), transparent 55%);
    padding: 3rem 1.5rem 2.5rem;
}

.blog-article-header-inner {
    max-width: 800px;
    margin: 0 auto;
}

.blog-back-link {
    display: inline-flex;
    align-items: center;
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    transition: color 0.2s;
}

.blog-back-link:hover {
    color: var(--accent-primary);
}

.blog-article-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.2rem;
    flex-wrap: wrap;
}

.blog-article-category {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--accent-primary);
    background: rgba(99, 102, 241, 0.12);
    padding: 0.3rem 0.7rem;
    border-radius: 6px;
}

.blog-article-date,
.blog-article-reading {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.blog-article-header h1 {
    font-size: clamp(1.75rem, 4vw, 2.5rem);
    line-height: 1.2;
    margin-bottom: 1rem;
    font-family: var(--font-display);
    letter-spacing: -0.02em;
}

.blog-article-excerpt {
    font-size: 1.1rem;
    line-height: 1.7;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.blog-article-author {
    font-size: 0.9rem;
    color: var(--text-muted);
    padding-top: 1rem;
    border-top: 1px solid var(--border-glass);
}

/* Article Body */
.blog-article-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 2.5rem 1.5rem;
    font-size: 1.05rem;
    line-height: 1.85;
    color: var(--text-main);
}

.blog-article-content h2 {
    font-size: 1.55rem;
    margin: 2.5rem 0 1rem;
    font-family: var(--font-display);
    letter-spacing: -0.01em;
    color: var(--text-main);
}

.blog-article-content h3 {
    font-size: 1.2rem;
    margin: 2rem 0 0.75rem;
    font-family: var(--font-display);
    color: var(--text-main);
}

.blog-article-content p {
    margin-bottom: 1.2rem;
    line-height: 1.85;
}

.blog-article-content ul,
.blog-article-content ol {
    margin: 1rem 0 1.5rem 1.5rem;
    line-height: 1.85;
}

.blog-article-content li {
    margin-bottom: 0.5rem;
}

.blog-article-content blockquote {
    border-left: 3px solid var(--accent-primary);
    padding: 1rem 1.5rem;
    margin: 1.5rem 0;
    background: rgba(99, 102, 241, 0.06);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    color: var(--text-main);
    font-style: italic;
}

.blog-article-content strong {
    color: var(--text-main);
    font-weight: 600;
}

.blog-article-content a {
    color: var(--accent-primary);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.blog-article-content a:hover {
    color: var(--accent-secondary);
}

.blog-article-content code {
    background: rgba(99, 102, 241, 0.1);
    padding: 0.15em 0.4em;
    border-radius: 4px;
    font-size: 0.9em;
}

.blog-article-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    font-size: 0.95rem;
}

.blog-article-content th,
.blog-article-content td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-glass);
}

.blog-article-content th {
    font-weight: 600;
    color: var(--accent-primary);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.blog-article-content hr {
    border: none;
    border-top: 1px solid var(--border-glass);
    margin: 2.5rem 0;
}

/* Footer */
.blog-article-footer {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    border-top: 1px solid var(--border-glass);
}

@media (max-width: 640px) {
    .blog-article-header {
        padding: 2rem 1rem 1.5rem;
    }
    .blog-article-content {
        padding: 1.5rem 1rem;
        font-size: 1rem;
    }
}
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>
