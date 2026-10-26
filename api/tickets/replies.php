<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$ticket_code = $_GET['ticket_code'] ?? '';

if (!$ticket_code) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing ticket_code']);
    exit;
}

$ticket = db_fetch("SELECT * FROM tickets WHERE ticket_code = ?", [$ticket_code]);

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Ticket not found']);
    exit;
}

$messages = db_fetch_all(
    "SELECT sender_type, sender_name, message, attachment_name, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC",
    [$ticket['id']]
);

$formatted_messages = [];
foreach ($messages as $m) {
    $text = $m['message'];
    if ($m['attachment_name']) {
        $text .= ($text ? "\n" : "") . "[Attachment: " . $m['attachment_name'] . "]";
    }
    $formatted_messages[] = [
        'sender_type' => $m['sender_type'],
        'sender_name' => $m['sender_name'],
        'text' => $text,
        'created_at' => $m['created_at']
    ];
}

echo json_encode([
    'ok' => true,
    'status' => $ticket['status'],
    'messages' => $formatted_messages
]);
