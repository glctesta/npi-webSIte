<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/license_lib.php';

require_auth();
$user = get_logged_user();

$licenses_page_url = '/portal/licenses.php';

function ensure_trial_plan_id() {
    $trial = db_fetch("SELECT id, is_active FROM plans WHERE slug = 'trial' LIMIT 1");
    if ($trial) {
        if ((int)($trial['is_active'] ?? 0) !== 1) {
            db_query("UPDATE plans SET is_active = 1 WHERE id = ?", [$trial['id']]);
        }
        return (int)$trial['id'];
    }

    $product = db_fetch("SELECT id FROM products WHERE slug = ? LIMIT 1", [PRODUCT_SLUG]);
    if (!$product) {
        $product_id = db_insert(
            "INSERT INTO products (slug, name, description, is_active) VALUES (?, 'GTMC-NPI', 'NPI & change management under control.', 1)",
            [PRODUCT_SLUG]
        );
    } else {
        $product_id = (int)$product['id'];
    }

    return (int)db_insert(
        "INSERT INTO plans (product_id, slug, name, price_eur, billing_period, sort_order, is_active, features) VALUES (?, 'trial', 'Trial', 0.00, 'trial', 0, 1, ?)",
        [$product_id, '["14-day evaluation","3 active projects","5 users","Single installation"]']
    );
}

/** 14-day trial: NPI key, 1 installation, tier "trial" (3 projects, 5 users). */
function create_trial_license(array $user) {
    $trial_id = ensure_trial_plan_id();
    do {
        $license_key = lic_generate_key();
    } while (db_fetch("SELECT id FROM licenses WHERE license_key = ?", [$license_key]));
    $id = db_insert(
        "INSERT INTO licenses (user_id, plan_id, license_key, status, company_name, starts_at, expires_at, grace_days, max_activations)
         VALUES (?, ?, ?, 'active', ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), ?, 1)",
        [$user['id'], $trial_id, $license_key, $user['company'] ?? null, LICENSE_DEFAULT_GRACE_DAYS]
    );
    lic_audit($id, $license_key, 'portal:trial_created', null, 'user ' . (int)$user['id']);
    return [$id, $license_key];
}

