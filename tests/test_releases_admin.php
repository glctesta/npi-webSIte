<?php
/**
 * La logica della pagina che pubblica le versioni.
 *
 * Uso:  php tests/test_releases_admin.php
 *
 * Niente database e niente server: le funzioni provate qui ricevono valori
 * e restituiscono valori. Quelle che parlano con MySQL stanno in fondo a
 * releases_admin.php e non sono provate da qui — sono tre righe di SQL
 * ciascuna, e il rischio non e' li'.
 *
 * COSA VA PROTETTO
 * ----------------
 * 1. IL NOME DEL FILE. `download-update.php` cerca esattamente
 *    `GTMC-NPI-Update-v<versione>.zip`. Ogni divergenza fra chi carica e chi
 *    consegna diventa un «Update file not found» per il cliente.
 * 2. CHE NON SI PUBBLICHI SPAZZATURA. Un file che non e' uno zip, o una
 *    versione con dentro `../`, o il pacchetto della 1.5.0 pubblicato come
 *    1.6.0.
 * 3. LA CONVENZIONE DEI PIANI. Nessuna riga = per tutti. Sbagliarla
 *    significa o nascondere una versione a chi l'ha pagata, o darla a chi
 *    non l'ha pagata.
 */

require_once __DIR__ . '/../includes/releases_admin.php';

$falliti = 0;
$eseguiti = 0;

function prova($descrizione, $condizione) {
    global $falliti, $eseguiti;
    $eseguiti++;
    if ($condizione) {
        echo "  ok       $descrizione\n";
    } else {
        echo "  FALLITO  $descrizione\n";
        $falliti++;
    }
}

