<?php
/**
 * releases_admin.php — la logica della pagina che pubblica le versioni.
 *
 * PERCHE' STA QUI E NON DENTRO LA PAGINA
 * --------------------------------------
 * Perche' cosi' si puo' provare. Le funzioni che decidono qualcosa —
 * se un pacchetto e' accettabile, come si chiama, quali piani sono validi —
 * ricevono valori e restituiscono valori: nessuna tocca la sessione, l'HTTP
 * o i file caricati. Le prove stanno in tests/test_releases_admin.php e
 * girano senza database e senza server.
 *
 * IL VINCOLO CHE GUIDA TUTTO
 * --------------------------
 * `api/download-update.php` cerca il file a un nome preciso:
 *
 *     uploads/updates/GTMC-NPI-Update-v<versione>.zip
 *
 * Se il nome non combacia, il sito annuncia una versione e poi risponde
 * «Update file not found». E' gia' successo con GTMC-MES-Update-v1.4.0.zip.
 * Per questo il nome NON si sceglie: si calcola dalla versione, sempre con
 * la stessa funzione, sia quando si carica sia quando si consegna.
 */

/*
 * Si richiede solo releases.php, che non ha dipendenze e non fa niente
 * all'atto dell'inclusione. `db.php` NON si richiede da qui: tirerebbe
 * dentro config.php, che apre la sessione e legge il .env, e le prove
 * dovrebbero far finta di essere un server web per poter girare. Le
 * pagine che usano questo file includono db.php per conto loro.
 */
require_once __DIR__ . '/releases.php';

/** Dove vivono i pacchetti di aggiornamento. */
function cartella_aggiornamenti() {
    return __DIR__ . '/../uploads/updates/';
}

/** Dove vivono le installazioni complete. */
function cartella_installazioni() {
    return __DIR__ . '/../uploads/releases/';
}

/**
 * Il nome del file per una versione. Unica fonte di verita': se cambia
 * qui, cambia ovunque.
 *
 * Questo sito e' gtmc-npi.eu e distribuisce GTMC-NPI: il nome
 * PRIMARIO e' quello che producono build.py e upload_release.py
 * (GTMC-NPI-*). I nomi Servanga restano solo come alias accettati
 * per i file eventualmente caricati durante il rebrand.
 */
function nome_pacchetto($versione) {
    return 'GTMC-NPI-Update-v' . $versione . '.zip';
}

/** Nomi alternativi accettati per la stessa versione (alias Servanga del rebrand). */
function candidati_pacchetto($versione) {
    return [
        'GTMC-Servanga-App-v' . $versione . '.zip',
        'GTMC-Servanga-Update-v' . $versione . '.zip',
    ];
}

/**
 * Il nome del pacchetto di aggiornamento Docker (immagine `docker save`).
 *
 * Con la distribuzione via container l'aggiornamento non e' piu' uno zip
 * di codice ma l'immagine dell'app esportata: il client la applica con
 * `docker load`. Stessa cartella, stessa regola del nome calcolato dalla
 * versione — solo l'estensione cambia.
 */
function nome_pacchetto_docker($versione) {
    return 'GTMC-NPI-Update-v' . $versione . '.tar.gz';
}

function candidati_pacchetto_docker($versione) {
    return ['GTMC-Servanga-Update-v' . $versione . '.tar.gz'];
}

/**
 * Il percorso del pacchetto di aggiornamento che esiste davvero per una
 * versione: tar.gz (Docker) se c'e', altrimenti zip.
 *
 * Una versione esce in UNO dei due formati, mai in entrambi; chi consegna
 * non puo' sapere quale ha scelto chi ha pubblicato, quindi guarda il
 * disco. Se non c'e' nessuno dei due restituisce il percorso dello zip,
 * cosi' l'errore resta quello storico («Update file not found» sul nome
 * documentato) e non un percorso che non e' mai stato caricato.
 */
function percorso_pacchetto_esistente($versione) {
    $nomi = array_merge(
        [nome_pacchetto_docker($versione), nome_pacchetto($versione)],
        candidati_pacchetto_docker($versione),
        candidati_pacchetto($versione)
    );
    foreach ($nomi as $nome) {
        $percorso = cartella_aggiornamenti() . $nome;
        if (is_file($percorso)) {
            return $percorso;
        }
    }
    return percorso_pacchetto($versione);
}

