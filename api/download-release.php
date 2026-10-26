<?php
/**
 * download-release.php — consegna l'installazione completa.
 *
 * E' l'unica strada per arrivare al file: `uploads/releases/` e
 * `uploads/updates/` sono chiuse dal .htaccess, cosi' non si puo' piu'
 * scaricare un pacchetto indovinandone il nome. Serviva: il vecchio
 * `GTMC-NPI-App.zip` stava li' dentro, raggiungibile da chiunque
 * conoscesse l'indirizzo, e conteneva un PDF con le credenziali dei
 * dipendenti finito nel pacchetto per sbaglio.
 *
 * QUI LA LICENZA NON SERVE, ed e' voluto: l'installazione completa e' il
 * prodotto che si scarica per provarlo, e senza una licenza non fa nulla
 * comunque — l'attivazione avviene durante l'installazione. A essere
 * protetti sono gli AGGIORNAMENTI (download-update.php), che sono il
 * servizio che il cliente paga.
 *
 * Quello che si controlla e' che il file esista e che la release sia
 * attiva: una versione ritirata non deve continuare a uscire.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/releases.php';
require_once __DIR__ . '/../includes/releases_admin.php';

function rifiuta_release($codice, $messaggio) {
    http_response_code($codice);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $messaggio]);
    exit;
}

$version = $_GET['version'] ?? null;

/* Nessuna versione: si consegna la piu' recente pubblicata. E' il caso
   normale — il bottone della pagina pubblica passa di qui. */
if ($version === null || $version === '') {
    $release = ultima_installazione();
    if (!$release) {
        rifiuta_release(404, 'No installer published');
    }
    $version = $release['version'];
} else {
    /* La versione finisce dentro un percorso di file: si controlla la
       forma prima di usarla, non dopo. */
    if (!versione_ben_formata($version)) {
        rifiuta_release(400, 'Malformed version parameter');
    }
    $release = db_fetch(
        'SELECT * FROM releases
          WHERE version = ? AND is_active = 1 AND is_update = 0',
        [$version]
    );
    if (!$release) {
        rifiuta_release(404, 'Version not found');
    }
}

$nome_file = nome_installatore($version);
$percorso  = percorso_installatore($version);

if (!is_file($percorso)) {
    /* Fallback: il percorso registrato in `releases.file_path` (compilato
       dal portale staff quando la release viene pubblicata). Consente
       nomi file liberi senza rinominare a mano sul server. */
    if (!empty($release['file_path'])) {
        $candidato = dirname(__DIR__) . $release['file_path'];
        if (is_file($candidato)) {
            $percorso  = $candidato;
            $nome_file = basename($candidato);
        }
    }
}

if (!is_file($percorso)) {
    error_log('Installer mancante per la release ' . $version . ': ' . $percorso);
    rifiuta_release(404, 'Installer file not found');
}

/* Niente riga in `downloads`: quella tabella ha `user_id` NOT NULL e qui
   non c'e' nessun utente autenticato. Meglio una riga nel log del server
   che una INSERT che fallisce a ogni download. */
error_log('Download installer ' . $version . ' da '
          . ($_SERVER['REMOTE_ADDR'] ?? '?'));

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nome_file . '"');
header('Content-Length: ' . filesize($percorso));
header('X-Content-Type-Options: nosniff');
readfile($percorso);
exit;
