<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';
require_once __DIR__ . '/../includes/newsletter.php';

newsletter_ensure_table();
$campaigns = db_fetch_all("
    SELECT c.*,
        (SELECT COUNT(*) FROM newsletter_deliveries d WHERE d.campaign_id = c.id AND d.status = 'sent')   AS sent_count,
        (SELECT COUNT(*) FROM newsletter_deliveries d WHERE d.campaign_id = c.id AND d.status = 'failed') AS failed_count
    FROM newsletter_campaigns c ORDER BY c.created_at DESC LIMIT 200");
$active_subs = db_fetch("SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'active'")['c'] ?? 0;
$langs = newsletter_languages();
$status_badge = ['draft' => 'secondary', 'sending' => 'warning', 'sent' => 'success'];

$page_title = 'Newsletter';
include __DIR__ . '/includes/staff_header.php';
?>
<div class="welcome-banner" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
    <div>
        <h1>📰 Newsletter</h1>
        <p><?= (int)$active_subs ?> active subscribers</p>
    </div>
    <a href="/staff/newsletter-edit.php" class="btn btn-primary">＋ New newsletter</a>
</div>

<?php $nl_tab = 'campaigns'; include __DIR__ . '/includes/newsletter_tabs.php'; ?>

<div class="glass-card" style="padding:1.5rem;">
    <div class="table-responsive">
        <table class="staff-table">
            <thead><tr><th>Subject</th><th>Audience</th><th>Status</th><th>Delivered</th><th>Date</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($campaigns)): ?>
                <tr><td colspan="6" style="text-align:center; padding:2.5rem;">
                    No newsletters yet. <a href="/staff/newsletter-edit.php">Create the first one →</a>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td><a href="/staff/newsletter-edit.php?id=<?= $c['id'] ?>"><strong><?= htmlspecialchars($c['subject']) ?></strong></a></td>
                    <td><?= htmlspecialchars($langs[$c['target_lang']] ?? $c['target_lang']) ?></td>
                    <td><span class="badge badge-<?= $status_badge[$c['status']] ?? 'secondary' ?>"><?= ucfirst($c['status']) ?></span></td>
                    <td>
                        <?php if ($c['status'] === 'draft'): ?>—<?php else: ?>
                            <?= (int)$c['sent_count'] ?> / <?= (int)$c['total_recipients'] ?>
                            <?php if ($c['failed_count'] > 0): ?><span class="text-danger"> (<?= (int)$c['failed_count'] ?> failed)</span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($c['sent_at'] ?: $c['updated_at'])) ?></td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a href="/staff/newsletter-edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline"><?= $c['status'] === 'draft' ? 'Edit' : 'Open' ?></a>
                        <button class="btn btn-sm btn-outline" data-act="duplicate" data-id="<?= $c['id'] ?>">Duplicate</button>
                        <?php if ($c['status'] !== 'sending'): ?>
                            <button class="btn btn-sm btn-outline" data-act="delete" data-id="<?= $c['id'] ?>" style="color:var(--error);">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('[data-act]').forEach(function (b) {
    b.addEventListener('click', function () {
        var act = b.dataset.act;
        if (act === 'delete' && !confirm('Delete this newsletter permanently?')) return;
        fetch('/staff/newsletter-api.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: act, id: +b.dataset.id, csrf: <?= json_encode($_SESSION['csrf_token']) ?> })
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.ok) { alert(d.error || 'Error'); return; }
            location.href = act === 'duplicate' ? '/staff/newsletter-edit.php?id=' + d.id : location.href;
        }).catch(function () { alert('Network error'); });
    });
});
</script>
<?php include __DIR__ . '/includes/staff_footer.php'; ?>