/**
 * Il nome dell'installazione completa.
 *
 * PERCHE' VERSIONATO. Prima si chiamava `GTMC-NPI-Setup.exe`, senza
 * numero: chi lo aveva scaricato non poteva sapere quale versione avesse
 * in mano, e sul server ogni pubblicazione cancellava la precedente. Se
 * una versione va ritirata, con un nome solo non c'e' niente a cui tornare.
 */
function nome_installatore($versione) {
    return 'GTMC-NPI-Setup-v' . $versione . '.exe';
}

function candidati_installatore($versione) {
    return [
        'GTMC-Servanga-Setup-v' . $versione . '.exe',   // alias del rebrand
        'GTMC-NPI-Setup.exe',                      // nome senza versione ( storico)
    ];
}

function percorso_pacchetto($versione) {
    return cartella_aggiornamenti() . nome_pacchetto($versione);
}

function percorso_installatore($versione) {
    foreach (array_merge([nome_installatore($versione)],
                         candidati_installatore($versione)) as $nome) {
        $percorso = cartella_installazioni() . $nome;
        if (is_file($percorso)) {
            return $percorso;
        }
    }
    return cartella_installazioni() . nome_installatore($versione);
}

/**
 * Nome e percorso giusti a seconda del tipo di release.
 *
 * Un aggiornamento e' uno zip di codice; un'installazione completa e'
 * l'eseguibile che si porta dietro Python e i prerequisiti. Sono due cose
 * diverse, in due cartelle diverse, e questa e' l'unica funzione che sa
 * quale sia quale.
 */
function nome_del_file($versione, $e_aggiornamento) {
    return $e_aggiornamento ? nome_pacchetto($versione)
                            : nome_installatore($versione);
}

function percorso_del_file($versione, $e_aggiornamento) {
    return $e_aggiornamento ? percorso_pacchetto($versione)
                            : percorso_installatore($versione);
}

function cartella_pubblica($e_aggiornamento) {
    return $e_aggiornamento ? '/uploads/updates/' : '/uploads/releases/';
}

/**
 * La dimensione da scrivere in `releases.file_size`, che e' un VARCHAR
 * mostrato all'utente ("12.7 MB"), non un numero.
 */
function dimensione_leggibile($byte) {
    $byte = (int)$byte;
    if ($byte >= 1048576) {
        $mb = $byte / 1048576;
        $testo = number_format($mb, 1, '.', '');
        if (substr($testo, -2) === '.0') {
            $testo = substr($testo, 0, -2);
        }
        return $testo . ' MB';
    }
    if ($byte >= 1024) {
        return round($byte / 1024) . ' KB';
    }
    return $byte . ' byte';
}

/**
 * Il limite reale di caricamento di questo hosting, in byte.
 *
 * PERCHE' SERVE SAPERLO. Il pacchetto pesa una dozzina di megabyte, e su
 * molti hosting condivisi `upload_max_filesize` e' fermo a 2 MB. PHP in quel
 * caso non da' un errore: consegna semplicemente un file vuoto con
 * UPLOAD_ERR_INI_SIZE, e chi carica pensa di aver pubblicato. Meglio dirlo
 * prima, in cima alla pagina, e offrire l'altra strada (FTP + «registra un
 * pacchetto gia' presente»).
 */
function limite_caricamento() {
    $valori = [_in_byte(ini_get('upload_max_filesize')),
               _in_byte(ini_get('post_max_size'))];
    $valori = array_filter($valori, function ($v) { return $v > 0; });
    return $valori ? min($valori) : 0;
}

function _in_byte($dimensione) {
    $dimensione = trim((string)$dimensione);
    if ($dimensione === '') {
        return 0;
    }
    $ultimo = strtolower(substr($dimensione, -1));
    $numero = (float)$dimensione;
    switch ($ultimo) {
        case 'g': return (int)($numero * 1073741824);
        case 'm': return (int)($numero * 1048576);
        case 'k': return (int)($numero * 1024);
    }
    return (int)$numero;
}