function zip_finto($contenuto = null) {
    $percorso = tempnam(sys_get_temp_dir(), 'prova') . '.zip';
    if (!class_exists('ZipArchive')) {
        file_put_contents($percorso, "PK\x03\x04" . str_repeat("\0", 60));
        return $percorso;
    }
    $zip = new ZipArchive();
    $zip->open($percorso, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('VERSION', "1.6.0\n");
    if ($contenuto !== null) {
        $zip->addFromString('aggiornamento.json', $contenuto);
    }
    $zip->close();
    return $percorso;
}


echo "\n── Il nome del pacchetto ───────────────────────────────────\n";

// E' l'unico punto in cui il nome si forma. Se questa prova cambia, deve
// cambiare anche download-update.php, ed e' esattamente il motivo per cui
// c'e' una prova.
prova('il nome e GTMC-NPI-Update-v<versione>.zip',
      nome_pacchetto('1.6.0') === 'GTMC-NPI-Update-v1.6.0.zip');
prova('vale anche per una versione a due cifre',
      nome_pacchetto('1.10.0') === 'GTMC-NPI-Update-v1.10.0.zip');
prova('il percorso finisce in uploads/updates',
      strpos(str_replace('\\', '/', percorso_pacchetto('1.6.0')),
             'uploads/updates/GTMC-NPI-Update-v1.6.0.zip') !== false);


echo "\n── Installazione completa e aggiornamento ──────────────────\n";

// Sono due cose diverse, in due cartelle diverse. Confonderle significa
// cercare un .exe dove c'e' uno .zip, e rispondere «file not found» a un
// cliente che sta provando a installare.
prova('l installatore e versionato',
      nome_installatore('1.6.0') === 'GTMC-NPI-Setup-v1.6.0.exe');
prova('il tipo giusto per un aggiornamento',
      nome_del_file('1.6.0', true) === 'GTMC-NPI-Update-v1.6.0.zip');
prova('il tipo giusto per un installazione',
      nome_del_file('1.6.0', false) === 'GTMC-NPI-Setup-v1.6.0.exe');
prova('gli aggiornamenti vanno in uploads/updates',
      cartella_pubblica(true) === '/uploads/updates/');
prova('le installazioni vanno in uploads/releases',
      cartella_pubblica(false) === '/uploads/releases/');
prova('i due percorsi non coincidono mai',
      percorso_del_file('1.6.0', true) !== percorso_del_file('1.6.0', false));

// Il nome senza numero era il difetto: chi aveva scaricato
// GTMC-NPI-Setup.exe non poteva sapere quale versione avesse in mano,
// e ogni pubblicazione cancellava la precedente.
prova('due versioni diverse hanno due file diversi',
      nome_installatore('1.6.0') !== nome_installatore('1.7.0'));

// Quale installazione offre la pagina pubblica. Ordinare per stringa
// darebbe la 1.9.0 quando la piu' recente e' la 1.10.0: chi scarica si
// porta a casa una versione vecchia e non ha modo di accorgersene.
$candidate = [
    ['version' => '1.9.0'], ['version' => '1.10.0'], ['version' => '1.4.0'],
];
prova('si offre la 1.10.0, non la 1.9.0',
      piu_recente_fra($candidate)['version'] === '1.10.0');
prova('l ordine in cui arrivano non conta',
      piu_recente_fra(array_reverse($candidate))['version'] === '1.10.0');
prova('senza installazioni non si inventa niente',
      piu_recente_fra([]) === null);
prova('con una sola installazione si offre quella',
      piu_recente_fra([['version' => '1.4.0']])['version'] === '1.4.0');


echo "\n── La dimensione da mostrare ───────────────────────────────\n";

prova('13.297.552 byte diventano 12.7 MB',
      dimensione_leggibile(13297552) === '12.7 MB');
prova('un numero tondo non porta lo .0',
      dimensione_leggibile(10 * 1048576) === '10 MB');
prova('sotto il megabyte si contano i KB',
      dimensione_leggibile(4096) === '4 KB');
prova('sotto il kilobyte si contano i byte',
      dimensione_leggibile(500) === '500 byte');
prova('zero non esplode', dimensione_leggibile(0) === '0 byte');


echo "\n── I campi del modulo ──────────────────────────────────────\n";

prova('una release normale passa',
      valida_release(['version' => '1.6.0', 'release_date' => '2026-08-21']) === []);
prova('senza versione non si salva',
      valida_release(['version' => '']) !== []);
prova('una versione con ../ viene rifiutata',
      valida_release(['version' => '../../etc/passwd']) !== []);
prova('una versione che non inizia con una cifra viene rifiutata',
      valida_release(['version' => 'v1.6.0']) !== []);
prova('un doppione viene detto prima che lo dica MySQL',
      valida_release(['version' => '1.5.0'], ['1.4.0', '1.5.0']) !== []);
prova('in modifica la propria versione non e un doppione (il chiamante la esclude)',
      valida_release(['version' => '1.5.0'], ['1.4.0'], 7) === []);
prova('ma cambiare versione su una gia occupata dallo stesso tipo resta rifiutato',
      valida_release(['version' => '1.5.0'], ['1.5.0'], 7) !== []);
prova('una data storta viene rifiutata',
      valida_release(['version' => '1.6.0', 'release_date' => '2026-13-45']) !== []);
prova('una data assente va bene',
      valida_release(['version' => '1.6.0', 'release_date' => '']) === []);
prova('il 29 febbraio di un anno non bisestile viene rifiutato',
      valida_release(['version' => '1.6.0', 'release_date' => '2026-02-29']) !== []);


echo "\n── Il file caricato ────────────────────────────────────────\n";

$buono = zip_finto();
prova('uno zip vero passa',
      valida_pacchetto_caricato(['error' => UPLOAD_ERR_OK, 'name' => 'x.zip',
                                 'size' => filesize($buono),
                                 'tmp_name' => $buono])[0] === true);

list($ok, $errore) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0, 'tmp_name' => '']);
prova('nessun file selezionato non e un caricamento riuscito', $ok === false);

// IL CASO CHE CONTA DAVVERO su hosting condiviso: il pacchetto pesa una
// dozzina di MB e upload_max_filesize spesso e' fermo a 2. PHP non urla:
// consegna un file vuoto. Senza questo controllo si crede di aver
// pubblicato e non si e' pubblicato niente.
list($ok, $errore) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'x.zip', 'size' => 0,
     'tmp_name' => ''], 2097152);
prova('un file oltre il limite del server viene detto', $ok === false);
prova('e viene detto anche COME fare invece',
      strpos($errore, 'FTP') !== false);
