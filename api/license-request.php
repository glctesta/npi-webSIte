<?php
/**
 * license-request.php — Riceve richieste di modifica licenza dal programma client.
 *
 * POST /api/license-request.php
 * Body JSON:
 * {
 *   "machine_id":     "abc123...",
 *   "hostname":       "PC-UFFICIO",
 *   "current_plan":   "basic",
 *   "request_type":   "upgrade|renew|add_seats|support",
 *   "desired_plan":   "professional",      // solo per upgrade
 *   "message":        "Testo libero...",
 *   "license_token":  "payload.signature", // opzionale, per identificare la licenza
 *   "contact_email":  "admin@azienda.com"
 * }
 *
 * Risposta:
 * {"ok": true, "request_id": 42}
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

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

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$machine_id    = trim($input['machine_id'] ?? '');
$hostname      = trim($input['hostname'] ?? '');
$current_plan  = trim($input['current_plan'] ?? '');
$request_type  = trim($input['request_type'] ?? '');
$desired_plan  = trim($input['desired_plan'] ?? '');
$message       = trim($input['message'] ?? '');
$license_token = trim($input['license_token'] ?? '');
$contact_email = trim($input['contact_email'] ?? '');

// Validazione
$valid_types = ['upgrade', 'renew', 'add_seats', 'support'];
if (!in_array($request_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request_type. Must be: ' . implode(', ', $valid_types)]);
    exit;
}

if (!$machine_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing machine_id']);
    exit;
}

if (!$contact_email) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing contact_email']);
    exit;
}

// Cerca la licenza associata
$license_id = null;
$user_email = $contact_email;

if ($license_token || !empty($input['license_key'])) {
    // The RSA token carries the license key in its payload ('key').
    require_once __DIR__ . '/../includes/license_lib.php';
    $key_from_token = '';
    if ($license_token) {
        $payload = lic_peek($license_token);
        $key_from_token = is_array($payload) ? (string)($payload['key'] ?? '') : '';
    }
    $lookup_key = lic_normalize_key($key_from_token ?: ($input['license_key'] ?? ''));
    if (lic_key_valid($lookup_key)) {
        list($lic) = lic_find($lookup_key);
        if ($lic) {
            $license_id = (int)$lic['id'];
            if (!empty($lic['user_email'])) {
                $user_email = $lic['user_email'];
            }
        }
    }
}

// Se non abbiamo trovato la licenza dal token, cerchiamo per email + machine_id
if (!$license_id && $contact_email) {
    $lic = db_fetch(
        "SELECT l.id FROM licenses l
         JOIN users u ON u.id = l.user_id
         JOIN license_activations la ON la.license_id = l.id
         WHERE u.email = ? AND la.machine_id = ? AND l.status = 'active'
         ORDER BY l.id DESC LIMIT 1",
        [$contact_email, $machine_id]
    );
    if ($lic) {
        $license_id = (int)$lic['id'];
    }
}

// Salva la richiesta
try {
    $request_id = db_insert(
        "INSERT INTO license_requests
         (license_id, user_email, machine_id, hostname, current_plan, request_type, desired_plan, message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$license_id, $user_email, $machine_id, $hostname, $current_plan, $request_type,
         $desired_plan ?: null, $message ?: null]
    );
} catch (Exception $e) {
    error_log("license-request.php insert error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
}

// Notifica staff via email
try {
    require_once __DIR__ . '/../includes/mailer.php';

    $type_labels = [
        'upgrade'   => '⬆️ Upgrade Piano',
        'renew'     => '🔄 Rinnovo Licenza',
        'add_seats' => '💻 Aggiunta Postazioni',
        'support'   => '🆘 Supporto Licenza',
    ];
    $type_label = $type_labels[$request_type] ?? $request_type;

    // Everything below comes from the client: escape before putting it in HTML.
    $esc = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    $desired_plan_h = $esc($desired_plan);
    $user_email_h   = $esc($user_email);
    $current_plan_h = $esc($current_plan);
    $machine_id_h   = $esc($machine_id);
    $hostname_h     = $esc($hostname);

    $plan_label = $desired_plan ? " → <strong>{$desired_plan_h}</strong>" : '';

    $subject = "GTMC-NPI — {$type_label} da " . preg_replace('/[\r\n]+/', ' ', $user_email);
    $body = <<<HTML
    <div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto;">
      <div style="background: linear-gradient(135deg, #1e293b, #334155); padding: 20px 30px; border-radius: 12px 12px 0 0;">
        <h1 style="margin: 0; color: #fff; font-size: 20px;">📋 Nuova Richiesta Licenza</h1>
      </div>
      <div style="background: #f8fafc; padding: 25px 30px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <tr><td style="padding: 8px 0; color: #64748b; width: 140px;">Tipo</td><td style="padding: 8px 0; font-weight: 600;">{$type_label}</td></tr>
          <tr><td style="padding: 8px 0; color: #64748b;">Email</td><td style="padding: 8px 0;">{$user_email_h}</td></tr>
          <tr><td style="padding: 8px 0; color: #64748b;">Piano attuale</td><td style="padding: 8px 0;">{$current_plan_h}{$plan_label}</td></tr>
          <tr><td style="padding: 8px 0; color: #64748b;">Machine ID</td><td style="padding: 8px 0; font-family: monospace; font-size: 12px;">{$machine_id_h}</td></tr>
          <tr><td style="padding: 8px 0; color: #64748b;">Hostname</td><td style="padding: 8px 0;">{$hostname_h}</td></tr>
        </table>
HTML;

    if ($message) {
        $message_safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $body .= <<<HTML
        <div style="margin-top: 15px; padding: 12px 15px; background: #fff; border-left: 4px solid #6366f1; border-radius: 4px;">
          <p style="margin: 0 0 5px; color: #64748b; font-size: 12px; text-transform: uppercase;">Messaggio</p>
          <p style="margin: 0; color: #1e293b;">{$message_safe}</p>
        </div>
HTML;
    }

    $body .= <<<HTML
        <div style="margin-top: 20px; text-align: center;">
          <a href="https://gtmc-npi.eu/staff/license-requests.php"
             style="display: inline-block; padding: 10px 25px; background: #6366f1; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
            Vedi Richieste →
          </a>
        </div>
      </div>
    </div>
HTML;

    $staff_email = defined('STAFF_EMAIL') ? STAFF_EMAIL : 'info@gtmc-npi.eu';
    $mailer = new SmtpMailer();
    $result = $mailer->sendMail($staff_email, $subject, $body);
    if (!$result) {
        error_log("license-request.php SMTP failed: " . $mailer->getLog());
    }
} catch (Exception $e) {
    // Non bloccare la risposta se l'email fallisce
    error_log("license-request.php email error: " . $e->getMessage());
}

echo json_encode([
    'ok'         => true,
    'request_id' => (int)$request_id,
    'message'    => 'Richiesta inviata con successo. Sarai contattato entro 24 ore.'
]);