/**
 * Controlla i campi del modulo. Restituisce l'elenco degli errori: vuoto
 * significa che si puo' salvare.
 *
 * `$versioni_esistenti` serve a non creare un doppione: l'indice UNIQUE
 * (version, is_update) lo vieterebbe comunque, ma cosi' l'errore arriva
 * come frase leggibile invece che come eccezione PDO. Il controllo vale
 * anche in modifica: e' il CHIAMANTE che esclude dai `$versioni_esistenti`
 * la riga che si sta modificando (la sua versione resta sua), mentre
 * cambiarla verso una versione gia' occupata dallo stesso tipo resta
 * vietato.
 */
function valida_release($dati, $versioni_esistenti = [], $id_in_modifica = null) {
    $errori = [];

    $versione = trim($dati['version'] ?? '');
    if ($versione === '') {
        $errori[] = 'La versione e obbligatoria.';
    } elseif (!versione_ben_formata($versione)) {
        $errori[] = 'Versione non valida: sono ammessi solo cifre, punti, '
                  . 'trattini e lettere, e deve iniziare con una cifra.';
    } elseif (in_array($versione, $versioni_esistenti, true)) {
        $errori[] = 'La versione ' . $versione . ' esiste gia.';
    }

    $data = trim($dati['release_date'] ?? '');
    if ($data !== '' && !_data_ben_formata($data)) {
        $errori[] = 'La data deve essere nel formato AAAA-MM-GG.';
    }

    return $errori;
}

function _data_ben_formata($data) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return false;
    }
    list($a, $m, $g) = array_map('intval', explode('-', $data));
    return checkdate($m, $g, $a);
}

/**
 * Il formato del pacchetto caricato: 'targz' (immagine docker) o 'zip'
 * per gli aggiornamenti, 'exe' per le installazioni complete.
 *
 * La pagina staff non tocca il nome del file caricato — la destinazione si
 * calcola sempre dalla versione — quindi l'unica cosa che serve leggere
 * dal nome e' il formato, e sta in questa funzione, provabile come tutto
 * il resto. La firma vera del file resta affare di valida_pacchetto_caricato.
 */
function tipo_pacchetto_caricato($file, $e_aggiornamento) {
    if (!$e_aggiornamento) {
        return 'exe';
    }
    return preg_match('/\.tar\.gz$/i', (string)($file['name'] ?? ''))
        ? 'targz' : 'zip';
}

/**
 * Controlla il file caricato. Restituisce [ok, errore].
 *
 * Non si fida dell'estensione ne' del tipo dichiarato dal browser: apre
 * l'archivio e verifica che sia davvero uno zip. Un file non-zip
 * pubblicato come aggiornamento significa un cliente che scarica e non
 * riesce piu' ad aggiornare.
 */
