<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

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

$input = json_decode(file_get_contents('php://input'), true);
$plan_slug = $input['plan'] ?? '';
$success_url = $input['success_url'] ?? SITE_URL . '/portal/licenses.php';
$cancel_url = $input['cancel_url'] ?? SITE_URL . '/pricing.php';

if (!in_array($plan_slug, ['basic', 'professional', 'enterprise'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid plan']);
    exit;
}

$plan = db_fetch("SELECT * FROM plans WHERE slug = ? AND is_active = 1", [$plan_slug]);
if (!$plan) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Plan not found']);
    exit;
}

$stripe_secret = env('STRIPE_SECRET_KEY');
if (!$stripe_secret) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Stripe is not configured']);
    exit;
}

// Stripe Checkout integration is a placeholder: in production this would call Stripe's API.
$stripe_price_id = env('STRIPE_PRICE_' . strtoupper($plan_slug));
if (!$stripe_price_id) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Stripe price ID not configured for this plan']);
    exit;
}

// Placeholder response: real implementation would create a Stripe CheckoutSession
// and return the session id / checkout URL.
$checkout_id = 'cs_placeholder_' . bin2hex(random_bytes(8));
$checkout_url = SITE_URL . '/api/payments/stripe-return.php?session_id=' . $checkout_id;

echo json_encode([
    'ok' => true,
    'checkout_id' => $checkout_id,
    'checkout_url' => $checkout_url,
    'plan' => $plan_slug,
    'amount_eur' => $plan['price_eur'],
    'note' => 'This is a placeholder checkout. Replace with real Stripe CheckoutSession creation.'
]);
