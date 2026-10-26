<?php
/**
 * create_staff.php — creates (or resets the password of) a staff back-office user.
 *
 * Usage (CLI only, from the site folder on the server):
 *   php tools/create_staff.php email@gtmc-npi.eu "Full Name" [admin|manager|sales]
 *
 * The password is asked interactively (never passed on the command line,
 * so it does not end up in the shell history) and stored with password_hash().
 *
 * Non-interactive mode for one-shot cron jobs (hosts without SSH/Terminal):
 *   php tools/create_staff.php email@gtmc-npi.eu "Full Name" admin "Min12CharsPass"
 * The 4th argument is the password: it stays visible in the cron job list,
 * so DELETE the cron job right after the first run (see DEPLOY_FTP.md).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/db.php';

$email = strtolower(trim($argv[1] ?? ''));
$name  = trim($argv[2] ?? '');
$role  = $argv[3] ?? 'admin';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || !in_array($role, ['admin', 'manager', 'sales'], true)) {
    fwrite(STDERR, "Usage: php tools/create_staff.php <email> \"<Full Name>\" [admin|manager|sales]\n");
    exit(1);
}

function ask_hidden($prompt) {
    echo $prompt;
    $unix = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    if ($unix) shell_exec('stty -echo 2>/dev/null');
    $v = rtrim((string)fgets(STDIN), "\r\n");
    if ($unix) { shell_exec('stty echo 2>/dev/null'); echo "\n"; }
    return $v;
}

if (isset($argv[4]) && $argv[4] !== '') {
    // Non-interactive mode (cron): password from the 4th argument
    $pass = $argv[4];
    fwrite(STDERR, "Note: password passed on the command line - delete the cron job after this run.\n");
} else {
    $pass = ask_hidden('Password (min 12 chars): ');
    if (ask_hidden('Repeat password: ') !== $pass) {
        fwrite(STDERR, "Passwords do not match.\n");
        exit(1);
    }
}
if (strlen($pass) < 12) {
    fwrite(STDERR, "Password too short.\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$existing = db_fetch('SELECT id FROM staff WHERE email = ?', [$email]);
if ($existing) {
    db_query('UPDATE staff SET password_hash = ?, full_name = ?, role = ?, is_active = 1 WHERE id = ?', [$hash, $name, $role, $existing['id']]);
    echo "Updated staff user #{$existing['id']} ($email, $role).\n";
} else {
    $id = db_insert('INSERT INTO staff (email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)', [$email, $hash, $name, $role]);
    echo "Created staff user #$id ($email, $role). Log in at " . SITE_URL . "/staff/\n";
}
