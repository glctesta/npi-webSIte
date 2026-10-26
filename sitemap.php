<?php
/**
 * Dynamic XML Sitemap Generator
 * Generates a multilingual sitemap with hreflang annotations.
 * 
 * Self-contained — does NOT include config.php to avoid session/header conflicts.
 */
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$base = 'https://gtmc-npi.eu';
$languages = ['en', 'it', 'de', 'ro', 'fr', 'es'];

// Static public pages
$pages = [
    '/'             => ['priority' => '1.0', 'changefreq' => 'weekly'],
    '/features'     => ['priority' => '0.9', 'changefreq' => 'monthly'],
    '/pricing'      => ['priority' => '0.9', 'changefreq' => 'monthly'],
    '/demo'         => ['priority' => '0.8', 'changefreq' => 'monthly'],
    '/about'        => ['priority' => '0.7', 'changefreq' => 'monthly'],
    '/benefits'     => ['priority' => '0.8', 'changefreq' => 'monthly'],
    '/requirements' => ['priority' => '0.6', 'changefreq' => 'monthly'],
    '/security'     => ['priority' => '0.5', 'changefreq' => 'monthly'],
    '/blog'         => ['priority' => '0.8', 'changefreq' => 'weekly'],
    '/privacy-policy' => ['priority' => '0.3', 'changefreq' => 'yearly'],
    '/cookie-policy'  => ['priority' => '0.3', 'changefreq' => 'yearly'],
    '/terms-of-use'   => ['priority' => '0.3', 'changefreq' => 'yearly'],
];

// Module pages (same list as the home page)
require_once __DIR__ . '/includes/feature_list.php';
foreach (array_keys(npi_feature_pages()) as $slug) {
    $pages['/' . $slug] = ['priority' => '0.8', 'changefreq' => 'monthly'];
}

// Load blog articles
$articles = [];
$articles_dir = __DIR__ . '/blog/articles/';
if (is_dir($articles_dir)) {
    foreach (glob($articles_dir . '*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['slug'])) {
            $articles[] = $data;
        }
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php foreach ($pages as $path => $meta): ?>
    <url>
        <loc><?= htmlspecialchars($base . $path) ?></loc>
<?php foreach ($languages as $lang): ?>
        <xhtml:link rel="alternate" hreflang="<?= $lang ?>" href="<?= htmlspecialchars($base . $path . '?lang=' . $lang) ?>" />
<?php endforeach; ?>
        <changefreq><?= $meta['changefreq'] ?></changefreq>
        <priority><?= $meta['priority'] ?></priority>
    </url>
<?php endforeach; ?>
<?php foreach ($articles as $article): ?>
    <url>
        <loc><?= htmlspecialchars($base . '/blog/' . $article['slug']) ?></loc>
<?php foreach ($languages as $lang): ?>
        <xhtml:link rel="alternate" hreflang="<?= $lang ?>" href="<?= htmlspecialchars($base . '/blog/' . $article['slug'] . '?lang=' . $lang) ?>" />
<?php endforeach; ?>
        <lastmod><?= htmlspecialchars($article['date']) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>
</urlset>
