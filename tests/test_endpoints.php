<?php
/**
 * Prova gli ENDPOINT, non solo la logica.
 *
 * Uso:  php tests/test_endpoints.php
 *
 * PERCHE' SERVE UN FILE A PARTE
 * -----------------------------
 * test_releases.php verifica le funzioni pure. Ma la falla che ho chiuso
 * non stava in una funzione: stava in un `if` dentro download-update.php
 * («verifica la licenza SE i parametri ci sono»). Una prova che non esegue
 * lo script non puo' vederla — e infatti, provando a rimettere quel difetto,
 * test_releases.php restava verde.
 *
 * Qui gli script vengono INCLUSI davvero, con il database e la verifica
 * della licenza sostituiti da doppi, e si guarda cosa rispondono.
 */

define('SITE_URL', 'https://esempio.invalid');
define('PROVA_ENDPOINT', true);

$falliti = 0; $eseguiti = 0;

function prova($descrizione, $condizione) {
    global $falliti, $eseguiti;
    $eseguiti++;
    echo($condizione ? "  ok       $descrizione\n"
                     : "  FALLITO  $descrizione\n");
    if (!$condizione) $falliti++;
}

// ── I doppi ─────────────────────────────────────────────────────────────
$GLOBALS['_releases'] = [];
$GLOBALS['_vincoli']  = [];
$GLOBALS['_licenza']  = [false, null, 'Licenza mancante'];
$GLOBALS['_inserted'] = [];

function db_fetch_all($sql, $params = []) {
    /* Il confronto fra versioni NON deve tornare in SQL: MySQL confronta i
       VARCHAR carattere per carattere e '1.10.0' risulterebbe minore di
       '1.9.0'. Un doppio che ignora i parametri non se ne accorgerebbe, e
       il difetto rientrerebbe dalla finestra. */
    if (strpos($sql, 'FROM releases') !== false
        && preg_match('/version\s*[<>]/i', $sql)) {
        throw new RuntimeException(
            'Le release non vanno filtrate per versione in SQL: ' . $sql);
    }
    if (strpos($sql, 'release_plans') !== false) {
        $righe = [];
        foreach ($GLOBALS['_vincoli'] as $rid => $slugs) {
            foreach ($slugs as $s) $righe[] = ['release_id' => $rid, 'slug' => $s];
        }
        return $righe;
    }
    return $GLOBALS['_releases'];
}

function db_fetch($sql, $params = []) {
    foreach ($GLOBALS['_releases'] as $r) {
        if ($r['version'] === ($params[0] ?? null)) return $r;
    }
    return null;
}

function db_insert($sql, $params = []) {
    $GLOBALS['_inserted'][] = $params;
    return 1;
}

/* Di releases.php si vuole TUTTO tranne `verifica_licenza`, che parla con
   la rete. Si carica il sorgente togliendo quella sola funzione: il filtro
   sui piani e il confronto fra versioni restano quelli veri, ed e' li' che
   sta la logica da proteggere. */
$_sorgente_releases = file_get_contents(__DIR__ . '/../includes/releases.php');
$_sorgente_releases = preg_replace(
    '/function verifica_licenza\(.*?\n\}/s', '', $_sorgente_releases);
eval(preg_replace('/^<\?php/', '', $_sorgente_releases, 1));

function verifica_licenza($token, $machine) {
    return $GLOBALS['_licenza'];
}

/* Anche di releases_admin.php si vuole tutto tranne cio' che parla con
   MySQL: `utente_della_licenza` viene sostituita qui sotto. Serve perche'
   download-update.php ora ricava da li' il nome del pacchetto — se le due
   formule divergessero, il sito consegnerebbe un file che non esiste. */
$_sorgente_admin = file_get_contents(__DIR__ . '/../includes/releases_admin.php');
$_sorgente_admin = preg_replace(
    '/function utente_della_licenza\(.*?\n\}/s', '', $_sorgente_admin);
$_sorgente_admin = preg_replace('/^\s*require_once .*$/m', '', $_sorgente_admin);
eval(preg_replace('/^<\?php/', '', $_sorgente_admin, 1));

function utente_della_licenza($license_id) {
    return $GLOBALS['_utente_licenza'] ?? null;
}