prova('e il limite viene mostrato', strpos($errore, '2 MB') !== false);

list($ok, ) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_OK, 'name' => 'pacchetto.tar.gz', 'size' => 10,
     'tmp_name' => $buono]);
prova('un file che non finisce in .zip viene rifiutato', $ok === false);

$non_zip = tempnam(sys_get_temp_dir(), 'prova') . '.zip';
file_put_contents($non_zip, 'questo non e uno zip, e nemmeno ci prova');
list($ok, ) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_OK, 'name' => 'x.zip',
     'size' => filesize($non_zip), 'tmp_name' => $non_zip]);
prova('un file rinominato .zip non passa il controllo del contenuto',
      $ok === false);

list($ok, ) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_OK, 'name' => 'x.zip', 'size' => 0,
     'tmp_name' => $buono]);
prova('un file di zero byte viene rifiutato', $ok === false);

prova('un array che non e un file caricato viene rifiutato',
      valida_pacchetto_caricato(null)[0] === false);


echo "\n── Il file di un installazione completa ────────────────────\n";

// Un .exe non e' uno zip: se il controllo restasse quello degli
// aggiornamenti, nessuna installazione completa si potrebbe pubblicare.
$exe = tempnam(sys_get_temp_dir(), 'prova') . '.exe';
file_put_contents($exe, "MZ\x90\x00" . str_repeat("\0", 100));
list($ok, ) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_OK, 'name' => 'GTMC-NPI-Setup-v1.6.0.exe',
     'size' => filesize($exe), 'tmp_name' => $exe], null, 'exe');
prova('un eseguibile Windows passa', $ok === true);

// Lo sbaglio vero: caricare lo zip degli aggiornamenti al posto del setup.
list($ok, ) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_OK, 'name' => 'pacchetto.zip',
     'size' => filesize($buono), 'tmp_name' => $buono], null, 'exe');
prova('uno zip caricato al posto del setup viene rifiutato', $ok === false);

$finto_exe = tempnam(sys_get_temp_dir(), 'prova') . '.exe';
file_put_contents($finto_exe, 'questo non e un eseguibile');
list($ok, ) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_OK, 'name' => 'x.exe',
     'size' => filesize($finto_exe), 'tmp_name' => $finto_exe], null, 'exe');
prova('un file rinominato .exe non passa il controllo del contenuto',
      $ok === false);

list($ok, ) = valida_pacchetto_caricato(
    ['error' => UPLOAD_ERR_OK, 'name' => 'x.exe',
     'size' => filesize($exe), 'tmp_name' => $exe], null, 'zip');
prova('un .exe caricato come aggiornamento viene rifiutato', $ok === false);


echo "\n── La versione dichiarata dentro il pacchetto ──────────────\n";

if (class_exists('ZipArchive')) {
    $con_manifesto = zip_finto(json_encode(['formato' => 1, 'versione' => '1.6.0']));
    prova('si legge dal manifesto',
          versione_dentro_il_pacchetto($con_manifesto) === '1.6.0');

    // Lo sbaglio piu' facile da fare e piu' difficile da scoprire:
    // pubblicare il pacchetto della 1.5.0 sotto il numero 1.6.0.
    $vecchio = zip_finto(json_encode(['formato' => 1, 'versione' => '1.5.0']));
    prova('un pacchetto della versione sbagliata si riconosce',
          versione_dentro_il_pacchetto($vecchio) === '1.5.0');

    prova('senza manifesto si risponde null, non si inventa',
          versione_dentro_il_pacchetto(zip_finto()) === null);
    prova('un manifesto illeggibile non solleva',
          versione_dentro_il_pacchetto(zip_finto('{{{ non e json')) === null);
    prova('un manifesto senza versione non conta',
          versione_dentro_il_pacchetto(zip_finto('{"formato":1}')) === null);
    prova('un file che non esiste non solleva',
          versione_dentro_il_pacchetto('/percorso/che/non/esiste.zip') === null);
} else {
    echo "  (saltate: estensione zip non disponibile in questo PHP)\n";
}


echo "\n── I piani ─────────────────────────────────────────────────\n";

