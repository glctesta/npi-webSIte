<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth();
$user = get_logged_user();

$download_count = db_fetch("SELECT COUNT(*) as cnt FROM downloads WHERE user_id = ?", [$user['id']])['cnt'] ?? 0;
$license_count = db_fetch("SELECT COUNT(*) as cnt FROM licenses WHERE user_id = ?", [$user['id']])['cnt'] ?? 0;

require __DIR__ . '/../includes/header.php';
?>

<div class="portal-page">
    <div class="glass-card reveal">
        <h2><?= htmlspecialchars(t('portal.welcome')) ?>, <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>!</h2>
        <div class="grid-3" style="margin-top: 2rem;">
            <div class="glass-card">
                <h3><?= htmlspecialchars(t('portal.member_since')) ?></h3>
                <p><?= htmlspecialchars(date('F Y', strtotime($user['created_at'] ?? 'now'))) ?></p>
            </div>
            <div class="glass-card">
                <h3><?= htmlspecialchars(t('portal.downloads_count')) ?></h3>
                <p><?= htmlspecialchars($download_count) ?></p>
            </div>
            <div class="glass-card">
                <h3><?= htmlspecialchars(t('portal.licenses')) ?></h3>
                <p><?= htmlspecialchars($license_count) ?></p>
            </div>
        </div>
        <div class="grid-3" style="margin-top: 2rem;">
            <div class="glass-card" style="display: flex; align-items: center; justify-content: center;">
                <a href="/portal/downloads.php" class="btn btn-primary"><?= htmlspecialchars(t('portal.go_to_downloads')) ?></a>
            </div>
            <div class="glass-card" style="display: flex; align-items: center; justify-content: center;">
                <a href="/portal/licenses.php" class="btn btn-primary"><?= htmlspecialchars(t('portal.go_to_licenses')) ?></a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
