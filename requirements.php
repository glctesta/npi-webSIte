<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = t('nav.requirements');
$meta_description = t('seo.requirements_desc');
require __DIR__ . '/includes/header.php';
?>

<div class="portal-page" style="padding-top: 120px;">
    <div class="section-heading" style="margin-bottom: 3rem;">
        <h1><?= htmlspecialchars(t('requirements.title')) ?></h1>
        <p><?= htmlspecialchars(t('requirements.subtitle')) ?></p>
    </div>

    <div style="max-width: 900px; margin: 0 auto;">

        <!-- Step 1: SQL Server -->
        <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: white; flex-shrink: 0;">1</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem;"><?= htmlspecialchars(t('requirements.sql_title')) ?></h2>
                    <p style="margin: 0.25rem 0 0; color: var(--text-muted);"><?= htmlspecialchars(t('requirements.sql_desc')) ?></p>
                </div>
            </div>
            
            <div style="background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 0.5rem; color: #6366f1;">📋 <?= htmlspecialchars(t('requirements.min_specs')) ?></h4>
                <ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8; color: var(--text-secondary);">
                    <li><?= htmlspecialchars(t('requirements.spec_os')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.spec_ram')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.spec_disk')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.spec_sql')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.spec_clients')) ?></li>
                </ul>
            </div>

            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="https://go.microsoft.com/fwlink/p/?linkid=2216019&clcid=0x409&culture=en-us&country=us" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    ⬇️ SQL Server 2022 Express
                </a>
                <a href="https://learn.microsoft.com/en-us/sql/database-engine/install-windows/install-sql-server?view=sql-server-ver16" target="_blank" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    📖 <?= htmlspecialchars(t('requirements.install_guide')) ?>
                </a>
            </div>

            <details style="margin-top: 1.5rem;">
                <summary style="cursor: pointer; color: #6366f1; font-weight: 600;"><?= htmlspecialchars(t('requirements.quick_steps')) ?></summary>
                <ol style="margin-top: 1rem; padding-left: 1.5rem; line-height: 2; color: var(--text-secondary);">
                    <li><?= htmlspecialchars(t('requirements.sql_step1')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.sql_step2')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.sql_step3')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.sql_step4')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.sql_step5')) ?></li>
                </ol>
            </details>
        </div>

        <!-- Step 2: ODBC Driver -->
        <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: white; flex-shrink: 0;">2</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem;"><?= htmlspecialchars(t('requirements.odbc_title')) ?></h2>
                    <p style="margin: 0.25rem 0 0; color: var(--text-muted);"><?= htmlspecialchars(t('requirements.odbc_desc')) ?></p>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="https://go.microsoft.com/fwlink/?linkid=2249006" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    ⬇️ ODBC Driver 18 (x64)
                </a>
                <a href="https://go.microsoft.com/fwlink/?linkid=2249005" target="_blank" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    ⬇️ ODBC Driver 18 (x86)
                </a>
            </div>

            <details style="margin-top: 1.5rem;">
                <summary style="cursor: pointer; color: #6366f1; font-weight: 600;"><?= htmlspecialchars(t('requirements.quick_steps')) ?></summary>
                <ol style="margin-top: 1rem; padding-left: 1.5rem; line-height: 2; color: var(--text-secondary);">
                    <li><?= htmlspecialchars(t('requirements.odbc_step1')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.odbc_step2')) ?></li>
                    <li><?= htmlspecialchars(t('requirements.odbc_step3')) ?></li>
                </ol>
            </details>
        </div>

        <!-- Step 3: SSMS (Optional) -->
        <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, #22c55e, #16a34a); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: white; flex-shrink: 0;">3</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem;"><?= htmlspecialchars(t('requirements.ssms_title')) ?></h2>
                    <p style="margin: 0.25rem 0 0; color: var(--text-muted);"><?= htmlspecialchars(t('requirements.ssms_desc')) ?></p>
                </div>
            </div>

            <a href="https://aka.ms/ssmsfullsetup" target="_blank" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                ⬇️ SQL Server Management Studio (SSMS)
            </a>
        </div>

        <!-- Step 4: GTMC-NPI Setup -->
        <div class="glass-card reveal" style="margin-bottom: 2rem; padding: 2.5rem; border: 1px solid rgba(99,102,241,0.4);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: white; flex-shrink: 0;">4</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem;"><?= htmlspecialchars(t('requirements.gtmc_title')) ?></h2>
                    <p style="margin: 0.25rem 0 0; color: var(--text-muted);"><?= htmlspecialchars(t('requirements.gtmc_desc')) ?></p>
                </div>
            </div>

            <?php if (is_logged_in()): ?>
                <a href="/portal/downloads.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    ⬇️ <?= htmlspecialchars(t('requirements.go_downloads')) ?>
                </a>
            <?php else: ?>
                <p style="color: var(--text-secondary);"><?= htmlspecialchars(t('requirements.login_required')) ?></p>
                <a href="/auth/register.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <?= htmlspecialchars(t('requirements.register_first')) ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Docker (optional) + licensing network -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="glass-card reveal" style="padding: 2rem;">
                <h3 style="margin: 0 0 .8rem;">🐳 <?= htmlspecialchars(t('requirements.docker_title')) ?></h3>
                <p style="line-height: 1.7; color: var(--text-secondary);"><?= htmlspecialchars(t('requirements.docker_desc')) ?></p>
            </div>
            <div class="glass-card reveal" style="padding: 2rem;">
                <h3 style="margin: 0 0 .8rem;">🔑 <?= htmlspecialchars(t('requirements.network_title')) ?></h3>
                <p style="line-height: 1.7; color: var(--text-secondary);"><?= htmlspecialchars(t('requirements.network_desc')) ?></p>
            </div>
        </div>

        <!-- Support -->
        <div class="glass-card reveal" style="text-align: center; padding: 2rem; margin-bottom: 2rem;">
            <p style="font-size: 1.1rem;">💬 <?= htmlspecialchars(t('requirements.support')) ?></p>
            <a href="mailto:help@gtmc-npi.eu" class="btn btn-outline" style="margin-top: 1rem;">
                📧 help@gtmc-npi.eu
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