$piani = [1, 2, 3, 4];

prova('nessuna scelta significa per tutti',
      piani_scelti([], $piani) === []);
prova('null significa per tutti',
      piani_scelti(null, $piani) === []);
prova('due piani restano due',
      piani_scelti(['2', '3'], $piani) === [2, 3]);
prova('un piano inventato viene scartato',
      piani_scelti(['2', '99'], $piani) === [2]);
prova('un doppione non si duplica',
      piani_scelti(['2', '2'], $piani) === [2]);
prova('l ordine e stabile',
      piani_scelti(['3', '1'], $piani) === [1, 3]);

// Sceglierli tutti e sceglierne nessuno danno lo stesso risultato oggi, ma
// non domani: un piano nuovo aggiunto fra un mese resterebbe fuori dalla
// release, e nessuno collegherebbe le due cose.
prova('sceglierli TUTTI viene salvato come per tutti',
      piani_scelti(['1', '2', '3', '4'], $piani) === []);
prova('con un solo piano esistente, sceglierlo significa per tutti',
      piani_scelti(['1'], [1]) === []);


echo "\n── Coerenza con chi consegna ───────────────────────────────\n";

// Se il nome cambia qui e non la', il sito annuncia una versione che poi
// non riesce a consegnare. La prova legge il codice di download-update.php
// perche' e' l'unico modo di accorgersene senza un server.
$consegna = file_get_contents(__DIR__ . '/../api/download-update.php');
prova('download-update.php usa nome_pacchetto()',
      strpos($consegna, 'nome_pacchetto(') !== false);
prova('e non ricostruisce il nome per conto suo',
      strpos($consegna, "'GTMC-NPI-Update-v' .") === false);
prova('download-update.php passa user_id nella INSERT',
      strpos($consegna, 'user_id') !== false);

// La pagina pubblica non deve piu' leggere un file scritto a mano: era
// fermo alla 1.0.0 e puntava a un installer che sul server non c'era.
$pubblica = file_get_contents(__DIR__ . '/../download.php');
prova('download.php legge la release dal database',
      strpos($pubblica, 'ultima_installazione()') !== false);
// Non basta che il bottone giusto ci sia: non deve esserci NESSUN modo,
// in questa pagina, di arrivare al file scavalcando lo script. La pagina
// riscritta non ha piu' JavaScript: si controlla l'intero file (il
// fallback version.json e' scomparso con la riscrittura — ora si legge
// solo la tabella releases).
prova('la pagina non contiene nessun percorso uploads/',
      strpos($pubblica, '/uploads/') === false);
prova('il bottone porta a download-release.php',
      strpos($pubblica, 'download-release.php') !== false);
prova('se il file non c e il bottone non promette niente',
      strpos($pubblica, '$disponibile') !== false);

// Le cartelle chiuse: e' quello che impediva di scaricare il vecchio
// GTMC-NPI-App.zip indovinandone il nome.
foreach (['releases', 'updates'] as $quale) {
    $regole = @file_get_contents(__DIR__ . '/../uploads/' . $quale . '/.htaccess');
    prova("uploads/$quale non e scaricabile direttamente",
          $regole !== false
          && (strpos($regole, 'Require all denied') !== false
              || strpos($regole, 'Deny from all') !== false));
}

// I due script consegnano con readfile(), che legge dal disco: il
// .htaccess non li tocca. Se uno dei due passasse a un redirect verso
// uploads/, smetterebbe di funzionare — e in modo non ovvio.
$installa = file_get_contents(__DIR__ . '/../api/download-release.php');
prova('download-release.php consegna con readfile',
      strpos($installa, 'readfile(') !== false);
prova('download-update.php consegna con readfile',
      strpos($consegna, 'readfile(') !== false);
prova('nessuno dei due rimanda a uploads/ con un redirect',
      strpos($installa, 'Location: /uploads') === false
      && strpos($consegna, 'Location: /uploads') === false);
prova('download-release.php controlla la forma della versione',
      strpos($installa, 'versione_ben_formata(') !== false);
prova('e serve solo release attive e non aggiornamenti',
      strpos($installa, 'is_active = 1 AND is_update = 0') !== false);