function valida_pacchetto_caricato($file, $limite = null, $tipo = 'zip') {
    if (!is_array($file) || !isset($file['error'])) {
        return [false, 'Nessun file ricevuto.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, 'Nessun file selezionato.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE
            || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $limite = $limite === null ? limite_caricamento() : $limite;
        return [false, 'Il file supera il limite di caricamento di questo '
                     . 'server (' . dimensione_leggibile($limite) . '). '
                     . 'Caricalo via FTP in uploads/updates/ e usa '
                     . '«registra un pacchetto gia presente».'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Caricamento non riuscito (codice ' . $file['error'] . ').'];
    }
    if (empty($file['size'])) {
        return [false, 'Il file caricato e vuoto.'];
    }
    $atteso = $tipo === 'exe' ? '.exe'
            : ($tipo === 'targz' ? '.tar.gz' : '.zip');
    $coda = strtolower($file['name'] ?? '');
    if (substr($coda, -strlen($atteso)) !== $atteso) {
        return [false, 'Il file deve essere un ' . $atteso . '.'];
    }
    if ($tipo === 'exe') {
        if (!_e_un_eseguibile($file['tmp_name'] ?? '')) {
            return [false, 'Il file non e un eseguibile Windows.'];
        }
        return [true, null];
    }
    if ($tipo === 'targz') {
        if (!_e_un_gzip($file['tmp_name'] ?? '')) {
            return [false, 'Il file non e un archivio tar.gz valido '
                         . '(manca la firma gzip).'];
        }
        return [true, null];
    }
    if (!_e_uno_zip($file['tmp_name'] ?? '')) {
        return [false, 'Il file non e un archivio zip valido.'];
    }
    return [true, null];
}

/**
 * Un eseguibile Windows comincia per "MZ". Non e' una garanzia di niente —
 * chiunque puo' mettere due byte in cima a un file — ma intercetta lo
 * sbaglio vero: caricare lo zip al posto del setup, o un file troncato.
 */
function _e_un_eseguibile($percorso) {
    if (!$percorso || !is_readable($percorso)) {
        return false;
    }
    $f = @fopen($percorso, 'rb');
    if (!$f) {
        return false;
    }
    $firma = fread($f, 2);
    fclose($f);
    return $firma === 'MZ';
}

function _e_uno_zip($percorso) {
    if (!$percorso || !is_readable($percorso)) {
        return false;
    }
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $esito = $zip->open($percorso, ZipArchive::CHECKCONS);
        if ($esito === true) {
            $zip->close();
            return true;
        }
        return false;
    }
    // Senza l'estensione zip resta la firma: "PK\x03\x04".
    $f = @fopen($percorso, 'rb');
    if (!$f) {
        return false;
    }
    $firma = fread($f, 4);
    fclose($f);
    return $firma === "PK\x03\x04";
}

/**
 * Un file gzip comincia con 0x1F 0x8B. Basta per intercettare lo sbaglio
 * vero sul pacchetto docker (uno zip caricato col nome .tar.gz, o un file
 * troncato): l'integrita' vera del tar la verifichera' `docker load` sul
 * client, che non parte proprio se l'immagine e' corrotta.
 */
function _e_un_gzip($percorso) {
    if (!$percorso || !is_readable($percorso)) {
        return false;
    }
    $f = @fopen($percorso, 'rb');
    if (!$f) {
        return false;
    }
    $firma = fread($f, 2);
    fclose($f);
    return $firma === "\x1f\x8b";
}

/**
 * La versione dichiarata dentro il pacchetto, se c'e'.
 *
 * `crea_pacchetto_aggiornamento.py` mette `aggiornamento.json` nella radice
 * dello zip. Confrontarla con la versione scritta nel modulo intercetta lo
 * sbaglio piu' facile da fare e piu' difficile da scoprire: pubblicare il
 * pacchetto della 1.5.0 sotto il numero 1.6.0.
 *
 * Il pacchetto docker dichiara la versione nell'unico posto in cui sta:
 * il tag dell'immagine, `gtmc-npi:<versione>`, riportato dal
 * `manifest.json` che `docker save` mette nella radice del tar.
 */
function versione_dentro_il_pacchetto($percorso, $tipo = 'zip') {
    if ($tipo === 'targz') {
        return _versione_dentro_immagine_docker($percorso);
    }
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($percorso) !== true) {
        return null;
    }
    $grezzo = $zip->getFromName('aggiornamento.json');
    $zip->close();
    if ($grezzo === false) {
        return null;
    }
    $dati = json_decode($grezzo, true);
    if (!is_array($dati) || empty($dati['versione'])) {
        return null;
    }
    return (string)$dati['versione'];
}

/**
 * Best-effort: qualunque ostacolo (Phar assente, zlib assente, tar con
 * layout inatteso) restituisce null, che significa «non posso controllare»
 * — mai un rifiuto di pubblicazione per un controllo opzionale.
 */
function _versione_dentro_immagine_docker($percorso) {
    if (!class_exists('PharData') || !$percorso || !is_readable($percorso)) {
        return null;
    }
    // PharData riconosce il formato dal suffisso: il file caricato via HTTP
    // sta nella cartella tmp di PHP senza estensione, quindi si copia col
    // nome giusto prima di aprirlo.
    $da_aprire = $percorso;
    $copia = null;
    if (!preg_match('/\.tar\.gz$/i', $percorso)) {
        $copia = tempnam(sys_get_temp_dir(), 'pkg') . '.tar.gz';
        if (!@copy($percorso, $copia)) {
            return null;
        }
        $da_aprire = $copia;
    }
    try {
        $tar = @new PharData($da_aprire);
        if (!isset($tar['manifest.json'])) {
            return null;
        }
        $manifesto = (string)$tar['manifest.json']->getContent();
        if ($copia !== null) {
            @unlink($copia);
        }
        // RepoTags: ["gtmc-npi:1.5.0"]. Il tag e' la versione del
        // modulo, quindi e' con quella che va confrontato.
        if (preg_match('/gtmc-npi:v?([0-9][0-9A-Za-z.\-]*)/i',
                       $manifesto, $m)) {
            return $m[1];
        }
        return null;
    } catch (Throwable $e) {
        if ($copia !== null) {
            @unlink($copia);
        }
        return null;
    }
}

