<?php
/**
 * Esegue tutte le prove del sito.
 *
 *   php tests/run.php
 *
 * Esce con codice 0 se tutto passa, 1 altrimenti: si puo' mettere in un
 * gancio prima del deploy.
 */

$suite = ['test_releases.php', 'test_releases_admin.php', 'test_endpoints.php', 'test_license.php', 'test_demo_return.php'];
$falliti = [];

// Extensions loaded with -d on this command line (e.g. openssl on Windows) are
// not inherited by the child processes: forward them when the child lacks them.
$extra = '';
foreach (['openssl', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) continue;
    $probe = trim((string)shell_exec(PHP_BINARY . ' -r ' . escapeshellarg("echo extension_loaded('$ext') ? 1 : 0;")));
    if ($probe !== '1') $extra .= ' -d extension=' . $ext;
}
if ($extra !== '') {
    $extra = ' -d extension_dir=' . escapeshellarg(ini_get('extension_dir')) . $extra;
}

foreach ($suite as $file) {
    echo "\n═══ $file ═══\n";
    $percorso = escapeshellarg(__DIR__ . '/' . $file);
    passthru(PHP_BINARY . $extra . ' ' . $percorso, $codice);
    if ($codice !== 0) {
        $falliti[] = $file;
    }
}

echo "\n════════════════════════════════════════════════════════════\n";
if (!$falliti) {
    echo "Tutte le suite sono passate.\n\n";
    exit(0);
}
echo 'Suite fallite: ' . implode(', ', $falliti) . "\n\n";
exit(1);
