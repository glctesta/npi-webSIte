<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';

$ticket_id = (int)($_GET['ticket_id'] ?? 0);
$filename = $_GET['filename'] ?? '';

if (!$ticket_id || !$filename) {
    die("Invalid request.");
}

$ticket = db_fetch("SELECT ticket_code FROM tickets WHERE id = ?", [$ticket_id]);
if (!$ticket) {
    die("Ticket not found.");
}

$safe_filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($filename));
$filepath = __DIR__ . '/../uploads/tickets/' . $ticket['ticket_code'] . '/' . $safe_filename;

if (!file_exists($filepath)) {
    die("File not found.");
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
