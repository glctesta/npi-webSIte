<?php
/**
 * generate_rsa_keys.php — creates a NEW RSA key pair for the license server.
 *
 * Usage (CLI only):
 *   php tools/generate_rsa_keys.php /home/USER/keys/license_private.pem [bits]
 *
 * - Writes the PRIVATE key to the given path (must be OUTSIDE public_html),
 *   permissions 0600. Refuses to overwrite an existing file.
 * - Prints the PUBLIC key PEM: paste it into the app's
 *   app/services/license_pubkey.py (PUBLIC_KEY_PEM) and rebuild the installer.
 *
 * WARNING: a new key pair invalidates every token issued with the old key.
 * To stay compatible with apps already installed, do NOT generate a new pair:
 * upload the existing bridge key instead (see DEPLOY.md). To print the public
 * key of an existing private key: php tools/generate_rsa_keys.php --public /path/key.pem
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!function_exists('openssl_pkey_new')) {
    fwrite(STDERR, "The PHP openssl extension is required.\n");
    exit(1);
}

$args = array_slice($argv, 1);
if (!$args || in_array($args[0], ['-h', '--help'], true)) {
    echo "Usage:\n  php tools/generate_rsa_keys.php <private_key_path> [bits=3072]\n  php tools/generate_rsa_keys.php --public <private_key_path>\n";
    exit($args ? 0 : 1);
}

if ($args[0] === '--public') {
    $path = $args[1] ?? '';
    $key = is_readable($path) ? openssl_pkey_get_private(file_get_contents($path)) : false;
    if (!$key) {
        fwrite(STDERR, "Cannot read a private key from '$path'.\n");
        exit(1);
    }
    echo openssl_pkey_get_details($key)['key'];
    exit(0);
}

$path = $args[0];
$bits = (int)($args[1] ?? 3072);
if ($bits < 2048) {
    fwrite(STDERR, "Use at least 2048 bits.\n");
    exit(1);
}
if (file_exists($path)) {
    fwrite(STDERR, "Refusing to overwrite existing file: $path\n");
    exit(1);
}
$webroot = realpath(__DIR__ . '/..');
$dir = dirname($path);
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "Cannot create directory $dir\n");
    exit(1);
}
$real_dir = realpath($dir);
if ($webroot && $real_dir && strpos($real_dir . DIRECTORY_SEPARATOR, $webroot . DIRECTORY_SEPARATOR) === 0) {
    fwrite(STDERR, "The private key must be stored OUTSIDE the website folder ($webroot).\n");
    exit(1);
}

$cfg = ['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
$res = @openssl_pkey_new($cfg);
if (!$res) {
    // Windows builds need an explicit openssl.cnf (shipped in PHP's extras/ssl)
    $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
    if (is_file($cnf)) {
        $cfg['config'] = $cnf;
        $res = @openssl_pkey_new($cfg);
    }
}
if (!$res || !openssl_pkey_export($res, $private_pem, null, $cfg)) {
    fwrite(STDERR, "Key generation failed: " . openssl_error_string() . "\n");
    exit(1);
}
if (file_put_contents($path, $private_pem) === false) {
    fwrite(STDERR, "Cannot write $path\n");
    exit(1);
}
@chmod($path, 0600);
$public_pem = openssl_pkey_get_details($res)['key'];

echo "Private key written to: $path  (keep it secret, chmod 600)\n";
echo "Set in .env:  LICENSE_RSA_PRIVATE_KEY_PATH=$path\n\n";
echo "Public key (paste into app/services/license_pubkey.py -> PUBLIC_KEY_PEM):\n\n";
echo $public_pem;
