<?php
// staff/newsletter-upload.php — image upload for the newsletter editor. Returns { ok, url }
require_once __DIR__ . '/includes/staff_auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_staff_logged_in()) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Not logged in']); exit; }
if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
    http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Session expired, reload the page.']); exit;
}

$f = $_FILES['image'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['ok' => false, 'error' => 'Upload failed.']); exit; }
if ($f['size'] > 3 * 1024 * 1024) { echo json_encode(['ok' => false, 'error' => 'Image too large (max 3 MB).']); exit; }

// Validate real content, not the file name
$info = @getimagesize($f['tmp_name']);
$types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
if (!$info || !isset($types[$info[2]])) { echo json_encode(['ok' => false, 'error' => 'Only JPG, PNG, GIF or WebP images.']); exit; }

$sub = date('Ym');
$dir = __DIR__ . '/../uploads/newsletter/' . $sub;
if (!is_dir($dir) && !mkdir($dir, 0755, true)) { echo json_encode(['ok' => false, 'error' => 'Cannot create upload folder.']); exit; }

$name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) { echo json_encode(['ok' => false, 'error' => 'Cannot save file.']); exit; }

// Absolute URL: email clients cannot resolve relative paths
echo json_encode(['ok' => true, 'url' => SITE_URL . "/uploads/newsletter/$sub/$name"]);
