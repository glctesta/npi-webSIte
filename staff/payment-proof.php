<?php
/**
 * Serves a payment proof (uploads/payments/ is not publicly reachable).
 */
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';

$id = (int)($_GET['id'] ?? 0);
$p = $id ? db_fetch("SELECT payment_proof_path FROM proforma_invoices WHERE id = ?", [$id]) : null;
if (!$p || empty($p['payment_proof_path'])) {
    http_response_code(404);
    die('File not found.');
}

$base = realpath(__DIR__ . '/../uploads/payments');
$file = realpath(__DIR__ . '/../' . $p['payment_proof_path']);
if ($base === false || $file === false || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    http_response_code(404);
    die('File not found.');
}

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . (isset($types[$ext]) ? 'inline' : 'attachment') . '; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
