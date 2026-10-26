<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth();
$user = get_logged_user();

if (isset($_GET['download'])) {
    $release_id = (int)$_GET['download'];
    $release = db_fetch("SELECT * FROM releases WHERE id = ? AND is_active = 1", [$release_id]);
    if ($release && $release['file_path']) {
        $uploads_root = realpath(__DIR__ . '/../uploads');
        $resolved = realpath(__DIR__ . '/..' . '/' . ltrim($release['file_path'], '/'));
        $file_path = ($resolved && $uploads_root && str_starts_with($resolved, $uploads_root . DIRECTORY_SEPARATOR)) ? $resolved : null;

        if ($file_path && is_file($file_path)) {
            db_insert(
                "INSERT INTO downloads (user_id, release_id, downloaded_at, ip_address) VALUES (?, ?, NOW(), ?)",
                [$user['id'], $release_id, $_SERVER['REMOTE_ADDR'] ?? null]
            );
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($release['file_path']) . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($file_path);
            exit;
        } else {
            flash(t('portal.file_not_found'), 'error');
            header("Location: /portal/downloads.php");
            exit;
        }
    }
}

$releases = db_fetch_all("SELECT * FROM releases WHERE is_active = 1 ORDER BY release_date DESC, version DESC");

require __DIR__ . '/../includes/header.php';
?>

<div class="portal-page" style="padding-top: 120px;">
    <div class="section-heading" style="margin-bottom: 2rem;">
        <h2><?= htmlspecialchars(t('portal.downloads_title')) ?></h2>
        <p><?= htmlspecialchars(t('portal.downloads_subtitle')) ?></p>
    </div>

    <!-- Terms & Conditions Acceptance -->
    <div class="glass-card reveal" style="max-width: 800px; margin: 0 auto 1.5rem; padding: 1.25rem 1.5rem;">
        <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; font-size: 0.9rem; color: var(--text-secondary);">
            <input type="checkbox" id="termsAccepted" style="margin-top: 3px; width: 18px; height: 18px; accent-color: var(--primary); flex-shrink: 0; cursor: pointer;">
            <span><?= htmlspecialchars(t('portal.terms_accept')) ?> 
                <a href="/terms-of-use" target="_blank" style="color: var(--primary-light); font-weight: 600;"><?= htmlspecialchars(t('portal.terms_link')) ?></a>
                 &amp; <a href="/cookie-policy" target="_blank" style="color: var(--primary-light); font-weight: 600;"><?= htmlspecialchars(t('portal.cookie_link')) ?></a>
            </span>
        </label>
    </div>

    <div class="releases-list" style="max-width: 800px; margin: 0 auto;">
        <?php foreach ($releases as $release): ?>
            <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div style="flex: 1;">
                        <span style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); padding: 0.3rem 0.8rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; color: white;">v<?= htmlspecialchars($release['version']) ?></span>
                        <h3 style="margin-top: 0.75rem; font-size: 1.3rem;">GTMC-NPI Setup</h3>
                        
                        <div style="display: flex; gap: 2rem; margin-top: 1rem; flex-wrap: wrap;">
                            <div>
                                <span style="color: var(--text-muted); font-size: 0.85rem;">📦 <?= htmlspecialchars(t('portal.size')) ?></span>
                                <p style="font-weight: 600; margin-top: 0.25rem;"><?= htmlspecialchars($release['file_size'] ?? '—') ?></p>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); font-size: 0.85rem;">📅 <?= htmlspecialchars(t('portal.released')) ?></span>
                                <p style="font-weight: 600; margin-top: 0.25rem;"><?= htmlspecialchars($release['release_date'] ?? date('d M Y', strtotime($release['created_at']))) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($release['file_path']): ?>
                        <button onclick="startDownload(<?= $release['id'] ?>)" class="btn btn-primary" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer;">
                            ⬇️ <?= htmlspecialchars(t('portal.download_btn')) ?>
                        </button>
                    <?php else: ?>
                        <span class="btn btn-outline" style="opacity: 0.5; cursor: not-allowed;"><?= htmlspecialchars(t('portal.coming_soon')) ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($release['min_requirements']): ?>
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <h4 style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">⚙️ <?= htmlspecialchars(t('portal.requirements')) ?></h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($release['min_requirements']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($release['changelog']): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <h4 style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">📋 <?= htmlspecialchars(t('portal.changelog')) ?></h4>
                    <pre style="white-space: pre-wrap; font-family: inherit; color: var(--text-secondary); font-size: 0.85rem; line-height: 1.6;"><?= htmlspecialchars($release['changelog']) ?></pre>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($releases)): ?>
            <div class="glass-card" style="text-align: center; padding: 3rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                <p style="color: var(--text-muted);"><?= htmlspecialchars(t('portal.no_downloads')) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function startDownload(releaseId) {
    var termsBox = document.getElementById('termsAccepted');
    if (!termsBox || !termsBox.checked) {
        termsBox.style.outline = '2px solid #ef4444';
        termsBox.style.outlineOffset = '2px';
        termsBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function() { termsBox.style.outline = ''; }, 3000);
        alert(<?= json_encode(t('portal.terms_alert'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
        return;
    }
    window.location.href = '?download=' + releaseId;
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
