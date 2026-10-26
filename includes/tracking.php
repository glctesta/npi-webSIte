<?php
/**
 * Site visit tracking — records every page view.
 * Include this file in config.php or header.php to track all pages automatically.
 */
function track_visit() {
    // Skip bots, static assets and API calls
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/(api|static|uploads|includes|i18n|staff)/#', $uri)) return;
    if (preg_match('#\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|map)$#i', $uri)) return;

    // Skip if no DB configured
    if (!defined('DB_NAME') || empty(DB_NAME)) return;

    // GTMC_PREVIEW=1: anteprima locale senza MySQL
    if (getenv('GTMC_PREVIEW')) return;

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Simple bot detection
        if (preg_match('#(bot|crawler|spider|slurp|googlebot|bingbot|yandex|baidu)#i', $ua)) return;

        $visitor_hash = hash('sha256', $ip . '|' . $ua);
        $page = parse_url($uri, PHP_URL_PATH) ?: '/';

        require_once __DIR__ . '/db.php';
        db_query(
            "INSERT INTO site_visits (visitor_hash, page_url, ip_address, user_agent) VALUES (?, ?, ?, ?)",
            [$visitor_hash, $page, $ip, substr($ua, 0, 512)]
        );
    } catch (\Exception $e) {
        // Silently fail — tracking should never break the site
        error_log("Visit tracking error: " . $e->getMessage());
    }
}

/**
 * Get site statistics for the counters.
 */
function get_site_stats() {
    try {
        require_once __DIR__ . '/db.php';
        
        // Unique visitors (distinct visitor hashes)
        $unique = db_fetch("SELECT COUNT(DISTINCT visitor_hash) AS cnt FROM site_visits");
        
        // Total page views
        $total = db_fetch("SELECT COUNT(*) AS cnt FROM site_visits");
        
        // Total downloads
        $downloads = db_fetch("SELECT COUNT(*) AS cnt FROM downloads");
        
        return [
            'unique_visitors' => (int)($unique['cnt'] ?? 0),
            'total_visits'    => (int)($total['cnt'] ?? 0),
            'total_downloads' => (int)($downloads['cnt'] ?? 0)
        ];
    } catch (\Exception $e) {
        error_log("Stats error: " . $e->getMessage());
        return [
            'unique_visitors' => 0,
            'total_visits'    => 0,
            'total_downloads' => 0
        ];
    }
}

// Auto-track on include
track_visit();
