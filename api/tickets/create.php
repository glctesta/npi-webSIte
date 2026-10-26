<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = [];
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$ticket_code = $input['ticket_code'] ?? '';
$contact_name = $input['contact_name'] ?? '';
$contact_email = $input['contact_email'] ?? '';
$company = $input['company'] ?? null;
$app_version = $input['app_version'] ?? null;
$category = $input['category'] ?? null;
$priority = $input['priority'] ?? 'normal';
$subject = $input['subject'] ?? '';
$description = $input['description'] ?? '';

if (!$ticket_code || !$contact_name || !$contact_email || !$subject || !$description) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

$upload_dir = __DIR__ . '/../../uploads/tickets/' . $ticket_code . '/';
$files_data = [];

if (!empty($_FILES)) {
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    foreach (['screenshot', 'logfile'] as $file_field) {
        if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES[$file_field]['tmp_name'];
            $name = basename($_FILES[$file_field]['name']);
            $safe_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name);
            $dest_path = $upload_dir . $safe_name;
            if (move_uploaded_file($tmp_name, $dest_path)) {
                $files_data[] = [
                    'path' => $dest_path,
                    'name' => $name
                ];
            }
        }
    }
}

try {
    $db = get_db();
    $db->beginTransaction();

    $ticket_id = db_insert(
        "INSERT INTO tickets (ticket_code, contact_name, contact_email, company, app_version, category, priority, status, subject, description) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)",
        [$ticket_code, $contact_name, $contact_email, $company, $app_version, $category, $priority, $subject, $description]
    );

    db_insert(
        "INSERT INTO ticket_messages (ticket_id, sender_type, sender_name, message) VALUES (?, 'user', ?, ?)",
        [$ticket_id, $contact_name, $description]
    );
    
    foreach ($files_data as $f) {
        db_insert(
            "INSERT INTO ticket_messages (ticket_id, sender_type, sender_name, message, attachment_path, attachment_name) VALUES (?, 'user', ?, '', ?, ?)",
            [$ticket_id, $contact_name, $f['path'], $f['name']]
        );
    }

    $db->commit();

    $support_email = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (defined('MAIL_FROM') ? MAIL_FROM : 'support@example.com');
    if ($support_email) {
        $mailer = new SmtpMailer();
        $mail_subject = "New Ticket [$ticket_code]: " . $subject;
        $mail_body = "<h3>New Ticket from " . htmlspecialchars($contact_name) . "</h3><p><strong>Code:</strong> " . htmlspecialchars($ticket_code) . "</p><p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p><p>" . nl2br(htmlspecialchars($description)) . "</p>";
        $mailer->sendMail($support_email, $mail_subject, $mail_body);
    }

    echo json_encode(['ok' => true, 'ticket_id' => $ticket_id]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error', 'details' => $e->getMessage()]);
}
