<?php
/**
 * db_check.php — one-shot diagnostic for the "Service Temporarily Unavailable" page.
 *
 * That page is printed by includes/db.php when the MySQL connection fails;
 * this tool reproduces the same .env lookup and connection test and tells
 * you exactly what is wrong.
 *
 * Usage (CLI only, e.g. from a one-shot cron job on hosts without Terminal):
 *   php tools/db_check.php > /home/USER/dbcheck.txt 2>&1
 * Read the output file from cPanel File Manager, then DELETE this tool
 * and the cron job (see DEPLOY_FTP.md, section 5-bis).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "GTMC-NPI db_check — " . date('c') . "\n";
echo "PHP " . PHP_VERSION . "\n";
echo "pdo_mysql driver: " . (in_array('mysql', PDO::getAvailableDrivers(), true) ? 'present' : 'MISSING (this alone causes the 503 page)') . "\n";

// Same search order as includes/config.php load_env()
$candidates = [];
$doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
if ($doc_root !== '') {
    $candidates[] = dirname($doc_root) . '/.env';
}
$candidates[] = __DIR__ . '/../../.env';
$candidates[] = __DIR__ . '/../.env';

echo "\n.env search:\n";
$env_path = null;
foreach ($candidates as $c) {
    $found = $c && is_file($c);
    echo "  " . ($found ? '[FOUND] ' : '[miss  ] ') . $c . "\n";
    if ($found && $env_path === null) {
        $env_path = $c;
    }
}
if (!$env_path) {
    echo "\nNO .env FOUND: create it one level ABOVE public_html (see DEPLOY_FTP.md section 5), then re-run.\n";
    exit(1);
}
echo "\n.env used: $env_path\n";

$values = [];
foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $values[trim($parts[0])] = trim(trim($parts[1]), "\"'");
    }
}
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
    if (!array_key_exists($k, $values)) {
        echo "  $k: NOT SET\n";
        continue;
    }
    $v = $values[$k];
    if ($k === 'DB_PASS') {
        echo "  $k: " . str_repeat('*', min(strlen($v), 8)) . " (" . strlen($v) . " chars)\n";
    } else {
        echo "  $k: " . var_export($v, true) . "\n";
        if (strpos($v, ' #') !== false) {
            echo "      WARNING: value contains ' #' - inline comments are NOT stripped: remove them from .env\n";
        }
    }
}

$host = $values['DB_HOST'] ?? 'localhost';
$name = $values['DB_NAME'] ?? '';
$user = $values['DB_USER'] ?? '';
$pass = $values['DB_PASS'] ?? '';
if ($name === '' || $user === '') {
    echo "\nDB_NAME / DB_USER are empty: fill them in .env (WITH the cPanel prefix, e.g. r145204gtmc_npi) and re-run.\n";
    exit(1);
}

echo "\nConnection test:\n";
try {
    $pdo = new PDO('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4', $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "  CONNECTED to '$name' as '$user'\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo '  tables (' . count($tables) . '): ' . (implode(', ', $tables) ?: '(none)') . "\n";
    if (!in_array('plans', $tables, true)) {
        echo "  NOTE: schema not imported yet - import sql/001_schema.sql in phpMyAdmin (pages would fail in other ways).\n";
    } else {
        $n = $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
        echo "  plans rows: $n\n";
    }
    echo "\nAll good: the 503 page should be gone. DELETE this tool and the cron job.\n";
} catch (PDOException $e) {
    $m = $e->getMessage();
    echo "  FAILED: $m\n";
    echo "\nMeaning:\n";
    if (stripos($m, 'could not find driver') !== false) {
        echo "  - pdo_mysql missing in this PHP: switch PHP version in cPanel MultiPHP or ask the hosting.\n";
    } elseif (stripos($m, 'Access denied') !== false) {
        echo "  - Wrong DB_USER / DB_PASS, or the user is not linked to the DB with ALL PRIVILEGES\n";
        echo "    (cPanel - MySQL Databases - Add User To Database). The user must be the FULL prefixed name.\n";
    } elseif (stripos($m, 'Unknown database') !== false) {
        echo "  - DB_NAME wrong: it must be the FULL cPanel name shown in MySQL Databases, e.g. r145204gtmc_npi.\n";
    } elseif (stripos($m, 'getaddrinfo') !== false || stripos($m, 'No such host') !== false) {
        echo "  - DB_HOST wrong: on cPanel use 'localhost'.\n";
    }
    echo "\nFix .env and re-run this check.\n";
    exit(1);
}