function find_active_release_id() {
    try {
        $release = db_fetch("SELECT id FROM releases WHERE is_active = 1 ORDER BY release_date DESC, id DESC LIMIT 1");
        if ($release && isset($release['id'])) {
            return (int)$release['id'];
        }
    } catch (Exception $e) {
        error_log('licenses.php find_active_release_id failed: ' . $e->getMessage());
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // Start free trial (one per account)
    if (isset($_POST['start_trial'])) {
        try {
            $existing = db_fetch("SELECT l.id FROM licenses l JOIN plans p ON p.id = l.plan_id WHERE l.user_id = ? AND p.slug = 'trial'", [$user['id']]);
            if (!$existing) {
                [, $trial_key] = create_trial_license($user);
                $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                @send_installation_key_email($user['email'], $fullName, $trial_key, '1.0.0');
                flash(t('portal.license_trial_started'), 'success');
            }
        } catch (Exception $e) {
            error_log('licenses.php start_trial failed for user ' . (int)$user['id'] . ': ' . $e->getMessage());
            flash(t('common.error'), 'error');
        }
        header('Location: ' . $licenses_page_url);
        exit;
    }

    // Additional installation key bound to a license (accepted by the app like the license key)
    if (isset($_POST['generate_key'], $_POST['license_id'])) {
        try {
            $license_id = (int)$_POST['license_id'];
            $license = db_fetch("SELECT * FROM licenses WHERE id = ? AND user_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())", [$license_id, $user['id']]);
            $count = $license ? (int)(db_fetch("SELECT COUNT(*) AS c FROM installation_keys WHERE license_id = ?", [$license_id])['c'] ?? 0) : 0;

            if ($license && $count < max(1, (int)$license['max_activations'])) {
                do {
                    $key = lic_generate_key();
                } while (db_fetch("SELECT id FROM installation_keys WHERE key_value = ?", [$key]));
                db_insert(
                    "INSERT INTO installation_keys (license_id, release_id, key_value) VALUES (?, ?, ?)",
                    [$license_id, find_active_release_id(), $key]
                );
                lic_audit($license_id, $key, 'portal:install_key', null, 'user ' . (int)$user['id']);

                $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $mailOk = send_installation_key_email($user['email'], $fullName, $key, '1.0.0');
                flash(t('portal.license_key_generated') . ' ' . $key, $mailOk ? 'success' : 'error');
            } else {
                flash(t('portal.license_key_limit'), 'error');
            }
        } catch (Exception $e) {
            error_log('licenses.php generate_key failed for user ' . (int)$user['id'] . ': ' . $e->getMessage());
            flash(t('common.error'), 'error');
        }
        header('Location: ' . $licenses_page_url);
        exit;
    }
}

$licenses = [];
try {
    $licenses = db_fetch_all(
        "SELECT l.*, p.name as plan_name, p.slug as plan_slug
         FROM licenses l
         JOIN plans p ON p.id = l.plan_id
         WHERE l.user_id = ?
         ORDER BY l.created_at DESC",
        [$user['id']]
    );
} catch (Exception $e) {
    error_log('licenses.php list query failed for user ' . (int)$user['id'] . ': ' . $e->getMessage());
}

foreach ($licenses as &$lic) {
    try {
        $lic['activations'] = db_fetch_all(
            "SELECT * FROM license_activations WHERE license_id = ? ORDER BY activated_at DESC",
            [$lic['id']]
        );
    } catch (Exception $e) {
        $lic['activations'] = [];
    }
    try {
        $lic['install_keys'] = db_fetch_all(
            "SELECT * FROM installation_keys WHERE license_id = ? ORDER BY created_at DESC",
            [$lic['id']]
        );
    } catch (Exception $e) {
        $lic['install_keys'] = [];
    }
}
unset($lic);

require __DIR__ . '/../includes/header.php';
?>

<div class="portal-page" style="padding-top: 120px;">
    <div class="section-heading" style="margin-bottom: 2rem;">
        <h2><?= htmlspecialchars(t('portal.licenses')) ?></h2>
    </div>

    <?php if (empty($licenses)): ?>
        <div class="glass-card reveal" style="max-width: 700px; margin: 0 auto; text-align: center; padding: 3rem;">
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;"><?= htmlspecialchars(t('portal.license_no_licenses')) ?></p>
            <form method="POST" action="<?= htmlspecialchars($licenses_page_url) ?>">
                <?= csrf_field() ?>
                <button type="submit" name="start_trial" class="btn btn-primary" value="1"><?= htmlspecialchars(t('portal.license_start_trial')) ?></button>
            </form>
        </div>
    <?php else: ?>
        <?php foreach ($licenses as $lic):
            $active_machines = count(array_filter($lic['activations'], fn($a) => (int)($a['is_active'] ?? 1) === 1));
            $usable = $lic['status'] === 'active' && (!$lic['expires_at'] || strtotime($lic['expires_at']) > time());
        ?>
            <div class="glass-card reveal" style="max-width: 900px; margin: 0 auto 1.5rem; padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="margin: 0;">GTMC-NPI <?= htmlspecialchars($lic['plan_name']) ?></h3>
                        <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.9rem;">
                            <?= htmlspecialchars(t('portal.license_status')) ?>: <span style="color: <?= $usable ? '#10b981' : '#ef4444' ?>;"><?= htmlspecialchars(ucfirst($lic['status'])) ?></span> •
                            <?= htmlspecialchars(t('portal.license_expires')) ?>: <?= htmlspecialchars($lic['expires_at'] ? date('Y-m-d', strtotime($lic['expires_at'])) : '—') ?> •
                            <?= htmlspecialchars(t('portal.license_workstations')) ?>: <?= $active_machines ?> / <?= (int)$lic['max_activations'] ?>
                        </p>
                    </div>
                    <?php if ($usable): ?>
                    <form method="POST" action="<?= htmlspecialchars($licenses_page_url) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="license_id" value="<?= (int)$lic['id'] ?>">
                        <button type="submit" name="generate_key" value="1" class="btn btn-outline" style="cursor: pointer;">
                            🔑 <?= htmlspecialchars(t('portal.license_generate_key')) ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (!empty($lic['license_key'])): ?>
                <div style="margin-top: 1.5rem;">
                    <h4 style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?= htmlspecialchars(t('portal.license_key')) ?></h4>
                    <code style="display:inline-block; background: rgba(0,0,0,0.3); padding: 0.6rem 0.9rem; border-radius: 6px; font-family: monospace; font-size: 1.05rem; letter-spacing: 1px; color: #10b981;"><?= htmlspecialchars($lic['license_key']) ?></code>
                </div>
                <?php endif; ?>

                <?php if (!empty($lic['install_keys'])): ?>
                <div style="margin-top: 1.5rem;">
                    <h4 style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?= htmlspecialchars(t('portal.license_install_keys')) ?></h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($lic['install_keys'] as $ik): ?>
                            <code style="background: rgba(0,0,0,0.3); padding: 0.5rem 0.75rem; border-radius: 6px; font-family: monospace; color: <?= $ik['used_at'] ? 'var(--text-muted)' : '#10b981' ?>;"><?= htmlspecialchars($ik['key_value']) ?></code>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <details style="margin-top: 1.5rem;">
                    <summary style="cursor:pointer; font-weight:600;"><?= htmlspecialchars(t('portal.license_howto_title')) ?></summary>
                    <ol style="margin: .75rem 0 0; padding-left: 1.25rem; line-height: 1.9; color: var(--text-secondary);">
                        <li><?= htmlspecialchars(t('portal.license_howto_1')) ?></li>
                        <li><?= htmlspecialchars(t('portal.license_howto_2')) ?></li>
                        <li><?= htmlspecialchars(t('portal.license_howto_3')) ?></li>
                        <li><?= htmlspecialchars(t('portal.license_howto_4')) ?></li>
                    </ol>
                </details>

                <div style="margin-top: 1.5rem;">
                    <h4 style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?= htmlspecialchars(t('portal.license_activations')) ?></h4>
                    <?php if (empty($lic['activations'])): ?>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;"><?= htmlspecialchars(t('portal.license_no_activations')) ?></p>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php foreach ($lic['activations'] as $act): ?>
                                <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem;">
                                    <?= htmlspecialchars($act['machine_name'] ?: '—') ?>
                                    <?php if (!(int)($act['is_active'] ?? 1)): ?><span style="color:#ef4444;">(<?= htmlspecialchars(t('portal.license_blocked')) ?>)</span><?php endif; ?>
                                    <span style="color: var(--text-muted);">(<?= htmlspecialchars(substr($act['machine_id'], 0, 16)) ?>…) — <?= htmlspecialchars($act['last_seen'] ?: $act['activated_at']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
