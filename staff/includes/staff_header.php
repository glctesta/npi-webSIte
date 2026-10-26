<?php
// staff/includes/staff_header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GTMC-NPI Staff Portal</title>
    <link rel="icon" type="image/png" href="/static/images/favicon.png">
    <!-- Base styles from main site -->
    <link rel="stylesheet" href="/static/css/style.css">
    <!-- Staff specific styles -->
    <link rel="stylesheet" href="/staff/css/staff.css">
</head>
<body>
    <div class="staff-layout">
        <!-- Sidebar -->
        <aside class="staff-sidebar">
            <div class="sidebar-brand">
                <img src="/static/images/logo.png" alt="GTMC-NPI" style="height:30px; filter: invert(1);">
                <span>Staff Portal</span>
            </div>
            
            <nav class="sidebar-nav">
                <?php
                    $req = $_SERVER['REQUEST_URI'];
                    $is_dashboard  = ($req === '/staff/' || $req === '/staff/index.php');
                    $is_pricing    = strpos($req, '/staff/pricing.php') !== false;
                    $is_releases   = strpos($req, '/staff/releases.php') !== false;
                    $is_licenses   = strpos($req, '/staff/licenses') !== false;
                    $is_tickets    = strpos($req, '/staff/ticket') !== false;
                    $is_requests   = strpos($req, '/staff/license-requests') !== false;
                    $is_proformas  = strpos($req, '/staff/proforma') !== false;
                    
                    $open_count = 0;
                    $expiring_lic_count = 0;
                    $pending_req_count = 0;
                    $pending_proforma_count = 0;
                    if (function_exists('get_db')) {
                        try {
                            $stmt = get_db()->query("SELECT COUNT(*) as cnt FROM tickets WHERE status = 'open'");
                            $open_count = $stmt->fetchColumn();
                            $stmt2 = get_db()->query("SELECT COUNT(*) as cnt FROM licenses WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)");
                            $expiring_lic_count = $stmt2->fetchColumn();
                            $stmt3 = get_db()->query("SELECT COUNT(*) as cnt FROM license_requests WHERE status = 'pending'");
                            $pending_req_count = $stmt3->fetchColumn();
                        } catch (Exception $e) {}
                        try {
                            $stmt4 = get_db()->query("SELECT COUNT(*) as cnt FROM proforma_invoices WHERE status = 'sent'");
                            $pending_proforma_count = $stmt4->fetchColumn();
                        } catch (Exception $e) {}
                    }
                ?>
                <a href="/staff/" class="<?= $is_dashboard ? 'active' : '' ?>">🏠 Dashboard</a>
                <a href="/staff/pricing.php" class="<?= $is_pricing ? 'active' : '' ?>">🏷️ Pricing</a>
                <a href="/staff/releases.php" class="<?= $is_releases ? 'active' : '' ?>">📦 Releases</a>
                <a href="/staff/licenses/" class="<?= $is_licenses ? 'active' : '' ?>">🔑 Licenses
                    <?php if ($expiring_lic_count > 0): ?><span class="badge badge-warning" style="margin-left:auto;"><?= $expiring_lic_count ?></span><?php endif; ?>
                </a>
                <a href="/staff/licenses/offline.php" class="<?= strpos($req, '/staff/licenses/offline') !== false ? 'active' : '' ?>" style="padding-left:2.2rem;font-size:.9em;">📴 Offline token</a>
                <a href="/staff/licenses/audit.php" class="<?= strpos($req, '/staff/licenses/audit') !== false ? 'active' : '' ?>" style="padding-left:2.2rem;font-size:.9em;">🧾 License audit</a>
                <a href="/staff/license-requests.php" class="<?= $is_requests ? 'active' : '' ?>">📋 Requests
                    <?php if ($pending_req_count > 0): ?><span class="badge badge-warning" style="margin-left:auto;"><?= $pending_req_count ?></span><?php endif; ?>
                </a>
                <a href="/staff/proformas.php" class="<?= $is_proformas ? 'active' : '' ?>">💰 Proformas
                    <?php if ($pending_proforma_count > 0): ?><span class="badge badge-warning" style="margin-left:auto;"><?= $pending_proforma_count ?></span><?php endif; ?>
                </a>
                <a href="/staff/tickets.php" class="<?= $is_tickets ? 'active' : '' ?>">🎫 Tickets
                    <?php if ($open_count > 0): ?><span class="badge badge-warning" style="margin-left:auto;"><?= $open_count ?></span><?php endif; ?>
                </a>
                <a href="/staff/newsletter-campaigns.php" class="<?= strpos($req, '/staff/newsletter') !== false ? 'active' : '' ?>">📰 Newsletter</a>
                <a href="/staff/logout.php">🌐 Public Site</a>
            </nav>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="staff-main-wrapper">
            <!-- Topbar -->
            <header class="staff-topbar">
                <div class="topbar-left">
                    <button id="sidebarToggle" class="sidebar-toggle">☰</button>
                    <h2><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></h2>
                </div>
                
                <div class="topbar-right">
                    <?php if (is_staff_logged_in()): ?>
                        <div class="staff-profile">
                            <span class="staff-name"><?= htmlspecialchars($_SESSION['staff_name'] ?? '') ?></span>
                            <span class="badge badge-role"><?= htmlspecialchars($_SESSION['staff_role'] ?? '') ?></span>
                        </div>
                        <a href="/staff/logout.php" class="btn btn-outline btn-sm">Logout</a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Flash Messages -->
            <div class="staff-flash-container">
                <?php if (function_exists('get_flash')): ?>
                <?php $flash = get_flash(); if ($flash): ?>
                    <div class="flash-message flash-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Page Content -->
            <main class="staff-content">