/**
 * I piani scelti nel modulo, ridotti a quelli che esistono davvero.
 *
 * Un elenco VUOTO significa «per tutti»: e' la convenzione di
 * release_spetta_al_piano(), ed e' quella che rende innocua l'introduzione
 * della tabella su un sito gia' in esercizio.
 */
function piani_scelti($inviati, $piani_validi) {
    if (!is_array($inviati)) {
        return [];
    }
    $validi = array_map('intval', $piani_validi);
    $scelti = [];
    foreach ($inviati as $id) {
        $id = (int)$id;
        if (in_array($id, $validi, true) && !in_array($id, $scelti, true)) {
            $scelti[] = $id;
        }
    }
    sort($scelti);
    // Se sono stati scelti TUTTI i piani, il significato e' «per tutti»:
    // salvarli tutti darebbe lo stesso risultato ma renderebbe la tabella
    // difficile da leggere, e un piano nuovo aggiunto domani resterebbe
    // fuori senza che nessuno se ne accorga.
    if (count($scelti) === count($validi) && count($validi) > 0) {
        return [];
    }
    return $scelti;
}


// ── Lettura e scrittura ─────────────────────────────────────────────────

function elenco_piani() {
    return db_fetch_all(
        'SELECT id, slug, name FROM plans WHERE is_active = 1 ORDER BY sort_order, id'
    );
}

function elenco_release() {
    return db_fetch_all('SELECT * FROM releases ORDER BY created_at DESC, id DESC');
}

function salva_piani_della_release($release_id, $plan_ids) {
    db_query('DELETE FROM release_plans WHERE release_id = ?', [(int)$release_id]);
    foreach ($plan_ids as $plan_id) {
        db_query(
            'INSERT INTO release_plans (release_id, plan_id) VALUES (?, ?)',
            [(int)$release_id, (int)$plan_id]
        );
    }
}

/**
 * L'utente a cui attribuire un download.
 *
 * IL DIFETTO CHIUSO: `downloads.user_id` e' NOT NULL senza valore
 * predefinito, e download-update.php faceva la INSERT senza passarlo. La
 * query falliva sempre, dentro un try/catch: il download funzionava e la
 * tabella restava vuota, quindi «nessuno ha scaricato» e «il tracciamento
 * e' rotto» erano indistinguibili.
 */
/**
 * L'installazione completa piu' recente fra quelle attive, o null.
 *
 * PERCHE' NON SI ORDINA IN SQL. `releases.version` e' un VARCHAR: MySQL
 * confronta carattere per carattere, quindi '1.10.0' risulterebbe minore
 * di '1.9.0' e la pagina pubblica offrirebbe una versione vecchia. Si
 * leggono le righe attive e si ordina in PHP con version_compare, come
 * fanno gia' check-update.php e download-update.php.
 */
function ultima_installazione() {
    try {
        $righe = db_fetch_all(
            'SELECT * FROM releases WHERE is_active = 1 AND is_update = 0');
    } catch (Throwable $e) {
        error_log('Elenco installazioni non disponibile: ' . $e->getMessage());
        return null;
    }
    return piu_recente_fra($righe);
}

/**
 * La riga con la versione piu' alta. Separata dalla query apposta: cosi'
 * la regola che conta — quale versione vince — si prova senza un MySQL.
 */
function piu_recente_fra($righe) {
    if (!$righe) {
        return null;
    }
    $migliore = null;
    foreach ($righe as $riga) {
        if ($migliore === null
                || release_e_piu_recente($riga['version'], $migliore['version'])) {
            $migliore = $riga;
        }
    }
    return $migliore;
}


function utente_della_licenza($license_id) {
    if (!$license_id) {
        return null;
    }
    $riga = db_fetch('SELECT user_id FROM licenses WHERE id = ?', [(int)$license_id]);
    return $riga ? (int)$riga['user_id'] : null;
}