echo "\n── La pagina staff ─────────────────────────────────────────\n";

// La pagina non si puo' eseguire senza sessione, database e $_FILES. Quello
// che si puo' — e si deve — verificare senza tutto quello e' che le
// protezioni ci siano: e' roba che si toglie per sbaglio durante una
// modifica e nessuno se ne accorge finche' non serve.
$pagina = file_get_contents(__DIR__ . '/../staff/releases.php');

prova('la pagina e riservata agli amministratori',
      strpos($pagina, "require_staff_role(['admin'])") !== false);
prova('ogni POST verifica il token CSRF',
      strpos($pagina, 'hash_equals') !== false
      && strpos($pagina, 'csrf_token') !== false);
prova('il confronto del token non usa == ',
      !preg_match('/csrf_token.{0,40}!==?\s*\$_POST/', $pagina));
prova('il file caricato passa da valida_pacchetto_caricato',
      strpos($pagina, 'valida_pacchetto_caricato(') !== false);
prova('e viene spostato con move_uploaded_file, non con copy/rename',
      strpos($pagina, 'move_uploaded_file(') !== false
      && strpos($pagina, 'rename($caricato') === false);
prova('il nome del file di destinazione non e quello scelto da chi carica',
      strpos($pagina, "\$caricato['name']") === false);
prova('la destinazione viene da percorso_del_file()',
      strpos($pagina, 'percorso_del_file($versione, $e_aggiornamento)') !== false);
prova('la cartella dipende dal tipo di release',
      strpos($pagina, 'cartella_installazioni()') !== false
      && strpos($pagina, 'cartella_aggiornamenti()') !== false);
prova('il tipo di file atteso dipende dal tipo di release',
      strpos($pagina, 'tipo_pacchetto_caricato(') !== false
      && strpos($pagina, 'valida_pacchetto_caricato(') !== false
      && strpos($pagina, "\$tipo_pacchetto)") !== false);
prova('la dimensione si legge dal file, non dal modulo',
      strpos($pagina, "filesize(\$destinazione)") !== false
      && strpos($pagina, "\$_POST['file_size']") === false);
prova('i piani passano da piani_scelti()',
      strpos($pagina, 'piani_scelti(') !== false);

// Nessun valore che arriva dall'esterno deve finire concatenato in SQL.
$righe_sql = [];
foreach (explode("\n", $pagina) as $riga) {
    if (preg_match('/(SELECT|INSERT|UPDATE|DELETE)\s/i', $riga)
            && strpos($riga, '$_POST') !== false) {
        $righe_sql[] = trim($riga);
    }
}
prova('nessun $_POST finisce dentro una query', $righe_sql === []);

// Le stringhe che arrivano dal database o dall'utente vanno in HTML.
prova('i valori mostrati passano da htmlspecialchars',
      substr_count($pagina, 'htmlspecialchars(') >= 8);
prova('la versione mostrata in tabella e protetta',
      strpos($pagina, "htmlspecialchars(\$r['version'])") !== false);


echo "\n── Il pacchetto docker (.tar.gz) ────────────────────────────\n";

// Il nome si calcola dalla versione, come per lo zip: e' il contratto con
// download-update.php, che cerca esattamente questo file.
prova('nome_pacchetto_docker segue lo stesso schema dello zip',
      nome_pacchetto_docker('1.6.0') === 'GTMC-NPI-Update-v1.6.0.tar.gz');

// percorso_pacchetto_esistente guarda il disco: chi consegna non puo'
// sapere quale formato ha scelto chi ha pubblicato.
$_cartella = cartella_aggiornamenti();
if (!is_dir($_cartella)) {
    @mkdir($_cartella, 0755, true);
}
$_tar = $_cartella . nome_pacchetto_docker('9.9.9');
$_zip = $_cartella . nome_pacchetto('9.9.8');
file_put_contents($_tar, "\x1f\x8b" . str_repeat("\0", 20));
file_put_contents($_zip, "PK\x03\x04" . str_repeat("\0", 20));
prova('percorso_pacchetto_esistente preferisce il tar.gz caricato',
      basename(percorso_pacchetto_esistente('9.9.9')) === nome_pacchetto_docker('9.9.9'));
