<?php
/**
 * check-update.php — «esiste una versione piu' nuova della mia?»
 *
 * Risponde all'applicazione installata dal cliente. La licenza qui e'
 * facoltativa: sapere CHE esiste un aggiornamento non e' un privilegio, e
 * rifiutare la risposta a chi ha la licenza scaduta gli nasconderebbe
 * proprio l'informazione che lo riguarda. A essere protetto e' il
 * download (download-update.php), non l'elenco.
 *
 * Se pero' la licenza c'e' ed e' valida, l'elenco viene filtrato sul piano:
 * non ha senso annunciare una versione che poi non si potra' scaricare.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/releases.php';
require_once __DIR__ . '/../includes/releases_admin.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$version       = $_GET['version'] ?? null;
$machine_id    = $_GET['machine_id'] ?? null;
$license_token = $_GET['license_token'] ?? null;

if (!$version) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing version parameter']);
    exit;
}
if (!versione_ben_formata($version)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Malformed version parameter']);
    exit;
}

list($licenza_ok, $piano, $errore_licenza) =
    verifica_licenza($license_token, $machine_id);

/* Il confronto fra versioni NON si fa in SQL: MySQL confronta i VARCHAR
   carattere per carattere, e '1.10.0' risulterebbe minore di '1.9.0'. Si
   leggono le release attive e si confrontano in PHP con version_compare. */
$righe = db_fetch_all(
    "SELECT * FROM releases WHERE is_active = 1 AND is_update = 1"
);

$piani_per_release = carica_piani_per_release();
$disponibili = release_disponibili(
    $righe, $version, $piani_per_release, $licenza_ok ? $piano : null
);

$updates = [];
foreach ($disponibili as $release) {
    /* L'impronta del pacchetto, quando chi pubblica carica anche il
       sidecar .sha256 (lo fa upload_release.py dell'installer): senza
       questo campo il cliente scarica senza poter verificare cio' che
       ha ricevuto. Manca il sidecar -> null, non si inventa nulla. */
    $sha256 = null;
    $percorso = percorso_pacchetto_esistente($release['version']);
    if ($percorso && is_file($percorso . '.sha256')) {
        $parti = preg_split('/\s+/',
                            trim((string)file_get_contents($percorso . '.sha256')));
        if ($parti && preg_match('/^[0-9a-f]{64}$/i', $parti[0])) {
            $sha256 = strtolower($parti[0]);
        }
    }
    $updates[] = [
        'version'          => $release['version'],
        'type'             => 'update',
        'title'            => $release['version'],
        'release_date'     => $release['release_date'] ?? null,
        'changelog'        => $release['changelog'],
        'download_url'     => SITE_URL . '/api/download-update.php?version='
                              . urlencode($release['version']),
        'file_size'        => $release['file_size'],
        'min_requirements' => $release['min_requirements'],
        'mandatory'        => !empty($release['is_mandatory']),
        'sha256'           => $sha256,
    ];
}

echo json_encode([
    'ok'              => true,
    'license_valid'   => $licenza_ok,
    'license_error'   => $errore_licenza,
    'plan'            => $licenza_ok ? $piano : null,
    'current_version' => $version,
    'latest_version'  => $updates ? $updates[0]['version'] : $version,
    'update_available' => count($updates) > 0,
    'updates'         => $updates,
]);
