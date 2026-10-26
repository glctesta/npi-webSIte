<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in production
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    // Hardened session cookie: HttpOnly, SameSite=Lax, Secure when served over HTTPS
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Pagina precedente (solo GET): il form demo la usa per tornare alla pagina
// di provenienza anche quando il Referer non arriva (policy, estensioni).
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $_SESSION['prev_page'] = $_SESSION['cur_page'] ?? null;
    $_SESSION['cur_page'] = $_SERVER['REQUEST_URI'] ?? null;
}

/**
 * Load .env file from outside public_html
 */
function load_env() {
    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $candidates = [];
    if ($doc_root !== '') {
        // Recommended: one level above public_html
        $candidates[] = dirname($doc_root) . '/.env';
    }
    // Fallback 1: two levels above includes/ (i.e. outside the site folder)
    $candidates[] = __DIR__ . '/../../.env';
    // Fallback 2: site root (protected by .htaccess, not recommended)
    $candidates[] = __DIR__ . '/../.env';

    $env_path = null;
    foreach ($candidates as $c) {
        if ($c && is_file($c)) { $env_path = $c; break; }
    }

    if (!$env_path) {
        error_log('GTMC-NPI: .env file not found! Searched: ' . implode(', ', $candidates));
        return;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments
        if ($line === '' || $line[0] === '#') continue;

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Remove quotes if present
            $value = trim($value, '"\'');
            // Allow multi-line PEM values written with literal \n
            $value = str_replace('\\n', "\n", $value);
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

load_env();

function env($key, $default = '') {
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

// Database
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'r145004gtmc_npi'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS'));

// Site
if (!defined('SITE_URL'))  define('SITE_URL', rtrim(env('SITE_URL', 'https://gtmc-npi.eu'), '/'));
if (!defined('SITE_NAME')) define('SITE_NAME', 'GTMC-NPI');
define('PRODUCT_SLUG', 'npi');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('RELEASES_DIR', __DIR__ . '/../uploads/releases/');

// Email (SMTP via cPanel)
define('MAIL_FROM', env('SMTP_USER', 'info@gtmc-npi.eu'));
define('MAIL_FROM_NAME', 'GTMC-NPI');
define('SMTP_HOST', env('SMTP_HOST', 'mail.gtmc-npi.eu'));
define('SMTP_PORT', (int)env('SMTP_PORT', '465'));
define('SMTP_USER', env('SMTP_USER', 'info@gtmc-npi.eu'));
define('SMTP_PASS', env('SMTP_PASS'));

// Support / staff notifications
define('SUPPORT_EMAIL', env('SUPPORT_EMAIL', 'help@gtmc-npi.eu'));
define('STAFF_EMAIL', env('STAFF_EMAIL', 'info@gtmc-npi.eu'));

/*
 * License server (RSA). The PRIVATE key signs the tokens validated by the
 * GTMC-NPI application (app/services/license_service.py). It must never
 * be committed nor placed inside public_html: point to it with
 * LICENSE_RSA_PRIVATE_KEY_PATH (preferred) or paste the PEM in
 * LICENSE_RSA_PRIVATE_KEY (use \n for line breaks).
 */
define('LICENSE_RSA_PRIVATE_KEY_PATH', env('LICENSE_RSA_PRIVATE_KEY_PATH', ''));
define('LICENSE_RSA_PRIVATE_KEY', env('LICENSE_RSA_PRIVATE_KEY', ''));
define('LICENSE_DEFAULT_GRACE_DAYS', (int)env('LICENSE_DEFAULT_GRACE_DAYS', '30'));

// Legacy secret kept only for HMAC-signed API helpers; MUST be set in .env.
define('LICENSE_MASTER_KEY', env('LICENSE_MASTER_KEY', ''));

// Languages
define('LANGUAGES', ['en', 'it', 'de', 'ro', 'fr', 'es']);
define('DEFAULT_LANG', 'en');

// Flash messages helper
function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['message' => $msg, 'type' => $type];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

require_once __DIR__ . '/csrf.php';
