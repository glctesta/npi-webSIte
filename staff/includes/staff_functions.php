<?php
require_once __DIR__ . '/../../includes/db.php';

// ── Flash Messages (only if not already defined in config.php) ──────
if (!function_exists('flash')) {
    function flash($message, $type = 'success') {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
}

if (!function_exists('get_flash')) {
    function get_flash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}

// ── Dashboard Stats ────────────────────────────────────────────────────
function get_dashboard_stats() {
    $stats = [
        'total_users' => 0,
        'verified_users' => 0,
        'expiring_licenses' => 0,
        'pending_demos' => 0,
        'total_downloads' => 0
    ];

    try {
        $stats['total_users'] = db_fetch("SELECT COUNT(*) as c FROM users")['c'] ?? 0;
        $stats['verified_users'] = db_fetch("SELECT COUNT(*) as c FROM users WHERE email_verified = 1")['c'] ?? 0;
        $stats['expiring_licenses'] = db_fetch("SELECT COUNT(*) as c FROM licenses WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)")['c'] ?? 0;
        $stats['pending_demos'] = db_fetch("SELECT COUNT(*) as c FROM demo_requests WHERE status = 'pending'")['c'] ?? 0;
        $stats['total_downloads'] = db_fetch("SELECT COUNT(*) as c FROM downloads")['c'] ?? 0;
    } catch (Exception $e) {
        // Tables might not exist yet
    }

    return $stats;
}

function check_expiring_licenses() {
    try {
        return db_fetch_all("
            SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email as customer_email
            FROM licenses l
            JOIN users u ON l.user_id = u.id
            WHERE l.status = 'active' AND l.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
            ORDER BY l.expires_at ASC
        ");
    } catch (Exception $e) {
        return [];
    }
}

function get_recent_users($limit = 10) {
    try {
        return db_fetch_all("SELECT id, first_name, last_name, email, email_verified, created_at FROM users ORDER BY created_at DESC LIMIT ?", [$limit]);
    } catch (Exception $e) {
        return [];
    }
}

function get_recent_demo_requests($limit = 10) {
    try {
        return db_fetch_all("SELECT * FROM demo_requests ORDER BY created_at DESC LIMIT ?", [$limit]);
    } catch (Exception $e) {
        return [];
    }
}

// ── Formatting ─────────────────────────────────────────────────────────
function format_currency($amount, $currency = 'EUR') {
    return number_format($amount, 2) . ' ' . $currency;
}

// ── UI Helpers ─────────────────────────────────────────────────────────
function badge_html($text, $color) {
    $color_classes = [
        'success' => 'badge-success',
        'danger' => 'badge-danger',
        'warning' => 'badge-warning',
        'info' => 'badge-info',
        'primary' => 'badge-primary',
        'secondary' => 'badge-secondary'
    ];
    $class = $color_classes[$color] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($text) . '</span>';
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return "just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    if ($diff < 2592000) return floor($diff / 86400) . "d ago";
    return date('Y-m-d', $time);
}

// ── License Helpers ────────────────────────────────────────────────────
function generate_api_token($license_id, $expires_at) {
    $token = bin2hex(random_bytes(32));
    try {
        // Deactivate existing tokens
        db_query("UPDATE api_tokens SET is_active = 0 WHERE license_id = ?", [$license_id]);
        // Insert new token
        db_insert("INSERT INTO api_tokens (license_id, token, expires_at, is_active) VALUES (?, ?, ?, 1)",
            [$license_id, $token, $expires_at]);
    } catch (Exception $e) {
        error_log("Failed to generate API token: " . $e->getMessage());
    }
    return $token;
}

function get_license_stats() {
    $stats = [
        'total' => 0,
        'active' => 0,
        'expired' => 0,
        'expiring_soon' => 0,
        'suspended' => 0,
        'revoked' => 0
    ];
    try {
        $stats['total'] = db_fetch("SELECT COUNT(*) as c FROM licenses")['c'] ?? 0;
        $stats['active'] = db_fetch("SELECT COUNT(*) as c FROM licenses WHERE status = 'active' AND expires_at > NOW()")['c'] ?? 0;
        $stats['expired'] = db_fetch("SELECT COUNT(*) as c FROM licenses WHERE status = 'expired' OR (status = 'active' AND expires_at <= NOW())")['c'] ?? 0;
        $stats['expiring_soon'] = db_fetch("SELECT COUNT(*) as c FROM licenses WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)")['c'] ?? 0;
        $stats['suspended'] = db_fetch("SELECT COUNT(*) as c FROM licenses WHERE status = 'suspended'")['c'] ?? 0;
        $stats['revoked'] = db_fetch("SELECT COUNT(*) as c FROM licenses WHERE status = 'revoked'")['c'] ?? 0;
    } catch (Exception $e) {}
    return $stats;
}

function get_plan_slug_by_id($plan_id) {
    try {
        $plan = db_fetch("SELECT slug FROM plans WHERE id = ?", [$plan_id]);
        return $plan ? $plan['slug'] : 'trial';
    } catch (Exception $e) {
        return 'trial';
    }
}

/** Constant-time CSRF check for staff POST forms (token from staff_header.php). */
function staff_csrf_ok() {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/** Default installations per tier (intranet server instances). */
function default_max_activations($tier) {
    return match ($tier) {
        'professional' => 3,
        'enterprise'   => 5,
        default        => 1,
    };
}

/** Default active NPI projects per tier (NULL = unlimited, Enterprise). */
function default_max_projects($tier) {
    return match ($tier) {
        'trial'        => 3,
        'professional' => 25,
        default        => null,
    };
}

/** Default app users per tier (NULL = unlimited, Enterprise). */
function default_max_users($tier) {
    return match ($tier) {
        'trial'        => 5,
        'professional' => 15,
        default        => null,
    };
}

/** Feature names known by the GTMC-NPI app — usable as add-ons beyond the tier. */
function npi_feature_addons() {
    return ['change_management', 'iatf_profile', 'client_bridge', 'nre', 'multi_company',
            'capacity_costs', 'kpi_postlaunch', 'api_access', 'email_notifications', 'sso_ad'];
}


