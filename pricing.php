<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/license_pricing.php';

$prices = get_license_prices();

$page_title = t('nav.pricing');
$meta_description = t('seo.pricing_desc');
require __DIR__ . '/includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 2rem;">
    <div class="section-heading reveal" style="margin-bottom: 2rem;">
        <h1><?= htmlspecialchars(t('pricing.title')) ?></h1>
        <p><?= htmlspecialchars(t('pricing.subtitle')) ?></p>
    </div>

    <div class="grid-3">
        <?php foreach ($prices as $tier => $row): ?>
            <?php
            $isProfessional = ($tier === 'professional');
            $price = number_format((float)$row['price_eur'], 0, ',', '.');
            $key_prefix = $tier;
            if ($tier === 'professional') $key_prefix = 'prof';
            if ($tier === 'enterprise') $key_prefix = 'ent';
            ?>
            <article class="glass-card reveal<?= $isProfessional ? ' pricing-card-professional' : '' ?>" style="position: relative; overflow: hidden;">
                <?php if ($isProfessional): ?>
                    <span class="pricing-ribbon-highlight"><?= htmlspecialchars(t('pricing.professional_promo_badge')) ?></span>
                <?php endif; ?>

                <h3 style="font-size:1.6rem; margin-bottom:0.5rem;"><?= htmlspecialchars(t('pricing.' . $tier . '_name')) ?></h3>
                <p style="color:var(--text-muted); min-height:3.5rem;"><?= htmlspecialchars(t('pricing.' . $tier . '_desc')) ?></p>

                <div style="margin:1.5rem 0 0.6rem; display:flex; align-items:baseline; gap:0.4rem;">
                    <span style="font-size:2.4rem; font-family:var(--font-display); font-weight:700;">€<?= $price ?></span>
                    <span style="color:var(--text-muted);">/<?= htmlspecialchars(t('pricing.period_' . $row['billing_period'])) ?></span>
                </div>

                <?php
                $fmt_limit = fn($v) => $v !== null && (int)$v > 0 ? (int)$v : '∞';
                $limits_line = '📋 ' . $fmt_limit($row['max_projects'] ?? null) . ' ' . htmlspecialchars(t('pricing.limit_projects'))
                    . ' · 👤 ' . $fmt_limit($row['max_users'] ?? null) . ' ' . htmlspecialchars(t('pricing.limit_users'))
                    . ' · 💻 ' . (int)($row['max_activations'] ?? 1) . ' ' . htmlspecialchars(t('pricing.limit_stations'));
                ?>
                <p style="margin:0 0 1rem; font-family:var(--font-display); font-weight:600; font-size:1.02rem; color:#a5b4fc;"><?= $limits_line ?></p>

                <ul style="list-style:none; display:grid; gap:0.6rem; margin-bottom:1.4rem; color:var(--text-main);">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <li>✅ <?= htmlspecialchars(t('pricing.' . $key_prefix . '_feature_' . $i)) ?></li>
                    <?php endfor; ?>
                </ul>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="/demo" class="btn btn-primary" style="flex:1; text-align:center;">
                        <?= htmlspecialchars(t('pricing.cta_request')) ?>
                    </a>
                    <button type="button" class="btn btn-outline js-proforma" style="flex:1; text-align:center; border-color: var(--accent); color: var(--accent);"
                            data-plan="<?= htmlspecialchars($tier) ?>"
                            data-name="<?= htmlspecialchars(t('pricing.' . $tier . '_name')) ?>"
                            data-price="<?= htmlspecialchars($price) ?>">
                        📄 <?= htmlspecialchars(t('pricing.request_proforma')) ?>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="reveal glass-card" style="max-width: 900px; margin: 2rem auto 0; text-align:center; border-color: rgba(245, 158, 11, 0.35);">
        <h3 style="margin-bottom:.5rem;">💻 <?= htmlspecialchars(t('pricing.addon_station_title')) ?></h3>
        <p style="color:var(--text-muted); margin-bottom: 1rem; max-width: 640px; margin-inline:auto;"><?= htmlspecialchars(t('pricing.addon_station_desc')) ?></p>
        <button type="button" class="btn btn-outline js-proforma" style="border-color: var(--amber, #f59e0b); color: #f59e0b;"
                data-plan="stations_addon"
                data-name="<?= htmlspecialchars(t('pricing.addon_station_title')) ?>"
                data-price="0">
            <?= htmlspecialchars(t('pricing.addon_station_cta')) ?>
        </button>
    </div>

    <div class="reveal glass-card" style="max-width: 900px; margin: 2rem auto 0; text-align:center;">
        <h3 style="margin-bottom:.5rem;">🧪 <?= htmlspecialchars(t('pricing.trial_name')) ?></h3>
        <p style="color:var(--text-secondary); margin-bottom: 1rem;"><?= htmlspecialchars(t('pricing.trial_note')) ?></p>
        <a href="/auth/register.php" class="btn btn-secondary"><?= htmlspecialchars(t('cta.button')) ?></a>
    </div>

    <div class="reveal" style="text-align:center; margin-top:2rem; color:var(--text-muted); font-size:0.95rem;">
        <?= htmlspecialchars(t('pricing.note_vat')) ?><br>
        <?= htmlspecialchars(t('pricing.note_installations')) ?>
    </div>
