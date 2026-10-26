<?php
// API endpoint: POST /api/proforma/request.php
// Creates a proforma invoice and sends it via email
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/license_pricing.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/proforma_pdf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Accept JSON or form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$plan_slug = trim($input['plan'] ?? '');
$contact_name = trim($input['name'] ?? '');
$contact_email = trim($input['email'] ?? '');
$company_name = trim($input['company'] ?? '');
$vat_id = trim($input['vat_id'] ?? '');
$billing_address = trim($input['address'] ?? '');
$lang = trim($input['lang'] ?? 'en');

// Validate required fields
if (!$contact_name || !$contact_email || !$plan_slug) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name, email and plan are required.']);
    exit;
}
if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email address.']);
    exit;
}
if (!in_array($plan_slug, ['basic', 'professional', 'enterprise', 'stations_addon'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid plan.']);
    exit;
}

// Get price (stations_addon: extra workstation, quoted manually by staff)
if ($plan_slug === 'stations_addon') {
    $amount = 0.00;
} else {
    $prices = get_license_prices();
    $plan_row = $prices[$plan_slug] ?? null;
    if (!$plan_row) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Plan not found.']);
        exit;
    }
    $amount = (float)$plan_row['price_eur'];
}
$currency = 'EUR';

// Generate proforma number: PORyyyy-030n (e.g. POR2026-0301, POR2026-0302...)
$year = date('Y');
$count_row = db_fetch("SELECT COUNT(*) as cnt FROM proforma_invoices WHERE proforma_number LIKE ?",
    ["POR{$year}-%"]);
$next_seq = ($count_row ? (int)$count_row['cnt'] : 0) + 1;
$proforma_number = sprintf("POR%s-%04d", $year, 300 + $next_seq);

// Check logged-in user
$user_id = $_SESSION['user_id'] ?? null;

// Insert
$pid = db_insert(
    "INSERT INTO proforma_invoices (user_id, proforma_number, plan_slug, company_name, contact_name, contact_email, vat_id, billing_address, amount, currency, status, lang)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', ?)",
    [$user_id, $proforma_number, $plan_slug, $company_name ?: null, $contact_name, $contact_email, $vat_id ?: null, $billing_address ?: null, $amount, $currency, $lang]
);

// ── Build proforma email ────────────────────────────────────────────────
$plan_label = $plan_slug === 'stations_addon' ? 'Additional workstation(s) — quotazione a seguire' : ucfirst($plan_slug);
$amount_fmt = $amount > 0 ? number_format($amount, 2, ',', '.') . ' ' . $currency : '—';
$date_fmt = date('d/m/Y');
$upload_url = SITE_URL . '/portal/upload-payment.php?ref=' . urlencode($proforma_number);

// GTMC Company Data
$gtmc = [
    'name' => 'GTMC CONSULTING SRL',
    'cui' => 'CUI 30767243',
    'reg' => 'Nr. Reg. Com. J02/771/2015',
    'euid' => 'EUID ROONRC.J2/771/2015',
    'address' => 'Bdul. G-Ral Vasile Milea 3, Et. 3, Ap. 8A',
    'city' => 'Arad, Cod 310131',
    'country' => 'România',
    'iban' => 'RO88INGB0000999903390932',
    'bank' => 'ING Bank, Timișoara',
];

$logo_url = SITE_URL . '/static/images/logo.png';

$email_body = <<<HTML
<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 650px; margin: 0 auto; background: #ffffff; color: #1a1a2e; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #6366f1, #4f46e5); padding: 25px 35px; display: flex; align-items: center; gap: 15px;">
        <img src="{$logo_url}" alt="GTMC" style="height: 40px; filter: brightness(10);">
        <div>
            <h1 style="margin: 0; color: #fff; font-size: 20px; letter-spacing: 0.5px;">PROFORMA INVOICE</h1>
            <p style="margin: 4px 0 0; color: rgba(255,255,255,0.8); font-size: 13px;">{$proforma_number}</p>
        </div>
    </div>

    <div style="padding: 30px 35px;">
        <!-- Two column: GTMC data | Customer data -->
        <table style="width: 100%; margin-bottom: 25px; border-collapse: collapse;">
            <tr>
                <td style="vertical-align: top; width: 50%; padding-right: 15px;">
                    <p style="margin: 0 0 3px; font-weight: 700; font-size: 14px; color: #4f46e5;">From:</p>
                    <p style="margin: 0; font-size: 13px; line-height: 1.7; color: #475569;">
                        <strong>{$gtmc['name']}</strong><br>
                        {$gtmc['cui']}<br>
                        {$gtmc['reg']}<br>
                        {$gtmc['address']}<br>
                        {$gtmc['city']}, {$gtmc['country']}<br>
                    </p>
                </td>
                <td style="vertical-align: top; width: 50%; padding-left: 15px;">
                    <p style="margin: 0 0 3px; font-weight: 700; font-size: 14px; color: #4f46e5;">To:</p>
                    <p style="margin: 0; font-size: 13px; line-height: 1.7; color: #475569;">
                        <strong>{$contact_name}</strong><br>
HTML;

if ($company_name) {
    $email_body .= "                        {$company_name}<br>\n";
}
if ($vat_id) {
    $email_body .= "                        VAT: {$vat_id}<br>\n";
}
if ($billing_address) {
    $email_body .= "                        " . nl2br(htmlspecialchars($billing_address)) . "<br>\n";
}

$email_body .= <<<HTML
                        {$contact_email}
                    </p>
                </td>
            </tr>
        </table>

        <!-- Invoice details -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <tr style="background: #f1f5f9;">
                <td style="padding: 8px 12px; font-weight: 600; font-size: 13px; color: #334155;">Date</td>
                <td style="padding: 8px 12px; font-weight: 600; font-size: 13px; color: #334155;">Reference</td>
            </tr>
            <tr>
                <td style="padding: 8px 12px; font-size: 13px; color: #475569;">{$date_fmt}</td>
                <td style="padding: 8px 12px; font-size: 13px; color: #475569;">{$proforma_number}</td>
            </tr>
        </table>

        <!-- Line items -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; border: 1px solid #e2e8f0;">
            <thead>
                <tr style="background: #4f46e5; color: #fff;">
                    <th style="padding: 10px 12px; text-align: left; font-size: 13px;">Description</th>
                    <th style="padding: 10px 12px; text-align: center; font-size: 13px;">Period</th>
                    <th style="padding: 10px 12px; text-align: right; font-size: 13px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 12px; font-size: 13px;">GTMC-NPI — <strong>{$plan_label}</strong> License</td>
                    <td style="padding: 12px; text-align: center; font-size: 13px;">1 Year</td>
                    <td style="padding: 12px; text-align: right; font-size: 14px; font-weight: 700;">{$amount_fmt}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; border-top: 2px solid #4f46e5;">
                    <td colspan="2" style="padding: 12px; text-align: right; font-weight: 700; font-size: 14px;">TOTAL</td>
                    <td style="padding: 12px; text-align: right; font-weight: 700; font-size: 16px; color: #4f46e5;">{$amount_fmt}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Bank details -->
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 18px; margin-bottom: 20px;">
            <p style="margin: 0 0 10px; font-weight: 700; color: #166534; font-size: 14px;">🏦 Bank Transfer Details</p>
            <table style="border-collapse: collapse; font-size: 13px; color: #1a1a2e;">
                <tr><td style="padding: 3px 15px 3px 0; color: #64748b;">Beneficiary:</td><td style="font-weight: 600;">{$gtmc['name']}</td></tr>
                <tr><td style="padding: 3px 15px 3px 0; color: #64748b;">IBAN:</td><td style="font-weight: 600; font-family: monospace; letter-spacing: 0.5px;">{$gtmc['iban']}</td></tr>
                <tr><td style="padding: 3px 15px 3px 0; color: #64748b;">Bank:</td><td>{$gtmc['bank']}</td></tr>
                <tr><td style="padding: 3px 15px 3px 0; color: #64748b;">Reference:</td><td style="font-weight: 600;">{$proforma_number}</td></tr>
            </table>
        </div>

        <!-- Upload payment -->
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 18px; margin-bottom: 20px;">
            <p style="margin: 0 0 8px; font-weight: 700; color: #1e40af; font-size: 14px;">📎 After Payment</p>
            <p style="margin: 0; font-size: 13px; color: #334155; line-height: 1.6;">
                Once you have completed the bank transfer, please upload your payment proof at:<br>
                <a href="{$upload_url}" style="color: #4f46e5; font-weight: 600;">{$upload_url}</a>
            </p>
        </div>

        <!-- Spam notice -->
        <div style="background: #fefce8; border-left: 4px solid #eab308; padding: 12px 16px; border-radius: 0 6px 6px 0; margin-bottom: 15px;">
            <p style="margin: 0; font-size: 12px; color: #854d0e;">
                ⚠️ If you don't see this email, please check your <strong>spam</strong> or <strong>junk</strong> folder.
            </p>
        </div>

        <p style="margin: 20px 0 0; font-size: 12px; color: #94a3b8; text-align: center;">
            This is a proforma invoice and does not represent a fiscal document.<br>
            For questions: <a href="mailto:info@gtmc-npi.eu" style="color: #4f46e5;">info@gtmc-npi.eu</a>
        </p>
    </div>

    <!-- Footer -->
    <div style="background: #f1f5f9; padding: 12px 35px; text-align: center; font-size: 11px; color: #94a3b8;">
        © {$year} {$gtmc['name']} — {$gtmc['address']}, {$gtmc['city']}
    </div>
</div>
HTML;

$subject = "GTMC-NPI - Proforma Invoice {$proforma_number}";

// Generate PDF attachment (with fallback if PDF generation fails)
$pdf_data = null;
$pdf_filename = "Proforma_{$proforma_number}.pdf";
try {
    ob_start();
    $pdf_data = @generate_proforma_pdf([
        'proforma_number' => $proforma_number,
        'contact_name' => $contact_name,
        'contact_email' => $contact_email,
        'company_name' => $company_name,
        'vat_id' => $vat_id,
        'billing_address' => $billing_address,
        'plan_label' => $plan_label,
        'amount' => $amount,
        'currency' => $currency,
        'date' => $date_fmt,
    ]);
    $ob_output = ob_get_clean();
    if ($ob_output) {
        // FPDF used die() and output an error message
        error_log("Proforma PDF error output: " . $ob_output);
        $pdf_data = null;
    }
} catch (\Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    error_log("Proforma PDF generation FAILED for {$proforma_number}: " . $e->getMessage());
    $pdf_data = null;
}

$mailer = new SmtpMailer();
if ($pdf_data) {
    $email_sent = $mailer->sendMailWithAttachment($contact_email, $subject, $email_body, $pdf_data, $pdf_filename, 'application/pdf');
} else {
    // Fallback: send email without PDF attachment
    $email_sent = $mailer->sendMail($contact_email, $subject, $email_body);
}

// Always log SMTP conversation for proforma
error_log("Proforma {$proforma_number} email to {$contact_email}: " . ($email_sent ? 'OK' : 'FAILED') . "\nSMTP Log:\n" . $mailer->getLog());

// Also notify staff
$staff_subject = "New Proforma Request: {$proforma_number} - {$contact_name}";
$staff_body = "<p>New proforma invoice <strong>{$proforma_number}</strong> requested by <strong>{$contact_name}</strong> ({$contact_email}).</p>";
$staff_body .= "<p>Plan: {$plan_label} | Amount: {$amount_fmt}</p>";
$staff_body .= "<p><a href='" . SITE_URL . "/staff/proforma-detail.php?id={$pid}'>View in Staff Portal</a></p>";
$mailer2 = new SmtpMailer();
$staff_sent = $mailer2->sendMail(SUPPORT_EMAIL, $staff_subject, $staff_body);

echo json_encode([
    'ok' => true,
    'proforma_number' => $proforma_number,
    'email_sent' => $email_sent,
    'staff_email_sent' => $staff_sent,
    'message' => $email_sent
        ? 'Proforma invoice sent to ' . $contact_email
        : 'Proforma created but email delivery may be delayed.'
]);
