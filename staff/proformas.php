<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';

$page_title = "Proforma Invoices";
include __DIR__ . '/includes/staff_header.php';

$status_filter = $_GET['status'] ?? 'all';
$where = [];
$params = [];
if ($status_filter !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $status_filter;
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';

$proformas = [];
try {
    $proformas = db_fetch_all("
        SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM proforma_invoices p
        LEFT JOIN users u ON p.user_id = u.id
        $where_clause
        ORDER BY p.created_at DESC
    ", $params);
} catch (Exception $e) {}
?>

<div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
    <h2 style="margin: 0;">💰 Proforma Invoices</h2>
</div>

<div class="glass-card table-card">
    <div class="card-header">
        <form method="GET" style="display: flex; gap: 10px;">
            <select name="status" style="padding: 0.5rem; border-radius: 4px; border: 1px solid var(--border-glass); background: var(--bg-glass); color: var(--text-main);">
                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="sent" <?= $status_filter == 'sent' ? 'selected' : '' ?>>📨 Sent</option>
                <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>✅ Paid</option>
                <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Proof</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proformas as $p): ?>
                <?php
                    $status_badges = [
                        'sent' => ['📨 Sent', 'warning'],
                        'paid' => ['✅ Paid', 'success'],
                        'cancelled' => ['❌ Cancelled', 'secondary'],
                    ];
                    $sb = $status_badges[$p['status']] ?? ['Unknown', 'secondary'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['proforma_number']) ?></strong></td>
                    <td>
                        <?= htmlspecialchars($p['contact_name']) ?>
                        <?php if ($p['company_name']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($p['company_name']) ?></small>
                        <?php endif; ?>
                        <br><small class="text-muted"><?= htmlspecialchars($p['contact_email']) ?></small>
                    </td>
                    <td><?= badge_html(ucfirst($p['plan_slug']), 'info') ?></td>
                    <td>€<?= number_format($p['amount'], 2, ',', '.') ?></td>
                    <td><?= badge_html($sb[0], $sb[1]) ?></td>
                    <td><?= $p['payment_proof_path'] ? '📎' : '—' ?></td>
                    <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
                    <td>
                        <a href="/staff/proforma-detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">👁️ View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($proformas)): ?>
                <tr><td colspan="8" class="text-center">No proforma invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/staff_footer.php'; ?>
