<?php
/**
 * Prova le funzioni del form demo (demo.php):
 *
 *  - demo_return_path(): pulizia di un URL candidato al redirect — solo
 *    path relativi dello stesso host;
 *  - demo_origin_path(): quale candidato vince (parametro "from", pagina
 *    precedente in sessione, Referer);
 *  - phone_plausible(): input telefono inventato fuori (8-15 cifre, niente
 *    sequenze banali); la prova vera richiederebbe un OTP via SMS;
 *  - email_domain_blocked(): domini di posta temporanea.
 *
 * Uso:  php tests/test_demo_return.php
 *
 * Le funzioni sono estratte da demo.php alla lettera: se qualcuno le
 * modifica li' senza rigirare questa prova, il test non copre piu' il
 * codice reale (il controllo in fondo lo segnala).
 *
 * Non serve un database ne' un server web (email_domain_resolves usa il
 * DNS e non viene provata qui).
 */

$demoPhp = file_get_contents(__DIR__ . '/../demo.php');

$estratte = '';
foreach (['demo_return_path', 'demo_origin_allowed', 'demo_origin_path', 'phone_plausible', 'email_domain_blocked'] as $fn) {
    if (!preg_match('/function ' . $fn . '.*?\n}/s', $demoPhp, $m)) {
        fwrite(STDERR, "$fn() non trovata in demo.php\n");
        exit(1);
    }
    $estratte .= $m[0] . "\n";
}
eval($estratte);

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

$_SERVER['HTTP_HOST'] = 'gtmc-npi.eu';

// ── demo_return_path: [referer / valore del campo nascosto, percorso atteso]
$cases = [
    ['',                                        '/demo.php'],        // accesso diretto: niente referer
    ['/',                                       '/'],                // homepage: ritorno legittimo
    ['/demo.php',                               '/demo.php'],
    ['/features.php',                           '/features.php'],
    ['/pricing.php?lang=ro',                    '/pricing.php?lang=ro'],
    // il browser manda il Referer assoluto: host nostro, si ripulisce al solo path
    ['https://gtmc-npi.eu/features.php',   '/features.php'],
    ['http://gtmc-npi.eu/pricing.php',     '/pricing.php'],
    ['https://evil.example.com/x',              '/demo.php'],        // host estraneo
    ['//evil.example.com/x',                    '/demo.php'],        // protocol-relative
    ['javascript:alert(1)',                     '/demo.php'],        // pseudo-schema
    ['http://gtmc-npi.eu:8080/x',          '/demo.php'],        // porta non standard
    ['http://user@gtmc-npi.eu/x',          '/demo.php'],        // userinfo
    ["/x\r\nLocation: /y",                      '/demo.php'],        // header injection
    ['features.php',                            '/demo.php'],        // path relativo
    ['https://gtmc-npi.eu//evil',          '/demo.php'],        // doppia barra iniziale
];

foreach ($cases as $c) {
    $input = $c[0] === '' ? null : $c[0];
    $got = demo_return_path($input);
    prova(
        "return_path '" . addcslashes((string)$c[0], "\x00..\x1f") . "' -> '$got'",
        $got === $c[1]
    );
}

// ── demo_origin_path: priorita' from (POST poi GET) > sessione > referer
function contesto_pulito() {
    $_GET = [];
    $_POST = [];
    $_SESSION = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_REFERER']);
}

contesto_pulito();
prova('origine: nessun candidato -> fallback', demo_origin_path() === '/demo.php');

contesto_pulito();
$_GET['from'] = '/features.php';
prova('origine: parametro from vince', demo_origin_path() === '/features.php');

contesto_pulito();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['from'] = '/pricing.php';
$_GET['from'] = '/blog';
prova('origine: in POST il from del form vince su quello della query', demo_origin_path() === '/pricing.php');

contesto_pulito();
$_SESSION['prev_page'] = '/';
prova('origine: pagina precedente in sessione (homepage)', demo_origin_path() === '/');

contesto_pulito();
$_SESSION['prev_page'] = '/pricing.php?lang=ro';
prova('origine: pagina precedente con query string', demo_origin_path() === '/pricing.php?lang=ro');

contesto_pulito();
$_SERVER['HTTP_REFERER'] = 'https://evil.example.com/x';
prova('origine: solo referer estraneo -> fallback', demo_origin_path() === '/demo.php');

contesto_pulito();
$_GET['from'] = 'https://evil.example.com/x';
$_SESSION['prev_page'] = '/features.php';
prova('origine: from invalido, vince la sessione', demo_origin_path() === '/features.php');

contesto_pulito();
$_GET['from'] = '/demo.php';
prova('origine: from uguale al fallback -> resta fallback', demo_origin_path() === '/demo.php');

// ── pagine di servizio escluse come origine (la segnalazione: dopo il form
//    si finiva sulla pagina di registrazione, visitata in precedenza)
$origine_no = ['/auth/register.php', '/auth/login.php', '/portal/', '/staff/', '/api/license-request.php', '/cron/report.php', '/download.php'];
foreach ($origine_no as $o) {
    contesto_pulito();
    $_GET['from'] = $o;
    prova("origine: '$o' esclusa -> fallback", demo_origin_path() === '/demo.php');
}
contesto_pulito();
$_GET['from'] = '/auth/register.php';
$_SESSION['prev_page'] = '/features.php';
prova('origine: da pagina auth si torna all ultima pagina di contenuto', demo_origin_path() === '/features.php');
contesto_pulito();
$_SERVER['HTTP_REFERER'] = 'https://gtmc-npi.eu/auth/register.php';
$_SESSION['prev_page'] = '/auth/login.php';
prova('origine: solo pagine auth -> fallback', demo_origin_path() === '/demo.php');

// ── phone_plausible
$telefoni_ok = ['+40 722 345 678', '0722345678', '0040-722-345-678', '+39 02 1234 5678', '(+40) 722.345.678'];
foreach ($telefoni_ok as $t) {
    prova("telefono plausibile: '$t'", phone_plausible($t) === true);
}
$telefoni_no = ['12345678', '87654321', '11111111', '0722 345', 'abcd1234', '', '0722-345-678-90-123456'];
foreach ($telefoni_no as $t) {
    prova("telefono rifiutato: '" . addcslashes($t, "\x00..\x1f") . "'", phone_plausible($t) === false);
}

// ── email_domain_blocked
prova('dominio bloccato: mailinator.com', email_domain_blocked('a@mailinator.com') === true);
prova('dominio bloccato: sottodominio di yopmail.com', email_domain_blocked('a@sub.yopmail.com') === true);
prova('dominio bloccato: senza chiocciola', email_domain_blocked('niente-chiocciola') === true);
prova('dominio ok: gmail.com', email_domain_blocked('a@gmail.com') === false);
prova('dominio ok: gtmc-npi.eu', email_domain_blocked('a@gtmc-npi.eu') === false);

// le funzioni nel test devono coincidere byte per byte con quelle in demo.php
foreach (['demo_return_path', 'demo_origin_allowed', 'demo_origin_path', 'phone_plausible', 'email_domain_blocked'] as $fn) {
    preg_match('/function ' . $fn . '.*?\n}/s', file_get_contents(__DIR__ . '/../demo.php'), $m2);
    preg_match('/function ' . $fn . '.*?\n}/s', $demoPhp, $m1);
    prova("il corpo di $fn() in demo.php non e' cambiato dopo l'estrazione", $m1[0] === $m2[0]);
}

echo "\n$eseguiti prove, $falliti fallite\n";
exit($falliti ? 1 : 0);
