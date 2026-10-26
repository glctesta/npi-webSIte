<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = t('benefits.page_title');
$meta_description = t('seo.benefits_desc');
require __DIR__ . '/includes/header.php';
?>

<div class="portal-page" style="padding-top: 120px;">
    <div class="section-heading" style="margin-bottom: 3rem;">
        <h1>🏆 <?= htmlspecialchars(t('benefits.page_title')) ?></h1>
        <p><?= htmlspecialchars(t('benefits.page_subtitle')) ?></p>
    </div>

    <div style="max-width: 1000px; margin: 0 auto;">

        <!-- Overview -->
        <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; flex-shrink: 0;">📊</div>
                <h2 style="margin: 0; font-size: 1.4rem;"><?= htmlspecialchars(t('benefits.overview_title')) ?></h2>
            </div>
            <p style="line-height: 1.8; color: var(--text-secondary);">
                <?= htmlspecialchars(t('benefits.overview_p1')) ?>
            </p>
            <p style="line-height: 1.8; color: var(--text-secondary); margin-top: 1rem;">
                <?= htmlspecialchars(t('benefits.overview_p2')) ?>
            </p>
        </div>

        <!-- Operational Benefits -->
        <div style="text-align: center; margin: 3rem 0 2rem;">
            <span style="background: linear-gradient(135deg, #10b981, #059669); padding: 0.5rem 1.5rem; border-radius: 50px; color: white; font-weight: 600; font-size: 1.1rem;">✅ <?= htmlspecialchars(t('benefits.intrinsic_title')) ?></span>
        </div>

        <?php
        $operational = [
            ['visibility', '👁️', '#10b981', t('benefits.result')],
            ['discipline', '⚙️', '#6366f1', t('benefits.result')],
            ['traceability', '🔍', '#f59e0b', t('benefits.strategic_value')],
            ['quality', '🛠️', '#ef4444', t('benefits.result')],
            ['analytics', '🤖', '#8b5cf6', t('benefits.evolution')],
        ];
        foreach ($operational as $b):
        ?>
        <div class="glass-card reveal" style="margin-bottom: 1.5rem; padding: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div style="background: rgba(255,255,255,0.05); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;"><?= $b[1] ?></div>
                <h3 style="margin: 0; font-size: 1.2rem;"><?= htmlspecialchars(t('benefits.' . $b[0] . '_title')) ?></h3>
            </div>
            <p style="line-height: 1.8; color: var(--text-secondary);">
                <?= htmlspecialchars(t('benefits.' . $b[0] . '_desc')) ?>
            </p>
            <div style="background: rgba(255,255,255,0.03); border-left: 3px solid <?= $b[2] ?>; padding: 1rem 1.5rem; border-radius: 0 8px 8px 0; margin-top: 1rem;">
                <strong style="color: <?= $b[2] ?>;"><?= htmlspecialchars($b[3]) ?>:</strong> <?= htmlspecialchars(t('benefits.' . $b[0] . '_result')) ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div style="background: rgba(6,182,212,0.15); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">🧩</div>
                <h3 style="margin: 0; font-size: 1.2rem;"><?= htmlspecialchars(t('benefits.modularity_title')) ?></h3>
            </div>
            <p style="line-height: 1.8; color: var(--text-secondary);">
                <?= htmlspecialchars(t('benefits.modularity_desc')) ?>
            </p>
        </div>

        <!-- Strategic Benefits -->
        <div style="text-align: center; margin: 3rem 0 2rem;">
            <span style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); padding: 0.5rem 1.5rem; border-radius: 50px; color: white; font-weight: 600; font-size: 1.1rem;">🌍 <?= htmlspecialchars(t('benefits.extrinsic_title')) ?></span>
        </div>

        <?php
        $strategic = [
            ['digital_hr', '🗂️', '#3b82f6'],
            ['audit', '📋', '#10b981'],
            ['business', '💰', '#f59e0b'],
        ];
        foreach ($strategic as $b):
        ?>
        <div class="glass-card reveal" style="margin-bottom: 1.5rem; padding: 2rem;">
            <h3 style="margin: 0 0 1rem;"><?= $b[1] ?> <?= htmlspecialchars(t('benefits.' . $b[0] . '_title')) ?></h3>
            <p style="line-height: 1.8; color: var(--text-secondary);">
                <?= htmlspecialchars(t('benefits.' . $b[0] . '_desc')) ?>
            </p>
        </div>
        <?php endforeach; ?>

        <!-- Romanian labour-law compliance -->
        <div style="text-align: center; margin: 3rem 0 2rem;">
            <span style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 0.5rem 1.5rem; border-radius: 50px; color: white; font-weight: 600; font-size: 1.1rem;">⚖️ <?= htmlspecialchars(t('benefits.compliance_section_title')) ?></span>
        </div>

        <div class="glass-card reveal" style="margin-bottom: 1.5rem; padding: 2.5rem; border-color: rgba(245, 158, 11, 0.25); background: linear-gradient(135deg, rgba(245, 158, 11, 0.06), rgba(99, 102, 241, 0.06));">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, #f59e0b, #d97706); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; flex-shrink: 0;">⚖️</div>
                <h2 style="margin: 0; font-size: 1.4rem;"><?= htmlspecialchars(t('benefits.compliance_title')) ?></h2>
            </div>
            <p style="line-height: 1.8; color: var(--text-secondary);">
                <?= htmlspecialchars(t('benefits.compliance_intro_p1')) ?>
            </p>
            <p style="line-height: 1.8; color: var(--text-secondary); margin-top: 1rem;">
                <?= htmlspecialchars(t('benefits.compliance_intro_p2')) ?>
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
            <?php
            $bonus_items = [
                ['compliance_reges', '🏛️', '#10b981'],
                ['compliance_gdpr', '🔐', '#6366f1'],
                ['compliance_docs', '📑', '#3b82f6'],
                ['compliance_audit', '🧾', '#f59e0b'],
            ];
            foreach ($bonus_items as $bi):
            ?>
            <div class="glass-card reveal" style="padding: 1.8rem;">
                <div style="font-size: 1.8rem; margin-bottom: 0.8rem;"><?= $bi[1] ?></div>
                <h3 style="font-size: 1.1rem; margin-bottom: 0.6rem;"><?= htmlspecialchars(t('benefits.' . $bi[0] . '_title')) ?></h3>
                <p style="line-height: 1.7; color: var(--text-secondary); font-size: 0.92rem;">
                    <?= htmlspecialchars(t('benefits.' . $bi[0] . '_desc')) ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2rem; border-left: 3px solid #f59e0b;">
            <p style="line-height: 1.8; color: var(--text-secondary);">
                <strong style="color: #f59e0b;"><?= htmlspecialchars(t('benefits.compliance_bottom_line')) ?>:</strong>
                <?= htmlspecialchars(t('benefits.compliance_bottom_desc')) ?>
            </p>
        </div>

        <!-- CTA -->
        <div class="glass-card reveal" style="text-align: center; padding: 3rem; margin-bottom: 2rem; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.1));">
            <h2 style="margin: 0 0 1rem;"><?= htmlspecialchars(t('benefits.cta_title')) ?></h2>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                <?= htmlspecialchars(t('benefits.cta_desc')) ?>
            </p>
            <div style="display: flex; justify-content: center; gap: 1rem;">
                <a href="/demo" class="btn btn-primary">🎯 <?= htmlspecialchars(t('benefits.cta_demo')) ?></a>
                <a href="mailto:info@gtmc-npi.eu" class="btn btn-outline">📧 <?= htmlspecialchars(t('benefits.cta_contact')) ?></a>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