prova('percorso_pacchetto_esistente torna allo zip quando e quello che c e',
      basename(percorso_pacchetto_esistente('9.9.8')) === nome_pacchetto('9.9.8'));
prova('senza nessun file resta il percorso zip documentato',
      basename(percorso_pacchetto_esistente('8.8.8')) === nome_pacchetto('8.8.8'));
unlink($_tar);
unlink($_zip);

// La firma gzip basta a fermare uno zip caricato col nome sbagliato.
$_gz_vero = tempnam(sys_get_temp_dir(), 'prova') . '.tar.gz';
file_put_contents($_gz_vero, "\x1f\x8b" . str_repeat("\0", 30));
$_zip_con_nome_tar = tempnam(sys_get_temp_dir(), 'prova') . '.tar.gz';
file_put_contents($_zip_con_nome_tar, "PK\x03\x04" . str_repeat("\0", 30));

prova('_e_un_gzip riconosce la firma gzip', _e_un_gzip($_gz_vero) === true);
prova('_e_un_gzip rifiuta uno zip con nome .tar.gz', _e_un_gzip($_zip_con_nome_tar) === false);

function upload_finto_targz($nome, $percorso) {
    return ['error' => UPLOAD_ERR_OK, 'size' => 30,
            'name' => $nome, 'tmp_name' => $percorso];
}
list($ok, $_) = valida_pacchetto_caricato(
    upload_finto_targz('GTMC-NPI-Update-v1.6.0.tar.gz', $_gz_vero), null, 'targz');
prova('valida_pacchetto_caricato accetta un tar.gz con firma gzip', $ok);
list($ok, $_) = valida_pacchetto_caricato(
    upload_finto_targz('GTMC-NPI-Update-v1.6.0.tar.gz', $_zip_con_nome_tar), null, 'targz');
prova('valida_pacchetto_caricato rifiuta uno zip spacciato per tar.gz', !$ok);
list($ok, $_) = valida_pacchetto_caricato(
    upload_finto_targz('GTMC-NPI-Update-v1.6.0.zip', $_gz_vero), null, 'targz');
prova('valida_pacchetto_caricato rifiuta l estensione sbagliata', !$ok);

// La versione dichiarata nell'immagine: il tag nel manifest.json del
// `docker save`. Se Phar non puo' leggerlo, il controllo e' semplicemente
// saltato (null), mai un falso positivo.
if (class_exists('PharData')) {
    $_tar_prova = tempnam(sys_get_temp_dir(), 'prova');
    unlink($_tar_prova);
    try {
        $t = new PharData($_tar_prova . '.tar');
        $t->addFromString('manifest.json',
            json_encode(['Config' => 'x.json',
                         'RepoTags' => ['gtmc-npi:1.6.0']]));
        $t->compress(Phar::GZ);              // produce .tar.gz
        $letta = _versione_dentro_immagine_docker($_tar_prova . '.tar.gz');
        prova('la versione si legge dal RepoTags del manifest.json',
              $letta === '1.6.0');
        $letta2 = _versione_dentro_immagine_docker($_gz_vero);
        prova('un tar.gz senza manifest.json non dichiara versioni',
              $letta2 === null);
        @unlink($_tar_prova . '.tar');
        @unlink($_tar_prova . '.tar.gz');
    } catch (Throwable $e) {
        prova('creazione tar.gz di prova non riuscita (Phar senza zlib?)', false);
    }
} else {
    echo "  --       PharData assente: versione nel tar non provata\n";
}
unlink($_gz_vero);
unlink($_zip_con_nome_tar);

prova('tipo_pacchetto_caricato distingue i tre formati',
      tipo_pacchetto_caricato(['name' => 'a.tar.gz'], true) === 'targz'
      && tipo_pacchetto_caricato(['name' => 'a.zip'], true) === 'zip'
      && tipo_pacchetto_caricato(['name' => 'a.exe'], false) === 'exe'
      && tipo_pacchetto_caricato([], true) === 'zip'
      && tipo_pacchetto_caricato(['name' => 'A.TAR.GZ'], true) === 'targz');