function release($id, $versione) {
    return ['id' => $id, 'version' => $versione, 'is_active' => 1,
            'is_update' => 1, 'changelog' => 'note', 'file_size' => '10 MB',
            'min_requirements' => '', 'release_date' => '2026-01-01'];
}

/**
 * Esegue uno script di api/ con i parametri dati e restituisce
 * [codice http, corpo decodificato, corpo grezzo].
 *
 * In CLI non esistono davvero le intestazioni HTTP, e `http_response_code()`
 * protesta appena qualcosa e' stato stampato. Si sostituiscono le funzioni
 * che parlano col web server con due doppi, e si legge il codice da li'.
 */
function _codice($n = null) {
    if ($n !== null) $GLOBALS['_http'] = $n;
    return $GLOBALS['_http'];
}
function _header($h) { /* in CLI non serve */ }

function chiama($script, $parametri) {
    $_GET = $parametri;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $GLOBALS['_http'] = 200;

    $sorgente = file_get_contents(__DIR__ . '/../api/' . $script);
    $sorgente = preg_replace('/^<\?php/', '', $sorgente, 1);
    // Via i require: config, db e releases sono gia' caricati o sostituiti.
    $sorgente = preg_replace('/^\s*require_once .*$/m', '', $sorgente);
    // Le funzioni dichiarate dallo script si dichiarerebbero due volte alla
    // seconda chiamata: si tolgono e si rifanno qui sotto.
    $sorgente = preg_replace('/^function \w+\(.*?\n\}\n/ms', '', $sorgente);
    $sorgente = str_replace('http_response_code(', '_codice(', $sorgente);
    $sorgente = str_replace('header(', '_header(', $sorgente);
    // `exit` interromperebbe la prova: diventa un'eccezione.
    $sorgente = preg_replace('/\bexit;/', 'throw new RuntimeException("exit");',
                             $sorgente);

    ob_start();
    try {
        eval($sorgente);
    } catch (RuntimeException $e) {
        // uscita prevista
    }
    $corpo = ob_get_clean();
    return [_codice(), json_decode($corpo, true), $corpo];
}

/* Lo stesso `rifiuta` di download-update.php, che qui viene tolto dal
   sorgente per non ridichiararlo a ogni chiamata. */
function rifiuta($codice, $messaggio) {
    _codice($codice);
    echo json_encode(['ok' => false, 'error' => $messaggio]);
    throw new RuntimeException('exit');
}


echo "\n── check-update.php ────────────────────────────────────────\n";

$GLOBALS['_releases'] = [release(1, '1.9.0'), release(2, '1.10.0')];
$GLOBALS['_vincoli']  = [];
$GLOBALS['_licenza']  = [false, null, 'Licenza mancante'];

list($codice, $dati) = chiama('check-update.php', ['version' => '1.9.0']);
prova('senza licenza risponde comunque', $codice === 200 && $dati['ok'] === true);
prova('dalla 1.9.0 annuncia la 1.10.0',
      count($dati['updates']) === 1 && $dati['updates'][0]['version'] === '1.10.0');
prova('dichiara che la licenza non e valida', $dati['license_valid'] === false);

list($codice, $dati) = chiama('check-update.php', ['version' => '../../etc/passwd']);
prova('una versione malformata e rifiutata', $codice === 400);

list($codice, $dati) = chiama('check-update.php', []);
prova('senza versione e rifiutata', $codice === 400);

$GLOBALS['_vincoli'] = [2 => ['enterprise']];
$GLOBALS['_licenza'] = [true, 'basic', null];
list($codice, $dati) = chiama('check-update.php', [
    'version' => '1.9.0', 'license_token' => 't', 'machine_id' => 'm']);
prova('a un cliente Basic non si annuncia la release riservata',
      $dati['updates'] === [] && $dati['update_available'] === false);

$GLOBALS['_licenza'] = [true, 'enterprise', null];
list($codice, $dati) = chiama('check-update.php', [
    'version' => '1.9.0', 'license_token' => 't', 'machine_id' => 'm']);
prova('a un cliente Enterprise si annuncia',
      count($dati['updates']) === 1 && $dati['update_available'] === true);
prova('il piano viene riportato nella risposta', $dati['plan'] === 'enterprise');


echo "\n── download-update.php ─────────────────────────────────────\n";

