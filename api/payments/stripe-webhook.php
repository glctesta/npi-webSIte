<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$secret = env('STRIPE_WEBHOOK_SECRET');

// Placeholder: in production, verify the signature with Stripe library.
if ($secret) {
    // \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
}

$event = json_decode($payload, true);
$type = $event['type'] ?? 'unknown';

try {
    if ($type === 'checkout.session.completed') {
        $session = $event['data']['object'] ?? [];
        $customer_email = $session['customer_email'] ?? $session['customer_details']['email'] ?? null;
        $amount_total = $session['amount_total'] ?? 0; // cents
        $currency = strtoupper($session['currency'] ?? 'eur');
        $subscription_id = $session['subscription'] ?? null;
        $metadata = $session['metadata'] ?? [];
        $plan_slug = $metadata['plan'] ?? null;
        $user_id = $metadata['user_id'] ?? null;

        if ($user_id && $plan_slug) {
            $plan = db_fetch("SELECT id FROM plans WHERE slug = ?", [$plan_slug]);
            if ($plan) {
                $amount_eur = $amount_total / 100;
                $license_id = db_insert(
                    "INSERT INTO licenses (user_id, plan_id, status, starts_at, expires_at, stripe_subscription_id) VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), ?)",
                    [$user_id, $plan['id'], $subscription_id]
                );

                $invoice_id = db_insert(
                    "INSERT INTO invoices (user_id, license_id, amount, currency, status, stripe_invoice_id, paid_at) VALUES (?, ?, ?, ?, 'paid', ?, NOW())",
                    [$user_id, $license_id, $amount_eur, $currency, $session['invoice'] ?? null]
                );

                db_insert(
                    "INSERT INTO payments (user_id, license_id, invoice_id, amount, currency, provider, provider_transaction_id, status) VALUES (?, ?, ?, ?, ?, 'stripe', ?, 'succeeded')",
                    [$user_id, $license_id, $invoice_id, $amount_eur, $currency, $session['id'] ?? null]
                );
            }
        }
    }

    // Log the event for monitoring
    error_log("Stripe webhook received: " . $type);
} catch (Exception $e) {
    error_log("Stripe webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Webhook processing error']);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true, 'received' => true, 'type' => $type]);