// Senza l'estensione zip di PHP la versione interna non si legge: la
// funzione deve dichiararlo (null), non inventare nulla. Con l'estensione
// presente vale il confronto con aggiornamento.json.
if (class_exists('ZipArchive')) {
    prova('versione_dentro_il_pacchetto resta zip per gli zip',
          versione_dentro_il_pacchetto(zip_finto('{"versione":"1.6.0"}'), 'zip') === '1.6.0');
} else {
    echo "  --       ZipArchive assente: versione interna dello zip non provata\n";
}


echo "\n── La stessa versione in due ruoli, e le obbligatorie ────\n";

// Con uq_releases_versione_tipo (migrazione 015) la 1.1.0 puo' esistere
// come Setup.exe e come .tar.gz: il controllo dei doppioni della pagina
// deve guardare solo le release dello stesso tipo, altrimenti il secondo
// dei due viene rifiutato e si torna al problema di partenza.
$pagina = file_get_contents(__DIR__ . '/../staff/releases.php');
prova('il controllo dei doppioni e per tipo (WHERE is_update)',
      strpos($pagina, 'SELECT version FROM releases WHERE is_update = ?') !== false);
prova('la pagina salva is_mandatory',
      strpos($pagina, 'is_mandatory') !== false);
prova('il modulo ha la spunta dell aggiornamento obbligatorio',
      strpos($pagina, 'name="is_mandatory"') !== false);
prova('la tabella segnala le obbligatorie',
      strpos($pagina, '(obbligatoria)') !== false);
prova('gli orfani si cercano per versione e tipo',
      strpos($pagina, "':' . \$r['version']") !== false);

// Registrare senza allegato (pacchetto caricato via FTP) deve guardare il
// disco come chi consegna: un aggiornamento e' .tar.gz oppure .zip, e la
// pagina che assume lo zip avvisa «manca il file» davanti a un'immagine
// docker perfettamente al suo posto.
prova('senza allegato si registra il pacchetto che esiste davvero',
      strpos($pagina, 'percorso_pacchetto_esistente($versione)') !== false);
prova('la tabella guarda il disco anche per gli aggiornamenti',
      strpos($pagina, "percorso_pacchetto_esistente(\$r['version'])") !== false);
prova('l avviso nomina entrambi i formati possibili',
      strpos($pagina, 'nome_pacchetto_docker($versione) . \' oppure \'') !== false);


echo "\n── Modificare una release pubblicata ──────────────────────\n";

// Fermare una release per errore, o accorgersi di una spunta dimenticata
// (obbligatoria, piani), non deve costringere a cancellare la riga e
// ricrearla: si modifica sul posto. Il salvataggio con id esiste gia'
// nella pagina; qui si prova che ci si arriva davvero.
prova('ogni riga pubblicata ha il pulsante Modifica',
      strpos($pagina, '?modifica=') !== false);
prova('il modulo in modifica porta l id della release',
      strpos($pagina, 'name="id"') !== false);
prova('il modulo si precompila dai valori della release',
      strpos($pagina, "\$in_modifica['version']") !== false);
prova('le spunte riflettono la release caricata (obbligatoria compresa)',
      strpos($pagina, "\$in_modifica['is_mandatory']") !== false);
prova('il controllo doppioni esclude la riga che si sta modificando',
      strpos($pagina, 'AND id <> ?') !== false);
prova('dalla modifica si puo' . "' tornare indietro",
      strpos($pagina, '>Annulla</a>') !== false);

$api = file_get_contents(__DIR__ . '/../api/check-update.php');
prova('check-update dichiara il carattere obbligatorio',
      strpos($api, "'mandatory'") !== false);
prova('check-update consegna la sha256 del pacchetto',
      strpos($api, "'sha256'") !== false);
prova('la sha256 si legge dal sidecar .sha256 del pacchetto',
      strpos($api, "'.sha256'") !== false);


echo "\n────────────────────────────────────────────────────────────\n";
if ($falliti === 0) {
    echo "Tutte le $eseguiti prove sono passate.\n\n";
    exit(0);
}
echo "$falliti prove fallite su $eseguiti.\n\n";
exit(1);
