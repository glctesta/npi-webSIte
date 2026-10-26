<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/../includes/staff_functions.php';
require_once __DIR__ . '/../../includes/license_lib.php';

$page_title = "Create License";
$users = [];
try {
    $users = db_fetch_all("SELECT id, email, company, CONCAT(first_name, ' ', last_name) as full_name FROM users ORDER BY email ASC");
} catch (Exception $e) {}

$plans = [];
try {
    $plans = db_fetch_all("SELECT id, slug, name FROM plans WHERE is_active = 1 ORDER BY sort_order ASC");
} catch (Exception $e) {}

$created_license = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_csrf_ok()) {
        http_response_code(400);
        die('CSRF token validation failed.');
    }

    $user_id      = (int)($_POST['user_id'] ?? 0);
    $plan_id      = (int)($_POST['plan_id'] ?? 0);
    $valid_until  = trim($_POST['valid_until'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    $grace_days   = max(1, min(90, (int)($_POST['grace_days'] ?? LICENSE_DEFAULT_GRACE_DAYS)));
    $max_projects = ($_POST['max_projects'] ?? '') !== '' ? max(1, (int)$_POST['max_projects']) : null;
    $max_users    = ($_POST['max_users'] ?? '') !== '' ? max(1, (int)$_POST['max_users']) : null;
    $features     = array_values(array_intersect((array)($_POST['features'] ?? []), npi_feature_addons()));

    $user = db_fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    $plan = db_fetch("SELECT * FROM plans WHERE id = ?", [$plan_id]);
    $tier_slug = $plan['slug'] ?? 'professional';
    $max_act = ($_POST['max_activations'] ?? '') !== ''
        ? max(1, min(500, (int)$_POST['max_activations']))
        : (isset($plan['max_activations']) && $plan['max_activations'] !== null ? (int)$plan['max_activations'] : default_max_activations($tier_slug));

    if (!$user) $errors[] = 'Select a customer.';
    if (!$plan) $errors[] = 'Select a plan.';
    $dt = DateTime::createFromFormat('Y-m-d', $valid_until);
    if (!$dt || $dt->format('Y-m-d') !== $valid_until) $errors[] = 'Valid until must be a date (YYYY-MM-DD).';

    if (!$errors) {
        $tier = $plan['slug'];
        $expiry = $valid_until . ' 23:59:59';
        $grace_expires_at = date('Y-m-d H:i:s', strtotime($expiry) + $grace_days * 86400);
        if ($company_name === '') $company_name = $user['company'] ?: null;

        // Unique NPI-XXXXX-XXXXX-XXXXX-XXXXX key
        do {
            $license_key = lic_generate_key();
        } while (db_fetch("SELECT id FROM licenses WHERE license_key = ?", [$license_key]));

        $lid = db_insert(
            "INSERT INTO licenses (user_id, plan_id, license_key, status, company_name, starts_at, expires_at, grace_days, grace_expires_at,
                                   max_activations, max_projects, max_users, extra_features, notes, created_at, updated_at)
             VALUES (?, ?, ?, 'active', ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$user_id, $plan_id, $license_key, $company_name, $expiry, $grace_days, $grace_expires_at,
             $max_act, $max_projects, $max_users, $features ? json_encode($features) : null, $notes !== '' ? $notes : null]
        );
        generate_api_token($lid, $expiry);
        $staff = get_staff_user();
        lic_audit($lid, $license_key, 'admin:create', null, 'by ' . ($staff['email'] ?? 'staff') . ", tier=$tier, max_act=$max_act, max_projects=" . ($max_projects ?? 'unlimited'));

        // Email to customer
        require_once __DIR__ . '/../../includes/mailer.php';
        $customer_name = htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['email']);
        $tier_label    = htmlspecialchars(ucfirst($tier));
        $expiry_fmt    = date('d.m.Y', strtotime($expiry));
        $email_html    = htmlspecialchars($user['email']);
        $key_html      = htmlspecialchars($license_key);

        $subject = "GTMC-NPI — your $tier_label license is ready";
        $email_body = <<<HTML
<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0f172a; color: #e2e8f0; border-radius: 12px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 30px 40px; text-align: center;">
        <h1 style="margin: 0; color: #fff; font-size: 24px; letter-spacing: 1px;">🔑 GTMC-NPI</h1>
        <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 14px;">NPI &amp; change management under control</p>
    </div>
    <div style="padding: 30px 40px;">
        <p style="font-size: 16px; margin-bottom: 20px;">Dear <strong>{$customer_name}</strong>,</p>
        <p>Your <strong style="color: #818cf8;">{$tier_label}</strong> license for GTMC-NPI has been issued.</p>
        <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; padding: 20px; margin: 25px 0;">
            <p style="margin: 0 0 8px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">License key</p>
            <code style="display: block; background: #1e293b; padding: 15px; border-radius: 6px; font-size: 16px; color: #a5b4fc; letter-spacing: 1px;">{$key_html}</code>
        </div>
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr><td style="padding: 8px 0; color: #94a3b8;">Tier</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{$tier_label}</td></tr>
            <tr><td style="padding: 8px 0; color: #94a3b8;">Valid until</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{$expiry_fmt}</td></tr>
            <tr><td style="padding: 8px 0; color: #94a3b8;">Installations</td><td style="padding: 8px 0; text-align: right;">{$max_act}</td></tr>
            <tr><td style="padding: 8px 0; color: #94a3b8;">Registered email</td><td style="padding: 8px 0; text-align: right;">{$email_html}</td></tr>
        </table>
        <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 15px 20px; border-radius: 0 8px 8px 0; margin: 25px 0;">
            <p style="margin: 0 0 10px; font-weight: 600; color: #10b981;">📋 How to activate</p>
            <ol style="margin: 0; padding-left: 20px; line-height: 2;">
                <li>Open <strong>GTMC-NPI</strong> and sign in as administrator</li>
                <li>Go to <strong>Amministrazione → Licenza</strong></li>
                <li>Paste the key and click <strong>Activează</strong> (the server needs internet access once)</li>
                <li>No internet on the server? Send us the fingerprint shown on that page and we will send you an offline token.</li>
            </ol>
        </div>
        <p style="color: #94a3b8; font-size: 13px; margin-top: 30px;">Questions? <a href="mailto:help@gtmc-npi.eu" style="color: #818cf8;">help@gtmc-npi.eu</a></p>
    </div>
    <div style="background: #1e293b; padding: 15px 40px; text-align: center; font-size: 12px; color: #64748b;">
        © GTMC — GTMC-NPI<br>
        <a href="https://gtmc-npi.eu" style="color: #818cf8;">gtmc-npi.eu</a>
    </div>
</div>
HTML;

        $email_sent = false;
        if (!empty($_POST['send_email'])) {
            try {
                $mailer = new SmtpMailer();
                $email_sent = $mailer->sendMail($user['email'], $subject, $email_body);
            } catch (Throwable $e) {
                error_log('License email failed: ' . $e->getMessage());
            }
            if ($email_sent) {
                flash("License created and email sent to {$user['email']}.");
            } else {
                flash("License created but the email could not be sent. Share the key manually.", "warning");
            }
        } else {
            flash("License created.");
        }
        $created_license = ['id' => $lid, 'key' => $license_key];
    }
}

