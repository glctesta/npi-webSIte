<?php
// download.php — pagina pubblica di download dell'installer GTMC-NPI.
//
// Mostra l'ultima installazione completa pubblicata (tabella `releases`)
// e passa il download a api/download-release.php, che e' l'unico punto
// effettivo di consegna del file: uploads/releases/ e uploads/updates/
// sono chiuse dal .htaccess.
//
// Gli aggiornamenti delle installazioni esistenti NON passano da qui:
// li gestisce l'app stessa tramite api/check-update.php e
// api/download-update.php (protetti da licenza).

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/releases_admin.php';

$release = ultima_installazione();

$versione    = $release['version'] ?? null;
$changelog   = $release['changelog'] ?? null;
$data_uscita = !empty($release['release_date'])
    ? date('d/m/Y', strtotime($release['release_date'])) : null;

// dimensione: colonna file_size se compilata, altrimenti letta dal disco
$dimensione = $release['file_size'] ?? null;
if ($versione && !$dimensione) {
    $percorso = percorso_installatore($versione);
    if (is_file($percorso)) {
        $b = filesize($percorso);
        $dimensione = $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB'
                    : number_format($b / 1024, 0) . ' KB';
    }
}

$disponibile = $versione !== null;

$page_title       = t('nav.download') ?: 'Download';
$meta_description = 'Download installer GTMC-NPI per Windows.';
require __DIR__ . '/includes/header.php';
?>

<section style="padding-top: 9rem; padding-bottom: 4rem;">
    <div class="container">

        <div class="text-center mb-4">
            <h1 class="h2 fw-bold mb-2">
                <i class="bi bi-download me-2"></i>Scarica GTMC-NPI
            </h1>
            <p class="text-muted mb-0">
                Installer completo per Windows. Durante l'installazione il
                programma chiede la <strong>chiave di licenza</strong>: la ricevi
                via email dopo aver confermato la richiesta demo. Copiala prima
                di iniziare.
            </p>
        </div>

        <?php if ($disponibile): ?>

        <div class="glass-card" style="max-width: 680px; margin: 0 auto; padding: 1.75rem;">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-3"
                     style="width:64px; height:64px; background:rgba(99,102,241,.15);">
                    <i class="bi bi-file-earmark-arrow-down fs-1" style="color:#818cf8;"></i>
                </div>
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="fw-bold fs-5">
                        GTMC-NPI v<?= htmlspecialchars($versione) ?>
                        <span class="badge bg-success ms-1">Ultima versione</span>
                    </div>
                    <div class="small text-muted">
                        <?php if ($dimensione): ?><?= htmlspecialchars($dimensione) ?> · <?php endif; ?>
                        <?php if ($data_uscita): ?><?= $data_uscita ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($changelog): ?>
            <details class="small mt-3">
                <summary style="cursor:pointer; color:#818cf8;">Novità di questa versione</summary>
                <div class="mt-2 text-muted" style="white-space:pre-line;"><?= htmlspecialchars($changelog) ?></div>
            </details>
            <?php endif; ?>

            <hr style="border-color:rgba(255,255,255,.12); margin:1.25rem 0;">

            <a href="/api/download-release.php"
               class="btn btn-primary btn-lg w-100"
               onclick="setTimeout(function(){ location.reload(); }, 1500);">
                <i class="bi bi-download me-2"></i>Scarica installer
            </a>

            <div class="small text-muted mt-3">
                <i class="bi bi-shield-check me-1"></i>
                Requisiti: Windows 10/11 o Windows Server 2019+, Microsoft SQL Server 2019+
                (Express supportato), ODBC Driver 18 for SQL Server.
            </div>
        </div>

        <?php else: ?>

        <div class="glass-card text-center" style="max-width: 680px; margin: 0 auto; padding: 2.5rem 1.75rem;">
            <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
            <p class="mt-3 mb-0">
                Nessuna versione disponibile al momento.<br>
                Contatta <a href="mailto:info@gtmc-npi.eu">info@gtmc-npi.eu</a>.
            </p>
        </div>

        <?php endif; ?>

        <div class="glass-card" style="max-width: 680px; margin: 1.25rem auto 0; padding: 1.25rem 1.75rem;">
            <h2 class="h6 fw-bold mb-2">
                <i class="bi bi-key me-2"></i>L'installer chiede la chiave di licenza
            </h2>
            <p class="small text-muted mb-2">
                Al passo <em>«Licență / Licenza»</em> del programma di installazione viene
                richiesta la chiave <code>NPI-XXXXX-XXXXX-XXXXX-XXXXX</code>. Se hai
                richiesto la demo, l'hai ricevuta <strong>via email</strong> subito dopo
                aver aperto il link di conferma — ed è visibile anche nella pagina di
                conferma, con il pulsante per copiarla.
            </p>
            <p class="small text-muted mb-0">
                Non l'hai ricevuta? Controlla la cartella spam, riapri l'email di
                conferma, oppure <a href="/demo.php" style="color:#818cf8;">ripeti la richiesta demo</a>:
                la chiave viene rispedita al link di conferma.
            </p>
        </div>

        <div class="glass-card" style="max-width: 680px; margin: 1.25rem auto 0; padding: 1.25rem 1.75rem;">
            <h2 class="h6 fw-bold mb-2">
                <i class="bi bi-arrow-repeat me-2"></i>Hai già un'installazione?
            </h2>
            <p class="small text-muted mb-0">
                Gli aggiornamenti non si scaricano da questa pagina: l'applicazione li
                controlla da sola all'avvio e li scarica con una licenza attiva.
                L'installer qui sopra serve solo per installazioni nuove.
            </p>
        </div>

        <div class="text-center mt-4 small text-muted">
            <i class="bi bi-envelope me-1"></i>
            Per assistenza: <a href="mailto:info@gtmc-npi.eu">info@gtmc-npi.eu</a>
        </div>

    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
