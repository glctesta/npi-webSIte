<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * license-requests.php — Gestione richieste licenza dal programma client.
 */
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';

$status_filter = $_GET['status'] ?? 'pending';
$valid_statuses = ['all', 'pending', 'in_progress', 'completed', 'rejected'];
if (!in_array($status_filter, $valid_statuses)) $status_filter = 'pending';

// Aggiorna stato richiesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    if (!staff_csrf_ok()) { http_response_code(400); die('CSRF token validation failed.'); }
    $req_id = (int)$_POST['request_id'];
    $new_status = $_POST['new_status'] ?? '';
    $staff_notes = trim($_POST['staff_notes'] ?? '');

    if (in_array($new_status, ['pending', 'in_progress', 'completed', 'rejected'])) {
        db_query(
            "UPDATE license_requests SET status = ?, staff_notes = ? WHERE id = ?",
            [$new_status, $staff_notes ?: null, $req_id]
        );
        flash("Request #{$req_id} updated to {$new_status}.", 'success');
    }
    header('Location: license-requests.php?status=' . $status_filter);
    exit;
}

// Query richieste
$where = $status_filter !== 'all' ? "WHERE lr.status = ?" : "WHERE 1=1";
$params = $status_filter !== 'all' ? [$status_filter] : [];

try {
    $requests = db_fetch_all(
        "SELECT lr.*
         FROM license_requests lr
         {$where}
         ORDER BY lr.created_at DESC
         LIMIT 100",
        $params
    );
} catch (Exception $e) {
    $requests = [];
}

// Conteggio per badge
$counts = [];
try {
    $count_rows = db_fetch_all("SELECT status, COUNT(*) as cnt FROM license_requests GROUP BY status");
    foreach ($count_rows as $r) $counts[$r['status']] = (int)$r['cnt'];
} catch (Exception $e) {}
$pending_count = $counts['pending'] ?? 0;

$type_labels = [
    'upgrade'   => ['⬆️ Upgrade', 'primary'],
    'renew'     => ['🔄 Rinnovo', 'success'],
    'add_seats' => ['💻 Postazioni', 'info'],
    'support'   => ['🆘 Supporto', 'warning'],
];

$status_badges = [
    'pending'     => ['In attesa', 'warning'],
    'in_progress' => ['In lavorazione', 'info'],
    'completed'   => ['Completata', 'success'],
    'rejected'    => ['Rifiutata', 'danger'],
];

require_once __DIR__ . '/includes/staff_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clipboard-check me-2"></i>License Requests</h2>
    <span class="badge bg-warning fs-6"><?= $pending_count ?> pending</span>
</div>

<!-- Filtri stato -->
<div class="btn-group mb-3">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $val => $label): ?>
    <a href="?status=<?= $val ?>"
       class="btn btn-sm <?= $status_filter === $val ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <?= $label ?>
        <?php if (isset($counts[$val])): ?>
            <span class="badge bg-light text-dark ms-1"><?= $counts[$val] ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($requests)): ?>
<div class="alert alert-info">No requests found.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Current Plan</th>
            <th>Desired</th>
            <th>Machine</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($requests as $r):
        $tl = $type_labels[$r['request_type']] ?? [$r['request_type'], 'secondary'];
        $sb = $status_badges[$r['status']] ?? [$r['status'], 'secondary'];
        $customer_name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td class="small"><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
            <td>
                <?php if ($customer_name): ?>
                    <strong><?= htmlspecialchars($customer_name) ?></strong><br>
                <?php endif; ?>
                <small class="text-muted"><?= htmlspecialchars($r['user_email']) ?></small>
                <?php if ($r['company']): ?>
                    <br><small class="text-info"><?= htmlspecialchars($r['company']) ?></small>
                <?php endif; ?>
            </td>
            <td><span class="badge bg-<?= $tl[1] ?>"><?= $tl[0] ?></span></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($r['current_plan'] ?? '—') ?></span></td>
            <td>
                <?php if ($r['desired_plan']): ?>
                    <span class="badge bg-primary"><?= htmlspecialchars($r['desired_plan']) ?></span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td>
                <small title="<?= htmlspecialchars($r['machine_id']) ?>">
                    <?= htmlspecialchars($r['hostname'] ?: substr($r['machine_id'], 0, 12) . '…') ?>
                </small>
            </td>
            <td><span class="badge bg-<?= $sb[1] ?>"><?= $sb[0] ?></span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#modal-<?= $r['id'] ?>">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>

        <!-- Modal dettaglio -->
        <div class="modal fade" id="modal-<?= $r['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Request #<?= $r['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm">
                        <tr><td class="text-muted">Email</td><td><?= htmlspecialchars($r['user_email']) ?></td></tr>
                        <tr><td class="text-muted">Type</td><td><span class="badge bg-<?= $tl[1] ?>"><?= $tl[0] ?></span></td></tr>
                        <tr><td class="text-muted">Current</td><td><?= htmlspecialchars($r['current_plan'] ?? '—') ?></td></tr>
                        <?php if ($r['desired_plan']): ?>
                        <tr><td class="text-muted">Desired</td><td><strong><?= htmlspecialchars($r['desired_plan']) ?></strong></td></tr>
                        <?php endif; ?>
                        <tr><td class="text-muted">Machine</td><td><code class="small"><?= htmlspecialchars($r['machine_id']) ?></code></td></tr>
                        <tr><td class="text-muted">Hostname</td><td><?= htmlspecialchars($r['hostname'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted">Date</td><td><?= $r['created_at'] ?></td></tr>
                        <?php if ($r['license_id']): ?>
                        <tr><td class="text-muted">License</td><td><a href="licenses/edit.php?id=<?= $r['license_id'] ?>">#<?= $r['license_id'] ?></a></td></tr>
                        <?php endif; ?>
                    </table>

                    <?php if ($r['message']): ?>
                    <div class="alert alert-light border-start border-4 border-primary">
                        <small class="text-muted d-block mb-1">Customer message:</small>
                        <?= nl2br(htmlspecialchars($r['message'])) ?>
                    </div>
                    <?php endif; ?>

                    <input type="hidden" name="update_request" value="1">
                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="new_status" class="form-select">
                            <?php foreach ($status_badges as $sv => $sl): ?>
                            <option value="<?= $sv ?>" <?= $r['status'] === $sv ? 'selected' : '' ?>><?= $sl[0] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Staff Notes</label>
                        <textarea name="staff_notes" class="form-control" rows="3"><?= htmlspecialchars($r['staff_notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
              </form>
            </div>
          </div>
        </div>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/staff_footer.php'; ?>
