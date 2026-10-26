<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_role(['admin', 'manager', 'sales']);
require_once __DIR__ . '/includes/staff_functions.php';
require_once __DIR__ . '/../includes/license_pricing.php';

$page_title = "License Pricing";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        flash('Invalid CSRF token.', 'error');
        header('Location: /staff/pricing.php');
        exit;
    }

    $newPrices = [];
    $newPrices['limits'] = [];
    foreach (['trial', 'professional', 'enterprise'] as $tier) {
        $raw = str_replace(',', '.', trim($_POST['price_' . $tier] ?? '0'));
        if (!is_numeric($raw)) {
            flash('Invalid value for ' . ucfirst($tier) . ' price.', 'error');
            header('Location: /staff/pricing.php');
            exit;
        }
        $newPrices[$tier] = max(0, (float)$raw);

        // Limiti: 0 o vuoto = illimitato
        $newPrices['limits'][$tier] = [
            'max_projects'    => max(0, (int)($_POST['max_projects_' . $tier] ?? 0)),
            'max_users'       => max(0, (int)($_POST['max_users_' . $tier] ?? 0)),
            'max_activations' => max(1, (int)($_POST['max_stations_' . $tier] ?? 1)),
        ];
    }

    try {
        update_license_prices($newPrices, $_SESSION['staff_id'] ?? null);
        flash('Pricing updated successfully.', 'success');
    } catch (Exception $e) {
        flash('Error updating pricing: ' . $e->getMessage(), 'error');
    }

    header('Location: /staff/pricing.php');
    exit;
}

$prices = get_license_prices();
include __DIR__ . '/includes/staff_header.php';
?>

<div class="welcome-banner">
    <h1>License Pricing</h1>
    <p>Manage prices and plan limits (active projects, app users, installations) for Trial, Professional and Enterprise. 0 = unlimited.</p>
</div>

<form method="POST" class="glass-card" style="padding:1.5rem; max-width:980px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>License</th>
                    <th>Price (EUR)</th>
                    <th>Billing</th>
                    <th>Max projects<br><small style="font-weight:400;">active, 0 = unlimited</small></th>
                    <th>Max users<br><small style="font-weight:400;">0 = unlimited</small></th>
                    <th>Installations<br><small style="font-weight:400;">included</small></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['trial', 'professional', 'enterprise'] as $tier): ?>
                    <?php $row = $prices[$tier] ?? ['price_eur' => 0, 'billing_period' => 'year', 'max_projects' => 0, 'max_users' => 0, 'max_activations' => 1]; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(ucfirst($tier)) ?></strong></td>
                        <td style="max-width:150px;">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="price_<?= htmlspecialchars($tier) ?>"
                                value="<?= htmlspecialchars(number_format((float)$row['price_eur'], 2, '.', '')) ?>"
                                class="form-control"
                                required
                            >
                        </td>
                        <td>/<?= htmlspecialchars($row['billing_period']) ?></td>
                        <td style="max-width:110px;">
                            <input type="number" min="0" name="max_projects_<?= htmlspecialchars($tier) ?>"
                                   value="<?= (int)($row['max_projects'] ?? 0) ?>" class="form-control">
                        </td>
                        <td style="max-width:110px;">
                            <input type="number" min="0" name="max_users_<?= htmlspecialchars($tier) ?>"
                                   value="<?= (int)($row['max_users'] ?? 0) ?>" class="form-control">
                        </td>
                        <td style="max-width:110px;">
                            <input type="number" min="1" max="500" name="max_stations_<?= htmlspecialchars($tier) ?>"
                                   value="<?= (int)($row['max_activations'] ?? 1) ?>" class="form-control">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem; display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary">Save Pricing</button>
        <span class="text-muted">Changes are immediately visible on the public pricing page and pre-filled when issuing new licenses.</span>
    </div>

    <p style="margin-top:1rem; color:var(--text-muted); font-size:.9rem;">
        💻 Extra installations beyond the plan are sold separately: raise <code>max_activations</code> on the license
        in <a href="/staff/licenses/" style="color:var(--accent-primary);">Licenses</a> → Edit (every change is audit-logged).
    </p>
</form>

<?php include __DIR__ . '/includes/staff_footer.php'; ?>
