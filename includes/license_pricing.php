<?php
require_once __DIR__ . '/db.php';

function pricing_defaults() {
    // Modello GTMC-NPI: una licenza per installazione (server intranet).
    // Professional = una società, senza IATF/ponte clienti/NRE;
    // Enterprise = multi-società, profilo IATF, ponte clienti, NRE, API.
    // max_projects/max_users NULL = illimitati.
    return [
        'trial' => [
            'tier' => 'trial',
            'price_eur' => 0.00,
            'currency' => 'EUR',
            'billing_period' => 'trial',
            'max_projects' => 3,
            'max_users' => 5,
            'max_activations' => 1,
            'sort_order' => 0,
        ],
        'professional' => [
            'tier' => 'professional',
            'price_eur' => 990.00,
            'currency' => 'EUR',
            'billing_period' => 'year',
            'max_projects' => 25,
            'max_users' => 15,
            'max_activations' => 3,
            'sort_order' => 20,
        ],
        'enterprise' => [
            'tier' => 'enterprise',
            'price_eur' => 1990.00,
            'currency' => 'EUR',
            'billing_period' => 'year',
            'max_projects' => null,
            'max_users' => null,
            'max_activations' => 5,
            'sort_order' => 30,
        ],
    ];
}

function get_license_prices() {
    $prices = pricing_defaults();

    try {
        // Try with products JOIN first
        $rows = db_fetch_all("SELECT p.slug, p.price_eur, p.billing_period, p.sort_order, p.features,
                                     p.max_projects, p.max_users, p.max_activations
                              FROM plans p
                              JOIN products pr ON pr.id = p.product_id
                              WHERE pr.slug = 'npi' AND p.is_active = 1
                              ORDER BY p.sort_order ASC");

        // Fallback: if JOIN returned nothing, read plans directly
        if (empty($rows)) {
            $rows = db_fetch_all("SELECT slug, price_eur, billing_period, sort_order, features,
                                         max_projects, max_users, max_activations
                                  FROM plans
                                  WHERE is_active = 1 AND slug IN ('trial','professional','enterprise')
                                  ORDER BY sort_order ASC");
        }

        foreach ($rows as $r) {
            $tier = $r['slug'];
            if (!isset($prices[$tier])) {
                continue;
            }
            $prices[$tier]['price_eur'] = (float)$r['price_eur'];
            $prices[$tier]['currency'] = 'EUR';
            $prices[$tier]['billing_period'] = $r['billing_period'];
            $prices[$tier]['sort_order'] = (int)$r['sort_order'];
            $prices[$tier]['features'] = $r['features'] ? json_decode($r['features'], true) : [];
            // NULL = illimitato; colonne assenti su DB non migrati -> default
            $prices[$tier]['max_projects'] = array_key_exists('max_projects', $r) && $r['max_projects'] !== null ? (int)$r['max_projects'] : (pricing_defaults()[$tier]['max_projects'] ?? null);
            $prices[$tier]['max_users'] = array_key_exists('max_users', $r) && $r['max_users'] !== null ? (int)$r['max_users'] : (pricing_defaults()[$tier]['max_users'] ?? null);
            $prices[$tier]['max_activations'] = array_key_exists('max_activations', $r) && $r['max_activations'] !== null ? (int)$r['max_activations'] : (pricing_defaults()[$tier]['max_activations'] ?? null);
        }
    } catch (Exception $e) {
        // Plans table may not exist yet; use hard-coded defaults
        error_log('get_license_prices: ' . $e->getMessage());
    }

    uasort($prices, function ($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });

    return $prices;
}

function update_license_prices($newPrices, $staffId = null) {
    foreach (['trial', 'professional', 'enterprise'] as $tier) {
        if (!array_key_exists($tier, $newPrices)) {
            continue;
        }
        $price = (float)$newPrices[$tier];
        if ($price < 0) {
            $price = 0;
        }

        // 0 o omesso = illimitato (NULL)
        $maxProjects = (int)($newPrices['limits'][$tier]['max_projects'] ?? 0);
        $maxUsers    = (int)($newPrices['limits'][$tier]['max_users'] ?? 0);
        $maxAct      = max(1, (int)($newPrices['limits'][$tier]['max_activations'] ?? 1));

        // Try with products JOIN first
        $plan = db_fetch(
            "SELECT p.id FROM plans p
             JOIN products pr ON pr.id = p.product_id
             WHERE pr.slug = 'npi' AND p.slug = ?",
            [$tier]
        );

        // Fallback: find plan directly by slug
        if (!$plan) {
            $plan = db_fetch("SELECT id FROM plans WHERE slug = ? AND is_active = 1", [$tier]);
        }

        if ($plan) {
            db_query("UPDATE plans SET price_eur = ?, max_projects = ?, max_users = ?, max_activations = ? WHERE id = ?",
                [$price, $maxProjects > 0 ? $maxProjects : null, $maxUsers > 0 ? $maxUsers : null, $maxAct, $plan['id']]);
        }
    }
}

function get_plan_by_slug($slug) {
    $plan = db_fetch(
        "SELECT p.* FROM plans p
         JOIN products pr ON pr.id = p.product_id
         WHERE pr.slug = 'npi' AND p.slug = ? AND p.is_active = 1",
        [$slug]
    );
    // Fallback: find plan directly
    if (!$plan) {
        $plan = db_fetch("SELECT * FROM plans WHERE slug = ? AND is_active = 1", [$slug]);
    }
    return $plan;
}
