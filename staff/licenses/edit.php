<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/../includes/staff_functions.php';
require_once __DIR__ . '/../../includes/license_lib.php';

$page_title = "Edit License";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /staff/licenses/'); exit; }

$license = db_fetch("
    SELECT l.*, p.slug as tier, p.name as plan_name,
           CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email as customer_email, u.company as customer_company
    FROM licenses l
    LEFT JOIN users u ON l.user_id = u.id
    JOIN plans p ON l.plan_id = p.id
    WHERE l.id = ?
", [$id]);

if (!$license) { header('Location: /staff/licenses/'); exit; }

$plans = [];
try { $plans = db_fetch_all("SELECT id, slug, name FROM plans WHERE is_active = 1 ORDER BY sort_order ASC"); } catch (Exception $e) {}

$staff = get_staff_user();
$actor = $staff['email'] ?? 'staff';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_csrf_ok()) {
        http_response_code(400);
        die('CSRF token validation failed.');
    }

    $action = $_POST['action'] ?? 'update';
    $key = $license['license_key'];

    if ($action === 'renew') {
        // One-click renewal: +1 year from the later of today / current expiry
        $base = max(time(), $license['expires_at'] ? strtotime($license['expires_at']) : time());
        $new_expiry = date('Y-m-d 23:59:59', strtotime('+1 year', $base));
        $grace_days = (int)($license['grace_days'] ?? LICENSE_DEFAULT_GRACE_DAYS);
        $new_grace = date('Y-m-d H:i:s', strtotime($new_expiry) + ($grace_days * 86400));

        db_query("UPDATE licenses SET status = 'active', expires_at = ?, grace_expires_at = ?, updated_at = NOW() WHERE id = ?",
            [$new_expiry, $new_grace, $id]);
        generate_api_token($id, $new_expiry);
        lic_audit($id, $key, 'admin:renew', null, "by $actor until $new_expiry");
        flash("License renewed until " . substr($new_expiry, 0, 10) . ". The app picks it up at its next online check.");
        header("Location: /staff/licenses/edit.php?id=$id");
        exit;
    }

    if (in_array($action, ['block', 'unblock', 'free'], true)) {
        $act_id = (int)($_POST['activation_id'] ?? 0);
        $act = db_fetch("SELECT * FROM license_activations WHERE id = ? AND license_id = ?", [$act_id, $id]);
        if ($act) {
            if ($action === 'free') {
                db_query("DELETE FROM license_activations WHERE id = ?", [$act_id]);
                flash("Activation slot freed. The machine can activate again if a slot is available.");
            } else {
                db_query("UPDATE license_activations SET is_active = ? WHERE id = ?", [$action === 'unblock' ? 1 : 0, $act_id]);
                flash($action === 'block'
                    ? "Machine blocked: its next online check returns machine_deactivated."
                    : "Machine unblocked.");
            }
            lic_audit($id, $key, 'admin:' . $action, $act['machine_id'], "by $actor");
        }
        header("Location: /staff/licenses/edit.php?id=$id");
        exit;
    }

    // Standard update
    $new_plan_id   = (int)($_POST['plan_id'] ?? $license['plan_id']);
    $new_status    = in_array($_POST['status'] ?? '', ['active', 'suspended', 'revoked', 'expired'], true) ? $_POST['status'] : $license['status'];
    $new_until     = trim($_POST['valid_until'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d', $new_until);
    $new_expires   = ($dt && $dt->format('Y-m-d') === $new_until) ? $new_until . ' 23:59:59' : $license['expires_at'];
    $new_grace_days = max(1, min(90, (int)($_POST['grace_days'] ?? 7)));
    $new_grace_expires = $new_expires ? date('Y-m-d H:i:s', strtotime($new_expires) + ($new_grace_days * 86400)) : null;
    $max_act   = max(1, min(500, (int)($_POST['max_activations'] ?? 1)));
    $max_projects = ($_POST['max_projects'] ?? '') !== '' ? max(1, (int)$_POST['max_projects']) : null;
    $max_users = ($_POST['max_users'] ?? '') !== '' ? max(1, (int)$_POST['max_users']) : null;
    $company   = trim($_POST['company_name'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $features  = array_values(array_intersect((array)($_POST['features'] ?? []), npi_feature_addons()));

    db_query("UPDATE licenses SET plan_id = ?, status = ?, expires_at = ?, grace_days = ?, grace_expires_at = ?,
                     max_activations = ?, max_projects = ?, max_users = ?, company_name = ?, extra_features = ?, notes = ?, updated_at = NOW()
              WHERE id = ?",
        [$new_plan_id, $new_status, $new_expires, $new_grace_days, $new_grace_expires,
         $max_act, $max_projects, $max_users, $company !== '' ? $company : null,
         $features ? json_encode($features) : null, $notes !== '' ? $notes : null, $id]);
    lic_audit($id, $key, 'admin:update', null, "by $actor status=$new_status until=" . substr((string)$new_expires, 0, 10) . " max_act=$max_act max_projects=" . ($max_projects ?? 'unlimited'));

    flash("License updated. Changes reach the app at its next online check (token refresh).");
    header("Location: /staff/licenses/edit.php?id=$id");
    exit;
}

$activations = [];
try {
    $activations = db_fetch_all("SELECT * FROM license_activations WHERE license_id = ? ORDER BY is_active DESC, last_seen DESC", [$id]);
} catch (Exception $e) {}
$active_count = count(array_filter($activations, fn($a) => (int)($a['is_active'] ?? 1) === 1));
$features_now = lic_features($license);

include __DIR__ . '/../includes/staff_header.php';

$days_left = $license['expires_at'] ? (int)floor((strtotime($license['expires_at']) - time()) / 86400) : null;
$field = 'width: 100%; padding: 0.5rem; margin-top: 0.4rem; border-radius: 4px; border: 1px solid var(--border-glass); background: var(--bg-glass); color: var(--text-main);';
$csrf = htmlspecialchars($_SESSION['csrf_token']);
?>

<div style="margin-bottom: 1rem; display:flex; gap:.5rem;">
    <a href="/staff/licenses/" class="btn btn-outline btn-sm">← Back to Licenses</a>
    <a href="/staff/licenses/offline.php?id=<?= $id ?>" class="btn btn-outline btn-sm">📴 Offline token</a>
    <a href="/staff/licenses/audit.php?license_id=<?= $id ?>" class="btn btn-outline btn-sm">🧾 Audit log</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Left: License Info & Edit Form -->
    <div class="glass-card" style="padding: 1.5rem;">
        <h3 style="margin-bottom: 1rem;">📋 License #<?= $id ?></h3>

        <div style="background: rgba(99,102,241,0.08); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
            <p style="margin: 0.3rem 0;"><strong>Customer:</strong> <?= htmlspecialchars(trim((string)$license['customer_name']) ?: '—') ?></p>
            <p style="margin: 0.3rem 0;"><strong>Email:</strong> <?= htmlspecialchars((string)$license['customer_email']) ?></p>
            <p style="margin: 0.3rem 0;"><strong>Company in app:</strong> <?= htmlspecialchars(lic_company($license)) ?></p>
            <p style="margin: 0.3rem 0;"><strong>Days remaining:</strong> <?= $days_left === null ? '∞' : max(0, $days_left) ?></p>
            <p style="margin: 0.3rem 0;"><strong>Last check:</strong> <?= $license['last_check'] ? htmlspecialchars($license['last_check'] . ' (' . time_ago($license['last_check']) . ') from ' . ($license['last_check_ip'] ?? '?')) : 'never' ?></p>
            <p style="margin: 0.3rem 0;"><strong>App version:</strong> <?= htmlspecialchars($license['app_version'] ?: '—') ?>
               · <strong>Projects:</strong> <?= $license['reported_projects'] !== null ? (int)$license['reported_projects'] : '—' ?>
               · <strong>Users:</strong> <?= $license['reported_users'] !== null ? (int)$license['reported_users'] : '—' ?></p>
            <p style="margin: 0.3rem 0;"><strong>Created:</strong> <?= date('Y-m-d H:i', strtotime($license['created_at'])) ?></p>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">License Key</label>
            <code style="display: block; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 4px; font-size: 15px; letter-spacing: 1px; margin-top: 4px;"><?= htmlspecialchars((string)$license['license_key']) ?></code>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="update">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0 1rem;">
                <div style="margin-bottom: 1rem;">
                    <label>🏷️ Tier</label>
                    <select name="plan_id" style="<?= $field ?>">
                        <?php foreach($plans as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $p['id'] == $license['plan_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] . ' (' . $p['slug'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>📊 Status</label>
                    <select name="status" style="<?= $field ?>">
                        <?php foreach(['active','suspended','revoked','expired'] as $s): ?>
                        <option value="<?= $s ?>" <?= $license['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>📅 Valid until</label>
                    <input type="date" name="valid_until" value="<?= $license['expires_at'] ? date('Y-m-d', strtotime($license['expires_at'])) : '' ?>" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>⏳ Offline grace (days)</label>
                    <input type="number" name="grace_days" value="<?= (int)($license['grace_days'] ?? 7) ?>" min="1" max="90" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>💻 Max installations</label>
                    <input type="number" name="max_activations" value="<?= (int)($license['max_activations'] ?? 1) ?>" min="1" max="500" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>📋 Max active projects (empty = unlimited)</label>
                    <input type="number" name="max_projects" value="<?= htmlspecialchars((string)($license['max_projects'] ?? '')) ?>" min="1" placeholder="unlimited" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>🔐 Max app users</label>
                    <input type="number" name="max_users" value="<?= htmlspecialchars((string)($license['max_users'] ?? '')) ?>" min="1" placeholder="tier default" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>🏢 Company in app</label>
                    <input type="text" name="company_name" maxlength="200" value="<?= htmlspecialchars((string)($license['company_name'] ?? '')) ?>" placeholder="<?= htmlspecialchars((string)($license['customer_company'] ?? '')) ?>" style="<?= $field ?>">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label>➕ Add-on features</label>
                <div style="display:flex; flex-wrap:wrap; gap:.4rem 1rem; margin-top:.4rem;">
                    <?php foreach (npi_feature_addons() as $f): ?>
                    <label style="font-size:.85rem;"><input type="checkbox" name="features[]" value="<?= $f ?>" <?= in_array($f, $features_now, true) ? 'checked' : '' ?>> <?= $f ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label>📝 Notes</label>
                <textarea name="notes" rows="3" style="<?= $field ?>"><?= htmlspecialchars((string)($license['notes'] ?? '')) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>

        <form method="POST" style="margin-top: 1rem;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="renew">
            <button type="submit" class="btn btn-outline" style="width: 100%; border-color: #10b981; color: #10b981;" onclick="return confirm('Renew license for 1 year?');">
                🔄 Renew +1 Year
            </button>
        </form>
    </div>

    <!-- Right: Activated Machines -->
    <div class="glass-card" style="padding: 1.5rem;">
        <h3 style="margin-bottom: 1rem;">💻 Installations (<?= $active_count ?> / <?= (int)$license['max_activations'] ?> active)</h3>

        <?php if (empty($activations)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No machines activated yet.</p>
        <?php else: ?>
            <?php foreach($activations as $act): $is_on = (int)($act['is_active'] ?? 1) === 1; ?>
            <div style="background: rgba(99,102,241,0.05); border: 1px solid <?= $is_on ? 'rgba(99,102,241,0.15)' : 'rgba(239,68,68,0.4)' ?>; border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong><?= htmlspecialchars($act['machine_name'] ?: 'Unknown host') ?> <?= $is_on ? '' : '<span class="badge badge-danger">blocked</span>' ?></strong>
                    <small style="color: var(--text-muted);"><?= date('Y-m-d H:i', strtotime($act['activated_at'])) ?></small>
                </div>
                <code style="font-size: 0.72rem; color: var(--text-muted); word-break: break-all;"><?= htmlspecialchars($act['machine_id']) ?></code>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                    Last seen: <?= $act['last_seen'] ? time_ago($act['last_seen']) : '—' ?><?= !empty($act['last_ip']) ? ' · ' . htmlspecialchars($act['last_ip']) : '' ?>
                </div>
                <div style="display:flex; gap:.4rem; margin-top:.6rem;">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="activation_id" value="<?= (int)$act['id'] ?>">
                        <input type="hidden" name="action" value="<?= $is_on ? 'block' : 'unblock' ?>">
                        <button class="btn btn-sm btn-outline" onclick="return confirm('<?= $is_on ? 'Block this machine? The app stops at its next check.' : 'Unblock this machine?' ?>');"><?= $is_on ? '⛔ Block' : '✅ Unblock' ?></button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="activation_id" value="<?= (int)$act['id'] ?>">
                        <input type="hidden" name="action" value="free">
                        <button class="btn btn-sm btn-outline" onclick="return confirm('Free this slot? (e.g. server replaced)');">♻️ Free slot</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/staff_footer.php'; ?>