// LA FALLA CHIUSA: la verifica stava dentro `if ($token && $machine)`, quindi
// chiamare senza parametri saltava il controllo e scaricava il pacchetto.
$GLOBALS['_vincoli'] = [];
$GLOBALS['_licenza'] = [false, null, 'Licenza mancante'];
list($codice, $dati, $corpo) = chiama('download-update.php', ['version' => '1.10.0']);
prova('SENZA licenza il download e rifiutato', $codice === 403);
prova('e non viene consegnato nessun pacchetto',
      strpos($corpo, 'PK') !== 0);

$GLOBALS['_licenza'] = [false, null, 'License expired'];
list($codice, $dati) = chiama('download-update.php', [
    'version' => '1.10.0', 'license_token' => 't', 'machine_id' => 'm']);
prova('con licenza scaduta il download e rifiutato', $codice === 403);
prova('e il motivo viene detto', $dati['error'] === 'License expired');

$GLOBALS['_licenza'] = [true, 'basic', null];
$GLOBALS['_vincoli'] = [2 => ['enterprise']];
list($codice, $dati) = chiama('download-update.php', [
    'version' => '1.10.0', 'license_token' => 't', 'machine_id' => 'm']);
prova('un piano non ammesso riceve 403, non 404', $codice === 403);
prova('con un messaggio che parla di licenza, non di file mancante',
      strpos(strtolower($dati['error']), 'license') !== false);

$GLOBALS['_licenza'] = [true, 'enterprise', null];
list($codice, $dati) = chiama('download-update.php', [
    'version' => '../../includes/config', 'license_token' => 't', 'machine_id' => 'm']);
prova('un percorso travestito da versione e rifiutato prima di tutto',
      $codice === 400);

list($codice, $dati) = chiama('download-update.php', [
    'version' => '9.9.9', 'license_token' => 't', 'machine_id' => 'm']);
prova('una versione inesistente da 404', $codice === 404);

// Con licenza valida e piano ammesso arriva fino alla ricerca del file:
// il pacchetto non esiste in questa prova, quindi 404 — ma DOPO i controlli.
$GLOBALS['_vincoli'] = [];
list($codice, $dati) = chiama('download-update.php', [
    'version' => '1.10.0', 'license_token' => 't', 'machine_id' => 'm']);
prova('con licenza valida si arriva alla consegna del file', $codice === 404);
prova('e il motivo e il file mancante, non la licenza',
      strpos(strtolower($dati['error']), 'file') !== false);


echo "\n── La traccia del download ─────────────────────────────────\n";

/* IL DIFETTO CHIUSO: `downloads.user_id` e' NOT NULL senza valore
   predefinito e la INSERT non lo passava. Falliva sempre, dentro un
   try/catch: il download funzionava e la tabella restava vuota, quindi
   «nessuno ha scaricato» e «il tracciamento e' rotto» erano
   indistinguibili. Qui il pacchetto esiste davvero, cosi' si arriva fino
   alla riga che registra. */
$cartella = __DIR__ . '/../uploads/updates/';
@mkdir($cartella, 0755, true);
$finto = $cartella . 'GTMC-NPI-Update-v1.10.0.zip';
file_put_contents($finto, "PK\x03\x04prova");

$GLOBALS['_inserted'] = [];
$GLOBALS['_utente_licenza'] = 42;
chiama('download-update.php', [
    'version' => '1.10.0', 'license_token' => 't', 'machine_id' => 'm']);
prova('il download viene registrato', count($GLOBALS['_inserted']) === 1);
prova('e la prima colonna e user_id',
      ($GLOBALS['_inserted'][0][0] ?? null) === 42);

/* Senza un utente la riga non si puo' scrivere: meglio non scriverla e
   lasciarne traccia nel log che tentare una INSERT che fallisce in
   silenzio a ogni download. */
$GLOBALS['_inserted'] = [];
$GLOBALS['_utente_licenza'] = null;
list($codice, ) = chiama('download-update.php', [
    'version' => '1.10.0', 'license_token' => 't', 'machine_id' => 'm']);
prova('senza utente non si tenta nessuna INSERT',
      $GLOBALS['_inserted'] === []);
prova('e il download avviene lo stesso', $codice === 200);

@unlink($finto);


echo "\n────────────────────────────────────────────────────────────\n";
if ($falliti === 0) { echo "Tutte le $eseguiti prove sono passate.\n\n"; exit(0); }
echo "$falliti prove fallite su $eseguiti.\n\n";
exit(1);
