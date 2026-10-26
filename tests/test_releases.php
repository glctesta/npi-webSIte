<?php
/**
 * Prova la logica degli aggiornamenti — eseguendola, non leggendola.
 *
 * Uso:  php tests/test_releases.php
 *
 * Non serve un database: le funzioni di includes/releases.php ricevono
 * righe e restituiscono righe, e questo file gliele passa a mano. Cosi' si
 * puo' verificare la regola che conta davvero — chi vede cosa — senza
 * dipendere da MySQL ne' da una licenza vera.
 */

require_once __DIR__ . '/../includes/releases.php';

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

function release($id, $versione, $attiva = 1, $aggiornamento = 1) {
    return ['id' => $id, 'version' => $versione,
            'is_active' => $attiva, 'is_update' => $aggiornamento,
            'changelog' => '', 'file_size' => '', 'min_requirements' => ''];
}

function versioni($righe) {
    return array_map(function ($r) { return $r['version']; }, $righe);
}


echo "\n── Confronto fra versioni ──────────────────────────────────\n";

// IL DIFETTO CHIUSO: in SQL '1.10.0' > '1.9.0' e' falso, perche' i VARCHAR
// si confrontano carattere per carattere. Un cliente alla 1.9.0 non avrebbe
// mai visto la 1.10.0.
prova('1.10.0 e piu recente di 1.9.0',
      release_e_piu_recente('1.10.0', '1.9.0'));
prova('1.9.0 NON e piu recente di 1.10.0',
      !release_e_piu_recente('1.9.0', '1.10.0'));
prova('2.0.0 e piu recente di 1.99.99',
      release_e_piu_recente('2.0.0', '1.99.99'));
prova('la stessa versione non e un aggiornamento',
      !release_e_piu_recente('1.5.0', '1.5.0'));
prova('1.5.1 e piu recente di 1.5.0',
      release_e_piu_recente('1.5.1', '1.5.0'));


echo "\n── Forma della versione ────────────────────────────────────\n";

// La versione finisce dentro un percorso di file.
prova('1.5.0 e ben formata',            versione_ben_formata('1.5.0'));
prova('1.5 e ben formata',              versione_ben_formata('1.5'));
prova('1.5.0-beta1 e ben formata',      versione_ben_formata('1.5.0-beta1'));
prova('../../config e rifiutata',       !versione_ben_formata('../../config'));
prova('1.5.0/../x e rifiutata',         !versione_ben_formata('1.5.0/../x'));
prova('la stringa vuota e rifiutata',   !versione_ben_formata(''));
prova('null e rifiutato',               !versione_ben_formata(null));
prova('un array e rifiutato',           !versione_ben_formata(['1.5.0']));


echo "\n── Quali aggiornamenti spettano ────────────────────────────\n";

$catalogo = [
    release(1, '1.4.0'),
    release(2, '1.9.0'),
    release(3, '1.10.0'),
    release(4, '2.0.0', 0),            // disattivata
    release(5, '1.11.0', 1, 0),        // non e' un aggiornamento
];

$viste = release_disponibili($catalogo, '1.9.0');
prova('dalla 1.9.0 si vede solo la 1.10.0',
      versioni($viste) === ['1.10.0']);

$viste = release_disponibili($catalogo, '1.3.0');
prova('dalla 1.3.0 si vedono tutte le attive, dalla piu recente',
      versioni($viste) === ['1.10.0', '1.9.0', '1.4.0']);

prova('una release disattivata non compare mai',
      !in_array('2.0.0', versioni(release_disponibili($catalogo, '1.0.0')), true));
prova('una release che non e un aggiornamento non compare',
      !in_array('1.11.0', versioni(release_disponibili($catalogo, '1.0.0')), true));
prova('chi e gia all ultima versione non vede nulla',
      release_disponibili($catalogo, '1.10.0') === []);


echo "\n── Versioni per tipo di licenza ────────────────────────────\n";

// Convenzione: nessun vincolo = per tutti. E' quella che rende innocua
// l'introduzione della tabella su un sito gia' in esercizio.
$vincoli = [
    3 => ['professional', 'enterprise'],   // la 1.10.0 e' riservata
];

$viste = release_disponibili($catalogo, '1.3.0', $vincoli, 'basic');
prova('un cliente Basic non vede la release riservata',
      !in_array('1.10.0', versioni($viste), true));
prova('un cliente Basic vede comunque quelle per tutti',
      versioni($viste) === ['1.9.0', '1.4.0']);

$viste = release_disponibili($catalogo, '1.3.0', $vincoli, 'professional');
prova('un cliente Professional vede la release riservata',
      in_array('1.10.0', versioni($viste), true));

$viste = release_disponibili($catalogo, '1.3.0', $vincoli, 'enterprise');
prova('anche Enterprise la vede',
      in_array('1.10.0', versioni($viste), true));

$viste = release_disponibili($catalogo, '1.3.0', $vincoli, null);
prova('senza licenza le release riservate non compaiono',
      !in_array('1.10.0', versioni($viste), true));
prova('senza licenza quelle per tutti compaiono lo stesso',
      versioni($viste) === ['1.9.0', '1.4.0']);

$viste = release_disponibili($catalogo, '1.3.0', [], 'basic');
prova('senza vincoli configurati tutti vedono tutto',
      versioni($viste) === ['1.10.0', '1.9.0', '1.4.0']);

// Una tabella dei vincoli con un elenco VUOTO per una release deve valere
// come «per tutti»: e' il caso che si ottiene cancellando l'ultimo vincolo.
prova('un elenco di piani vuoto vale come per tutti',
      release_spetta_al_piano(release(3, '1.10.0'), [3 => []], 'basic'));

prova('una release senza id non viene mai considerata riservata',
      release_spetta_al_piano(['version' => '1.0.0'], $vincoli, null));


echo "\n── La licenza e obbligatoria ───────────────────────────────\n";

// `verifica_licenza` parla con la rete solo DOPO questo controllo: il caso
// dei parametri mancanti si puo' quindi provare davvero. Ed e' il caso che
// conta: era il buco di download-update.php.
list($ok, $piano, $errore) = verifica_licenza(null, null);
prova('senza token ne macchina la licenza non e valida', $ok === false);
prova('e il motivo viene detto', $errore !== null);

list($ok, , ) = verifica_licenza('token', null);
prova('un token senza macchina non basta', $ok === false);

list($ok, , ) = verifica_licenza(null, 'macchina');
prova('una macchina senza token non basta', $ok === false);

list($ok, , ) = verifica_licenza('', '');
prova('stringhe vuote non bastano', $ok === false);


echo "\n────────────────────────────────────────────────────────────\n";
if ($falliti === 0) {
    echo "Tutte le $eseguiti prove sono passate.\n\n";
    exit(0);
}
echo "$falliti prove fallite su $eseguiti.\n\n";
exit(1);