include __DIR__ . '/../includes/staff_header.php';
$field = 'width: 100%; padding: 0.5rem; margin-top: 0.4rem; border-radius: 4px; border: 1px solid var(--border-glass); background: var(--bg-glass); color: var(--text-main);';
?>

<div class="glass-card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header">
        <h3>🔑 Create New License</h3>
    </div>
    <div class="card-body" style="padding: 1.5rem;">
        <?php if ($created_license): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 15px 20px; border-radius: 4px; margin-bottom: 1rem;">
                <strong>License key generated</strong>
                <code style="display:block; margin: 10px 0; font-size: 1.1rem; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 4px;"><?= htmlspecialchars($created_license['key']) ?></code>
            </div>
            <a href="/staff/licenses/edit.php?id=<?= (int)$created_license['id'] ?>" class="btn btn-primary">Open license</a>
            <a href="/staff/licenses/offline.php?id=<?= (int)$created_license['id'] ?>" class="btn btn-secondary">Offline token</a>
            <a href="/staff/licenses/" class="btn btn-secondary">Back to licenses</a>
        <?php else: ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
        <?php if (!lic_signing_available()): ?>
            <div class="alert alert-warning" style="margin-bottom:1rem;">⚠️ The RSA private key is not configured (LICENSE_RSA_PRIVATE_KEY_PATH). Licenses can be created, but the app cannot be activated until the key is installed.</div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div style="margin-bottom: 1rem;">
                <label>👤 Customer account</label>
                <select name="user_id" required style="<?= $field ?>">
                    <option value="">— Select customer —</option>
                    <?php foreach($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['email'] . ' — ' . ($u['company'] ?: $u['full_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 1rem;">
                <label>🏢 Company name shown in the app (empty = customer company)</label>
                <input type="text" name="company_name" maxlength="200" style="<?= $field ?>">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div style="margin-bottom: 1rem;">
                    <label>🏷️ Tier</label>
                    <select name="plan_id" required style="<?= $field ?>">
                        <?php foreach($plans as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $p['slug'] === 'professional' ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] . ' (' . $p['slug'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>📅 Valid until</label>
                    <input type="date" name="valid_until" required value="<?= date('Y-m-d', strtotime('+1 year')) ?>" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>💻 Max installations (empty = tier default: Trial 1 / Professional 3 / Enterprise 5)</label>
                    <input type="number" name="max_activations" min="1" max="500" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>⏳ Offline grace days</label>
                    <input type="number" name="grace_days" min="1" max="90" value="<?= (int)LICENSE_DEFAULT_GRACE_DAYS ?>" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>📋 Max active NPI projects (empty = tier default)</label>
                    <input type="number" name="max_projects" min="1" style="<?= $field ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>🔐 Max app users (empty = tier default)</label>
                    <input type="number" name="max_users" min="1" style="<?= $field ?>">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label>➕ Add-on features (beyond the tier)</label>
                <div style="display:flex; flex-wrap:wrap; gap:.4rem 1rem; margin-top:.4rem;">
                    <?php foreach (npi_feature_addons() as $f): ?>
                    <label style="font-size:.85rem;"><input type="checkbox" name="features[]" value="<?= $f ?>"> <?= $f ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label>📝 Internal notes</label>
                <textarea name="notes" rows="3" style="<?= $field ?>"></textarea>
            </div>

            <label style="display:block; margin-bottom:1rem;"><input type="checkbox" name="send_email" value="1" checked> Email the key to the customer</label>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Create License</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/staff_footer.php'; ?>
