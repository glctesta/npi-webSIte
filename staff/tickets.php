<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_role(['admin', 'manager']);
require_once __DIR__ . '/includes/staff_functions.php';

$page_title = "Tickets";

$status_filter = $_GET['status'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ticket'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        flash('Invalid CSRF token.', 'error');
        header("Location: /staff/tickets.php?status=" . urlencode($status_filter));
        exit;
    }
    
    $ticket_id = (int)$_POST['ticket_id'];
    $staff_id = (int)$_POST['staff_id'] ?: null;
    
    db_query("UPDATE tickets SET assigned_staff_id = ? WHERE id = ?", [$staff_id, $ticket_id]);
    flash('Ticket assigned successfully.', 'success');
    header("Location: /staff/tickets.php?status=" . urlencode($status_filter));
    exit;
}

$where = "1=1";
$params = [];
if ($status_filter !== 'all') {
    $where .= " AND t.status = ?";
    $params[] = $status_filter;
}

$tickets = db_fetch_all(
    "SELECT t.*, s.full_name as assigned_name 
     FROM tickets t 
     LEFT JOIN staff s ON t.assigned_staff_id = s.id 
     WHERE $where 
     ORDER BY t.created_at DESC",
    $params
);

$staff_members = db_fetch_all("SELECT id, full_name AS name FROM staff WHERE role IN ('admin', 'manager')");

$stats = [
    'open' => db_fetch("SELECT COUNT(*) as c FROM tickets WHERE status = 'open'")['c'] ?? 0,
    'critical' => db_fetch("SELECT COUNT(*) as c FROM tickets WHERE priority = 'critical'")['c'] ?? 0,
    'resolved_today' => db_fetch("SELECT COUNT(*) as c FROM tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()")['c'] ?? 0,
];

include __DIR__ . '/includes/staff_header.php';
?>

<div class="welcome-banner">
    <h1>Tickets</h1>
    <p>Manage customer support tickets.</p>
</div>

<div style="display:flex; gap:1rem; margin-bottom: 2rem;">
    <div class="glass-card stat-card" style="flex:1; padding:1.5rem; text-align:center;">
        <h3>Open Tickets</h3>
        <div style="font-size:2rem; font-weight:bold; color:var(--warning-color);"><?= $stats['open'] ?></div>
    </div>
    <div class="glass-card stat-card" style="flex:1; padding:1.5rem; text-align:center;">
        <h3>Critical Tickets</h3>
        <div style="font-size:2rem; font-weight:bold; color:var(--danger-color);"><?= $stats['critical'] ?></div>
    </div>
    <div class="glass-card stat-card" style="flex:1; padding:1.5rem; text-align:center;">
        <h3>Resolved Today</h3>
        <div style="font-size:2rem; font-weight:bold; color:var(--success-color);"><?= $stats['resolved_today'] ?></div>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div style="margin-bottom:1rem; display:flex; gap:0.5rem;">
        <?php foreach (['all', 'open', 'in_progress', 'resolved', 'closed'] as $s): ?>
            <a href="?status=<?= $s ?>" class="btn <?= $status_filter === $s ? 'btn-primary' : 'btn-outline' ?>">
                <?= ucfirst(str_replace('_', ' ', $s)) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Subject</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="8" style="text-align:center;">No tickets found.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['ticket_code']) ?></strong></td>
                        <td><?= date('Y-m-d H:i', strtotime($t['created_at'])) ?></td>
                        <td>
                            <div><?= htmlspecialchars($t['contact_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($t['company'] ?: '-') ?></small>
                        </td>
                        <td><?= htmlspecialchars($t['subject']) ?></td>
                        <td>
                            <span class="badge badge-<?= $t['priority'] === 'critical' ? 'danger' : ($t['priority'] === 'high' ? 'warning' : 'info') ?>">
                                <?= htmlspecialchars(ucfirst($t['priority'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $t['status'] === 'open' ? 'warning' : ($t['status'] === 'closed' ? 'secondary' : 'primary') ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $t['status']))) ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; gap:0.5rem;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="assign_ticket" value="1">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <select name="staff_id" class="form-control" style="width:120px; font-size:0.875rem;" onchange="this.form.submit()">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staff_members as $sm): ?>
                                        <option value="<?= $sm['id'] ?>" <?= $t['assigned_staff_id'] == $sm['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sm['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <a href="ticket-detail.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/staff_footer.php'; ?>