</section>

<!-- Proforma Request Modal -->
<div id="proformaModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.7); align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div class="glass-card" style="max-width:500px; width:90%; padding:2rem; position:relative; max-height:90vh; overflow-y:auto;">
        <button onclick="closeProformaModal()" style="position:absolute; top:12px; right:16px; background:none; border:none; color:var(--text-main); font-size:1.5rem; cursor:pointer;">✕</button>
        <h3 style="margin-bottom:0.3rem;">📄 <?= htmlspecialchars(t('pricing.proforma_modal_title')) ?></h3>
        <p id="proformaPlanInfo" style="color:var(--text-muted); margin-bottom:1.2rem; font-size:0.95rem;"></p>

        <form id="proformaForm" onsubmit="submitProforma(event)">
            <input type="hidden" id="proformaPlan" name="plan">
            <input type="hidden" name="lang" value="<?= htmlspecialchars(get_lang()) ?>">

            <div style="margin-bottom:0.9rem;">
                <label style="font-size:0.9rem;"><?= htmlspecialchars(t('pricing.proforma_name')) ?> *</label>
                <input type="text" name="name" required style="width:100%; padding:0.5rem; margin-top:0.3rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main);">
            </div>

            <div style="margin-bottom:0.9rem;">
                <label style="font-size:0.9rem;"><?= htmlspecialchars(t('pricing.proforma_email')) ?> *</label>
                <input type="email" name="email" required style="width:100%; padding:0.5rem; margin-top:0.3rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main);">
            </div>

            <div style="margin-bottom:0.9rem;">
                <label style="font-size:0.9rem;"><?= htmlspecialchars(t('pricing.proforma_company')) ?></label>
                <input type="text" name="company" style="width:100%; padding:0.5rem; margin-top:0.3rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main);">
            </div>

            <div style="margin-bottom:0.9rem;">
                <label style="font-size:0.9rem;"><?= htmlspecialchars(t('pricing.proforma_vat')) ?></label>
                <input type="text" name="vat_id" placeholder="e.g. RO12345678" style="width:100%; padding:0.5rem; margin-top:0.3rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main);">
            </div>

            <div style="margin-bottom:1.2rem;">
                <label style="font-size:0.9rem;"><?= htmlspecialchars(t('pricing.proforma_address')) ?></label>
                <textarea name="address" rows="2" style="width:100%; padding:0.5rem; margin-top:0.3rem; border-radius:4px; border:1px solid var(--border-glass); background:var(--bg-glass); color:var(--text-main);"></textarea>
            </div>

            <button type="submit" id="proformaSubmitBtn" class="btn btn-primary" style="width:100%;">
                <?= htmlspecialchars(t('pricing.proforma_submit')) ?>
            </button>
        </form>

        <div id="proformaResult" style="display:none; text-align:center; padding:1.5rem 0;">
            <div style="font-size:2rem; margin-bottom:0.5rem;">✅</div>
            <p style="font-size:1.05rem; font-weight:600;"><?= htmlspecialchars(t('pricing.proforma_success')) ?></p>
            <p style="color:var(--text-muted); font-size:0.9rem;">⚠️ <?= htmlspecialchars(t('pricing.proforma_check_spam')) ?></p>
            <p style="color:var(--text-muted); font-size:0.9rem;">📎 <?= htmlspecialchars(t('pricing.proforma_upload_msg')) ?></p>
        </div>
    </div>
</div>

<script>
var PROFORMA_TXT = <?= json_encode([
    'submit' => t('pricing.proforma_submit'),
    'period' => t('pricing.period_year'),
    'addon_note' => t('pricing.addon_quote_note'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
function openProformaModal(plan, planName, price) {
    document.getElementById('proformaPlan').value = plan;
    document.getElementById('proformaPlanInfo').textContent = plan === 'stations_addon'
        ? planName + ' — ' + PROFORMA_TXT.addon_note
        : planName + ' — €' + price + '/' + PROFORMA_TXT.period;
    document.getElementById('proformaForm').style.display = '';
    document.getElementById('proformaResult').style.display = 'none';
    document.getElementById('proformaModal').style.display = 'flex';
}
document.querySelectorAll('.js-proforma').forEach(function (b) {
    b.addEventListener('click', function () {
        openProformaModal(b.dataset.plan, b.dataset.name, b.dataset.price);
    });
});
function closeProformaModal() {
    document.getElementById('proformaModal').style.display = 'none';
}
document.getElementById('proformaModal').addEventListener('click', function(e) {
    if (e.target === this) closeProformaModal();
});
function submitProforma(e) {
    e.preventDefault();
    var btn = document.getElementById('proformaSubmitBtn');
    btn.disabled = true;
    btn.textContent = '⏳ …';
    var form = document.getElementById('proformaForm');
    var data = {};
    new FormData(form).forEach(function(v, k) { data[k] = v; });
    fetch('/api/proforma/request.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(j) {
        if (j.ok) {
            form.style.display = 'none';
            document.getElementById('proformaResult').style.display = '';
        } else {
            alert(j.error || 'Error sending request.');
            btn.disabled = false;
            btn.textContent = PROFORMA_TXT.submit;
        }
    })
    .catch(function() {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.textContent = PROFORMA_TXT.submit;
    });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
