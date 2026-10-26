<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';
require_once __DIR__ . '/../includes/newsletter.php';

newsletter_ensure_table();
$status = in_array($_GET['status'] ?? '', ['active', 'unsubscribed'], true) ? $_GET['status'] : 'active';
$q = trim($_GET['q'] ?? '');
$langs = newsletter_languages();

// ── POST actions: add / unsubscribe / delete ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        flash('Session expired, try again.', 'error');
    } elseif (($_POST['action'] ?? '') === 'add') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $lang = array_key_exists($_POST['lang'] ?? '', $langs) && $_POST['lang'] !== 'all' ? $_POST['lang'] : 'en';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Invalid email address.', 'error');
        } else {
            $ex = db_fetch("SELECT id FROM newsletter_subscribers WHERE email = ?", [$email]);
            if ($ex) {
                db_query("UPDATE newsletter_subscribers SET status = 'active', lang = ?, unsubscribed_at = NULL WHERE id = ?", [$lang, $ex['id']]);
            } else {
                db_insert("INSERT INTO newsletter_subscribers (email, lang, status, token) VALUES (?, ?, 'active', ?)",
                    [$email, $lang, bin2hex(random_bytes(32))]);
            }
            flash("$email added.", 'success');
        }
    } elseif (($_POST['action'] ?? '') === 'unsubscribe') {
        db_query("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [(int)$_POST['id']]);
        flash('Subscriber unsubscribed.', 'success');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        // GDPR erasure: remove the address and its delivery history
        $sid = (int)$_POST['id'];
        db_query("DELETE FROM newsletter_deliveries WHERE subscriber_id = ?", [$sid]);
        db_query("DELETE FROM newsletter_subscribers WHERE id = ?", [$sid]);
        flash('Subscriber permanently deleted.', 'success');
    }
    header('Location: /staff/newsletter.php?status=' . $status . ($q !== '' ? '&q=' . urlencode($q) : ''));
    exit;
}

$where = "status = ?";
$params = [$status];
if ($q !== '') { $where .= " AND email LIKE ?"; $params[] = '%' . $q . '%'; }

// CSV export
if (isset($_GET['export'])) {
    $rows = db_fetch_all("SELECT email, lang, status, created_at, unsubscribed_at FROM newsletter_subscribers WHERE $where ORDER BY created_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter_' . $status . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, ['email', 'lang', 'status', 'created_at', 'unsubscribed_at']);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

$subs = db_fetch_all("SELECT * FROM newsletter_subscribers WHERE $where ORDER BY created_at DESC LIMIT 500", $params);
$counts = [
    'active' => db_fetch("SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'active'")['c'] ?? 0,
    'unsubscribed' => db_fetch("SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'unsubscribed'")['c'] ?? 0,
];

$page_title = 'Newsletter subscribers';
include __DIR__ . '/includes/staff_header.php';
$csrf = htmlspecialchars($_SESSION['csrf_token']);
?>
<div class="welcome-banner" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
    <div>
        <h1>📰 Newsletter</h1>
        <p>Subscribers collected from the public site footer.</p>
    </div>
    <a href="/staff/newsletter-edit.php" class="btn btn-primary">＋ New newsletter</a>
</div>

<?php $nl_tab = 'subscribers'; include __DIR__ . '/includes/newsletter_tabs.php'; ?>

<div class="glass-card" style="padding:1.25rem; margin-bottom:1.5rem;">
    <form method="post" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="add">
        <strong style="margin-right:0.5rem;">Add subscriber</strong>
        <input type="email" name="email" class="form-control" placeholder="email@company.com" required style="flex:1; min-width:220px;">
        <select name="lang" class="form-control" style="width:150px;">
            <?php foreach ($langs as $code => $label): if ($code === 'all') continue; ?>
                <option value="<?= $code ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Add</button>
    </form>
    <p class="text-muted" style="font-size:0.8rem; margin:0.6rem 0 0;">Only add people who gave you consent to receive the newsletter (GDPR).</p>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div style="margin-bottom:1rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <a href="?status=active" class="btn btn-sm <?= $status === 'active' ? 'btn-primary' : 'btn-outline' ?>">Active (<?= (int)$counts['active'] ?>)</a>
        <a href="?status=unsubscribed" class="btn btn-sm <?= $status === 'unsubscribed' ? 'btn-primary' : 'btn-outline' ?>">Unsubscribed (<?= (int)$counts['unsubscribed'] ?>)</a>
        <form method="get" style="margin-left:auto; display:flex; gap:0.4rem;">
            <input type="hidden" name="status" value="<?= $status ?>">
            <input name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search email…" style="width:200px;">
        </form>
        <a href="?status=<?= $status ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>&export=1" class="btn btn-sm btn-outline">⬇ Export CSV</a>
    </div>
    <div class="table-responsive">
        <table class="staff-table">
            <thead><tr><th>Email</th><th>Lang</th><th>Subscribed</th><?php if ($status === 'unsubscribed'): ?><th>Unsubscribed</th><?php endif; ?><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($subs)): ?>
                <tr><td colspan="5" style="text-align:center;">No subscribers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($subs as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><?= strtoupper(htmlspecialchars($s['lang'])) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($s['created_at'])) ?></td>
                    <?php if ($status === 'unsubscribed'): ?><td><?= $s['unsubscribed_at'] ? date('Y-m-d H:i', strtotime($s['unsubscribed_at'])) : '-' ?></td><?php endif; ?>
                    <td style="text-align:right; white-space:nowrap;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <?php if ($status === 'active'): ?>
                                <button name="action" value="unsubscribe" class="btn btn-sm btn-outline">Unsubscribe</button>
                            <?php endif; ?>
                            <button name="action" value="delete" class="btn btn-sm btn-outline" style="color:var(--error);"
                                    onclick="return confirm('Permanently delete <?= htmlspecialchars($s['email'], ENT_QUOTES) ?>?');">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/staff_footer.php'; ?>
