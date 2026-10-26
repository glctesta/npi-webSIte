<?php
// Clear staff session when browsing public site — forces re-login on /staff/
unset($_SESSION['staff_id'], $_SESSION['staff_name'], $_SESSION['staff_role']);
?>
<!DOCTYPE html>
<html lang="<?= get_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' . htmlspecialchars(t('site.name')) : htmlspecialchars(t('site.title')) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_description ?? t('seo.default_desc')) ?>">
    <link rel="icon" type="image/png" href="/static/images/favicon.png">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url ?? 'https://gtmc-npi.eu' . strtok($_SERVER['REQUEST_URI'], '?')) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="<?= $og_type ?? 'website' ?>">
    <meta property="og:site_name" content="GTMC-NPI">
    <meta property="og:title" content="<?= htmlspecialchars($page_title ?? t('site.title')) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_description ?? t('seo.default_desc')) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical_url ?? 'https://gtmc-npi.eu' . strtok($_SERVER['REQUEST_URI'], '?')) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image ?? 'https://gtmc-npi.eu/static/images/og-default.png') ?>">
    <meta property="og:locale" content="<?= get_lang() ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title ?? t('site.title')) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta_description ?? t('seo.default_desc')) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image ?? 'https://gtmc-npi.eu/static/images/og-default.png') ?>">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "GTMC-NPI",
        "url": "https://gtmc-npi.eu",
        "logo": "https://gtmc-npi.eu/static/images/logo.png",
        "description": "<?= htmlspecialchars(t('seo.default_desc')) ?>",
        "sameAs": []
    }
    </script>
    <?php if (isset($json_ld)): ?>
    <script type="application/ld+json">
    <?= $json_ld ?>
    </script>
    <?php endif; ?>

    <link rel="stylesheet" href="/static/css/style.css?v=20261023a">
</head>
<body>
<?php @include_once __DIR__ . '/tracking.php'; ?>

    <!-- Navbar -->
    <nav class="navbar" id="navbar">
        <a href="/" class="nav-brand"><img src="/static/images/logo.png" alt="GTMC-NPI" style="height:36px; filter: invert(1);"></a>
        
        <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>

        <ul class="nav-links" id="navLinks">
            <li><a href="/"><?= t('nav.home') ?></a></li>
            <li><a href="/#ramuri"><?= t('sv.leave_title') ?></a></li>
            <li><a href="/#castiga"><?= t('sv.gain_title') ?></a></li>
            <li><a href="/pricing"><?= t('nav.pricing') ?></a></li>
            <li><a href="/demo?from=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/') ?>"><?= t('nav.demo') ?></a></li>
            <li><a href="/blog"><?= t('nav.blog') ?></a></li>
            
            <?php if (is_logged_in()): ?>
                <li><a href="/portal/"><?= t('nav.dashboard') ?></a></li>
                <li><a href="/portal/downloads.php"><?= t('nav.downloads') ?></a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-actions" id="navActions">
            <div class="lang-switcher">
                <button class="lang-btn" aria-label="<?= htmlspecialchars(t('nav.language')) ?>">
                    <?php if (get_lang() == 'en'): ?>🇬🇧
                    <?php elseif (get_lang() == 'it'): ?>🇮🇹
                    <?php elseif (get_lang() == 'de'): ?>🇩🇪
                    <?php elseif (get_lang() == 'ro'): ?>🇷🇴
                    <?php elseif (get_lang() == 'fr'): ?>🇫🇷
                    <?php elseif (get_lang() == 'es'): ?>🇪🇸
                    <?php else: ?>🌐
                    <?php endif; ?>
                </button>
                <div class="lang-menu">
                    <a href="?lang=en">🇬🇧 English</a>
                    <a href="?lang=it">🇮🇹 Italiano</a>
                    <a href="?lang=de">🇩🇪 Deutsch</a>
                    <a href="?lang=ro">🇷🇴 Română</a>
                    <a href="?lang=fr">🇫🇷 Français</a>
                    <a href="?lang=es">🇪🇸 Español</a>
                </div>
            </div>

            <?php if (is_logged_in()): ?>
                <a href="/auth/logout.php" class="btn btn-outline"><?= t('nav.logout') ?></a>
            <?php else: ?>
                <a href="/auth/login.php" class="btn btn-outline"><?= t('nav.login') ?></a>
                <a href="/auth/register.php" class="btn btn-primary"><?= t('nav.register') ?></a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="flash-container">
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="flash-message flash-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <main>
