<?php
/**
 * download-update.php — consegna il pacchetto di aggiornamento.
 *
 * LA LICENZA E' OBBLIGATORIA, e questa e' la differenza rispetto a prima.
 * La verifica stava dentro `if ($license_token && $machine_id)`: chiamare
 * l'indirizzo SENZA parametri saltava il controllo e scaricava. Su un
 * prodotto a pagamento significa che bastava conoscere il numero di
 * versione per avere l'aggiornamento.
 *
 * Il filtro sul piano e' lo stesso di check-update.php, e viene dalla
 * stessa funzione: se le due risposte divergessero, si finirebbe per
 * annunciare aggiornamenti che poi vengono rifiutati, o peggio per
 * consegnarne di non spettanti.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/releases.php';
require_once __DIR__ . '/../includes/releases_admin.php';
require_once __DIR__ . '/../includes/license_lib.php';

function rifiuta($codice, $messaggio) {
    http_response_code($codice);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $messaggio]);
    exit;
}

$version       = $_GET['version'] ?? null;
$license_token = $_GET['license_token'] ?? null;
$machine_id    = $_GET['machine_id'] ?? null;

if (!$version) {
    rifiuta(400, 'Missing version parameter');
}
/* La versione finisce dentro un percorso di file: si controlla la forma
   prima di usarla, non dopo. */
if (!versione_ben_formata($version)) {
    rifiuta(400, 'Malformed version parameter');
}

list($licenza_ok, $piano, $errore_licenza) =
    verifica_licenza($license_token, $machine_id);
if (!$licenza_ok) {
    rifiuta(403, $errore_licenza ?: 'Valid license required');
}

$release = db_fetch(
    "SELECT * FROM releases WHERE version = ? AND is_active = 1 AND is_update = 1",
    [$version]
);
if (!$release) {
    rifiuta(404, 'Version not found');
}

$piani_per_release = carica_piani_per_release();
if (!release_spetta_al_piano($release, $piani_per_release, $piano)) {
    /* 403 e non 404: il pacchetto esiste, semplicemente non spetta a
       questa licenza. Dirlo chiaramente evita una segnalazione di guasto
       per una cosa che e' una scelta commerciale. */
    rifiuta(403, 'This update is not available for your license plan');
}

/* Il nome NON si costruisce qui. Lo calcolano nome_pacchetto() /
   nome_pacchetto_docker(), le stesse funzioni che usa la pagina staff
   quando carica il pacchetto: due formule separate per lo stesso nome
   finiscono per divergere, e quando divergono il sito annuncia una
   versione che poi non riesce a consegnare.
   Una versione esce o come zip di codice o come immagine docker
   (.tar.gz): percorso_pacchetto_esistente() guarda quale dei due e'
   stato davvero caricato. */
$zip_path = percorso_pacchetto_esistente($version);
if (!file_exists($zip_path)) {
    error_log('Pacchetto mancante per la release ' . $version . ': ' . $zip_path);
    rifiuta(404, 'Update file not found');
}
$nome_file = basename($zip_path);

// Traccia del download: chi, cosa, quando.
// Il token RSA contiene la chiave di licenza ('key'), non l'id.
$license_id = null;
$parti = explode('.', (string)$license_token);
if (count($parti) === 2) {
    $json = base64_decode(strtr($parti[0], '-_', '+/'));
    $payload = json_decode((string)$json, true);
    if (is_array($payload)) {
        $license_id = $payload['license_id'] ?? null;
        if (!$license_id && !empty($payload['key']) && function_exists('lic_find')) {
            list($lic_row) = lic_find(strtoupper($payload['key']));
            $license_id = $lic_row['id'] ?? null;
        }
    }
}

/* IL DIFETTO CHIUSO: `downloads.user_id` e' NOT NULL senza valore
   predefinito, e questa INSERT non lo passava. Falliva SEMPRE, dentro il
   try/catch: il download funzionava, la tabella restava vuota, e «nessuno
   ha scaricato» era indistinguibile da «il tracciamento e' rotto». */
$user_id = utente_della_licenza($license_id);
if ($user_id === null) {
    error_log('Download non tracciato: nessun utente per la licenza '
              . var_export($license_id, true));
} else {
    try {
        db_insert(
            "INSERT INTO downloads (user_id, license_id, release_id,
                                    downloaded_at, ip_address)
             VALUES (?, ?, ?, NOW(), ?)",
            [$user_id, $license_id, $release['id'],
             $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (Exception $e) {
        error_log('Traccia del download non registrata: ' . $e->getMessage());
    }
}

header('Content-Type: ' . (substr($nome_file, -7) === '.tar.gz'
    ? 'application/gzip'
    : 'application/zip'));
header('Content-Disposition: attachment; filename="' . $nome_file . '"');
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);
exit;
