<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';

$page_title = "Dashboard";
include __DIR__ . '/includes/staff_header.php';

$stats = get_dashboard_stats();
$license_stats = get_license_stats();
$expiring_licenses = check_expiring_licenses();
$recent_users = get_recent_users(10);
$recent_demos = get_recent_demo_requests(10);
?>

<?php if (!empty($expiring_licenses)): ?>
<div class="staff-alert staff-alert-warning">
    ⚠️ <strong>Alert:</strong> There are <?= count($expiring_licenses) ?> licenses expiring within 30 days.
    <a href="/staff/licenses/?status=active" style="color: inherit; margin-left: 0.5rem;">View →</a>
</div>
<?php endif; ?>

<?php
$pending_requests = 0;
try { $pr = db_fetch("SELECT COUNT(*) as cnt FROM license_requests WHERE status = 'pending'"); $pending_requests = (int)($pr['cnt'] ?? 0); } catch (Exception $e) {}
if ($pending_requests > 0): ?>
<div class="staff-alert staff-alert-info" style="border-left: 4px solid #6366f1;">
    📋 <strong><?= $pending_requests ?> license request<?= $pending_requests > 1 ? 's' : '' ?> pending.</strong>
    Customers are waiting for a response.
    <a href="/staff/license-requests.php" style="color: inherit; margin-left: 0.5rem; font-weight: 600;">Review Requests →</a>
</div>
<?php endif; ?>

<div class="welcome-banner">
    <h1>Welcome back, <?= htmlspecialchars($_SESSION['staff_name'] ?? '') ?>! 👋</h1>
    <p>Here's what's happening with the GTMC-NPI system today.</p>
</div>

<!-- Stats Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="glass-card stat-card">
        <div class="stat-icon stat-info">👥</div>
        <div class="stat-details">
            <span class="stat-value"><?= number_format($stats['total_users']) ?></span>
            <span class="stat-label">Total Users</span>
        </div>
    </div>
    
    <div class="glass-card stat-card">
        <div class="stat-icon stat-success">✅</div>
        <div class="stat-details">
            <span class="stat-value"><?= number_format($stats['verified_users']) ?></span>
            <span class="stat-label">Verified Users</span>
        </div>
    </div>
    
    <a href="/staff/licenses/?status=active" class="glass-card stat-card" style="text-decoration:none; color:inherit;">
        <div class="stat-icon stat-success">🔑</div>
        <div class="stat-details">
            <span class="stat-value"><?= number_format($license_stats['active']) ?></span>
            <span class="stat-label">Active Licenses</span>
        </div>
    </a>
    
    <a href="/staff/licenses/" class="glass-card stat-card" style="text-decoration:none; color:inherit;">
        <div class="stat-icon stat-warning">🔑</div>
        <div class="stat-details">
            <span class="stat-value"><?= number_format($license_stats['expiring_soon']) ?></span>
            <span class="stat-label">Expiring (30d)</span>
        </div>
    </a>
    
    <div class="glass-card stat-card">
        <div class="stat-icon stat-primary">📝</div>
        <div class="stat-details">
            <span class="stat-value"><?= number_format($stats['pending_demos']) ?></span>
            <span class="stat-label">Pending Demos</span>
        </div>
    </div>
    
    <div class="glass-card stat-card">
        <div class="stat-icon stat-info">📥</div>
        <div class="stat-details">
            <span class="stat-value"><?= number_format($stats['total_downloads']) ?></span>
            <span class="stat-label">Downloads</span>
        </div>
    </div>
</div>

<div class="staff-grid-2">
    <!-- Recent Users -->
    <div class="glass-card table-card">
        <div class="card-header">
            <h3>Recent Users</h3>
        </div>
        <div class="table-responsive">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Verified</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_users)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No recent users</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($recent_users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= badge_html($u['email_verified'] ? 'Yes' : 'No', $u['email_verified'] ? 'success' : 'warning') ?></td>
                            <td><?= time_ago($u['created_at'] ?? date('Y-m-d H:i:s')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Demo Requests -->
    <div class="glass-card table-card">
        <div class="card-header">
            <h3>Recent Demo Requests</h3>
        </div>
        <div class="table-responsive">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>CUI / VAT</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Verified</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_demos)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No recent demo requests</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($recent_demos as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['company'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($d['vat'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($d['phone'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($d['email']) ?></td>
                            <td><?= !empty($d['email_verified']) ? '✅' : '—' ?></td>
                            <td><?= badge_html(ucfirst($d['status'] ?? 'pending'), ($d['status'] ?? 'pending') === 'pending' ? 'warning' : 'success') ?></td>
                            <td><?= time_ago($d['created_at'] ?? date('Y-m-d H:i:s')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/staff_footer.php'; ?>
