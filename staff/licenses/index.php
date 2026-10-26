<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/../includes/staff_functions.php';

$page_title = "Licenses";
include __DIR__ . '/../includes/staff_header.php';

// Filters
$status_filter = $_GET['status'] ?? 'all';
$tier_filter = $_GET['tier'] ?? 'all';
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($status_filter !== 'all') {
    $where[] = "l.status = ?";
    $params[] = $status_filter;
}
if ($tier_filter !== 'all') {
    $where[] = "p.slug = ?";
    $params[] = $tier_filter;
}
if ($q !== '') {
    $where[] = "(l.license_key LIKE ? OR l.company_name LIKE ? OR u.company LIKE ? OR u.email LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

$where_clause = '';
if (!empty($where)) {
    $where_clause = "WHERE " . implode(' AND ', $where);
}

$query = "
    SELECT l.*, p.slug as tier, p.name as plan_name,
           CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email as customer_email, u.company as customer_company,
           (SELECT COUNT(*) FROM license_activations la WHERE la.license_id = l.id AND la.is_active = 1) as activation_count
    FROM licenses l
    LEFT JOIN users u ON l.user_id = u.id
    JOIN plans p ON l.plan_id = p.id
    $where_clause
    ORDER BY l.created_at DESC
";
$licenses = [];
try {
    $licenses = db_fetch_all($query, $params);
} catch (Exception $e) {}
$sel = 'padding: 0.5rem; border-radius: 4px; border: 1px solid var(--border-glass); background: var(--bg-glass); color: var(--text-main);';
?>
<div style="margin-bottom: 1.5rem; display:flex; gap:.5rem;">
    <a href="/staff/licenses/create.php" class="btn btn-primary">➕ Create New License</a>
    <a href="/staff/licenses/offline.php" class="btn btn-secondary">📴 Offline token</a>
    <a href="/staff/licenses/audit.php" class="btn btn-secondary">🧾 Audit log</a>
</div>

<div class="glass-card table-card">
    <div class="card-header">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Key, company or email" style="<?= $sel ?>">
            <select name="status" style="<?= $sel ?>">
                <?php foreach (['all' => 'All Statuses', 'active' => 'Active', 'expired' => 'Expired', 'revoked' => 'Revoked', 'suspended' => 'Suspended'] as $v => $label): ?>
                <option value="<?= $v ?>" <?= $status_filter === $v ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tier" style="<?= $sel ?>">
                <?php foreach (['all' => 'All Tiers', 'trial' => 'Trial', 'basic' => 'Basic', 'professional' => 'Professional', 'enterprise' => 'Enterprise'] as $v => $label): ?>
                <option value="<?= $v ?>" <?= $tier_filter === $v ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Key / Company</th>
                    <th>Tier</th>
                    <th>Status</th>
                    <th>Valid until</th>
                    <th>Days left</th>
                    <th>Machines</th>
                    <th>Last check</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($licenses as $l): ?>
                <?php
                    $days_left = $l['expires_at'] ? (strtotime($l['expires_at']) - time()) / 86400 : INF;
                    if ($l['status'] === 'revoked') {
                        $status_color = 'secondary';
                    } elseif ($l['status'] === 'suspended') {
                        $status_color = 'warning';
                    } elseif ($days_left < 0) {
                        $status_color = 'danger';
                    } elseif ($days_left < 30) {
                        $status_color = 'warning';
                    } else {
                        $status_color = 'success';
                    }

                    $tier_colors = ['trial' => 'secondary', 'basic' => 'info', 'professional' => 'primary', 'enterprise' => 'success'];
                    $tier_color = $tier_colors[$l['tier']] ?? 'info';
                    $company = $l['company_name'] ?: ($l['customer_company'] ?: trim((string)$l['customer_name']));
                ?>
                <tr>
                    <td><?= (int)$l['id'] ?></td>
                    <td>
                        <code><?= htmlspecialchars((string)$l['license_key']) ?></code><br>
                        <?= htmlspecialchars((string)$company) ?>
                        <small class="text-muted"><?= htmlspecialchars((string)$l['customer_email']) ?></small>
                    </td>
                    <td><?= badge_html(ucfirst($l['tier']), $tier_color) ?></td>
                    <td><?= badge_html($l['status'] === 'active' && $days_left < 0 ? 'Expired' : ucfirst($l['status']), $status_color) ?></td>
                    <td><?= $l['expires_at'] ? date('Y-m-d', strtotime($l['expires_at'])) : '—' ?></td>
                    <td><?= is_infinite($days_left) ? '∞' : ($days_left < 0 ? 0 : floor($days_left)) ?></td>
                    <td style="text-align:center;"><?= (int)$l['activation_count'] ?> / <?= (int)$l['max_activations'] ?></td>
                    <td>
                        <?= $l['last_check'] ? htmlspecialchars(time_ago($l['last_check'])) : '—' ?>
                        <?php if (!empty($l['app_version'])): ?><br><small class="text-muted">v<?= htmlspecialchars($l['app_version']) ?><?= $l['reported_projects'] !== null ? ' · ' . (int)$l['reported_projects'] . ' proj.' : '' ?></small><?php endif; ?>
                    </td>
                    <td>
                        <a href="/staff/licenses/edit.php?id=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline">✏️ Edit</a>
                        <?php if ($l['status'] !== 'revoked'): ?>
                        <form action="/staff/licenses/revoke.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to revoke this license?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #ef4444;">🚫 Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($licenses)): ?>
                <tr>
                    <td colspan="9" class="text-center">No licenses found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/staff_footer.php'; ?>
