<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/../includes/staff_functions.php';

$page_title = "License audit";
include __DIR__ . '/../includes/staff_header.php';

$license_id = (int)($_GET['license_id'] ?? 0);
$action_f   = trim($_GET['action'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 100;

$where = [];
$params = [];
if ($license_id) { $where[] = 'a.license_id = ?'; $params[] = $license_id; }
if ($action_f !== '') { $where[] = 'a.action LIKE ?'; $params[] = $action_f . '%'; }
$wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = [];
$total = 0;
try {
    $total = (int)(db_fetch("SELECT COUNT(*) AS c FROM license_audit a $wc", $params)['c'] ?? 0);
    $rows = db_fetch_all("SELECT a.*, COALESCE(l.company_name, u.company) AS company
                          FROM license_audit a
                          LEFT JOIN licenses l ON l.id = a.license_id
                          LEFT JOIN users u ON u.id = l.user_id
                          $wc ORDER BY a.id DESC LIMIT $per_page OFFSET " . (($page - 1) * $per_page), $params);
} catch (Exception $e) {}
$pages = max(1, (int)ceil($total / $per_page));
$sel = 'padding: 0.5rem; border-radius: 4px; border: 1px solid var(--border-glass); background: var(--bg-glass); color: var(--text-main);';
?>
<div class="glass-card table-card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h3 style="margin:0;">🧾 License audit (<?= $total ?>)</h3>
        <form method="GET" style="display:flex; gap:10px;">
            <?php if ($license_id): ?><input type="hidden" name="license_id" value="<?= $license_id ?>"><?php endif; ?>
            <select name="action" style="<?= $sel ?>">
                <?php foreach (['' => 'All actions', 'activate' => 'activate', 'validate' => 'validate', 'deactivate' => 'deactivate', 'rejected' => 'rejected:*', 'admin' => 'admin:*'] as $v => $label): ?>
                <option value="<?= $v ?>" <?= $action_f === $v ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary">Filter</button>
            <?php if ($license_id): ?><a class="btn btn-outline" href="/staff/licenses/audit.php">All licenses</a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="staff-table">
            <thead><tr><th>When</th><th>License</th><th>Action</th><th>Fingerprint</th><th>IP</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td>
                        <?php if ($r['license_id']): ?><a href="/staff/licenses/edit.php?id=<?= (int)$r['license_id'] ?>"><?php endif; ?>
                        <code><?= htmlspecialchars((string)$r['license_key']) ?></code>
                        <?php if ($r['license_id']): ?></a><?php endif; ?>
                        <br><small class="text-muted"><?= htmlspecialchars((string)$r['company']) ?></small>
                    </td>
                    <td><?= badge_html($r['action'], str_starts_with($r['action'], 'rejected') ? 'danger' : (str_starts_with($r['action'], 'admin') ? 'warning' : 'info')) ?></td>
                    <td><code style="font-size:.7rem;"><?= htmlspecialchars(substr((string)$r['fingerprint'], 0, 16)) ?><?= $r['fingerprint'] ? '…' : '' ?></code></td>
                    <td><?= htmlspecialchars((string)$r['ip_address']) ?></td>
                    <td><?= htmlspecialchars((string)$r['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center">No audit entries.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div style="padding:1rem; display:flex; gap:.4rem; flex-wrap:wrap;">
        <?php for ($i = 1; $i <= $pages; $i++):
            $qs = http_build_query(array_filter(['license_id' => $license_id ?: null, 'action' => $action_f ?: null, 'page' => $i])); ?>
            <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>" href="?<?= htmlspecialchars($qs) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/staff_footer.php'; ?>
