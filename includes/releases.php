<?php
/**
 * releases.php — quali aggiornamenti spettano a chi.
 *
 * PERCHE' ESISTE
 * --------------
 * La stessa domanda («quali versioni sono piu' recenti della tua, e ti
 * spettano?») veniva risposta in due posti diversi: `check-update.php`
 * diceva quali aggiornamenti esistono, `download-update.php` decideva se
 * consegnarli. Due risposte separate alla stessa domanda finiscono sempre
 * per divergere, e quando divergono su un prodotto a pagamento significa
 * che si scarica qualcosa che non si e' comprato.
 *
 * Le funzioni qui dentro NON toccano il database: ricevono righe e
 * restituiscono righe. Cosi' si possono provare davvero, senza un MySQL.
 */

/**
 * Vero se $candidata e' successiva a $attuale.
 *
 * IL DIFETTO CHE CHIUDE
 * ---------------------
 * Prima il filtro era in SQL: `WHERE version > ?`. MySQL confronta due
 * VARCHAR carattere per carattere, quindi '1.10.0' > '1.9.0' e' FALSO —
 * '1' viene prima di '9'. Le righe giuste venivano scartate dalla query, e
 * il `version_compare` che c'era dopo lavorava su un insieme gia' monco.
 * Un cliente alla 1.9.0 non avrebbe mai visto la 1.10.0.
 */
function release_e_piu_recente($candidata, $attuale) {
    return version_compare($candidata, $attuale, '>');
}

/**
 * Il formato di una versione, prima di usarla per costruire un percorso.
 *
 * `download-update.php` compone il nome del file con la versione ricevuta
 * dalla richiesta. Anche se prima la cerca nel database, un controllo di
 * forma costa una riga e toglie di mezzo qualunque `../` per sempre.
 */
function versione_ben_formata($versione) {
    return is_string($versione)
        && preg_match('/^\d+(\.\d+){0,3}([-.][A-Za-z0-9]+)?$/', $versione) === 1;
}

/**
 * Gli aggiornamenti che spettano a chi si trova su $versione_attuale.
 *
 * @param array  $righe            righe della tabella `releases`
 * @param string $versione_attuale la versione installata dal cliente
 * @param array  $piani_per_release  [release_id => [slug, ...]]
 *        Una release ASSENTE da questa mappa, o con elenco vuoto, e' per
 *        tutti: cosi' le release gia' pubblicate non cambiano destinatari.
 * @param string|null $piano_cliente  slug del piano; null = nessuna licenza
 */
function release_disponibili($righe, $versione_attuale,
                             $piani_per_release = [], $piano_cliente = null) {
    $risultato = [];
    foreach ($righe as $riga) {
        if (empty($riga['is_active']) || empty($riga['is_update'])) {
            continue;
        }
        if (!release_e_piu_recente($riga['version'], $versione_attuale)) {
            continue;
        }
        if (!release_spetta_al_piano($riga, $piani_per_release, $piano_cliente)) {
            continue;
        }
        $risultato[] = $riga;
    }

    // Dalla piu' recente: il cliente vede in cima quella che gli interessa.
    usort($risultato, function ($a, $b) {
        return version_compare($b['version'], $a['version']);
    });
    return $risultato;
}

/**
 * Vero se la release e' destinata al piano del cliente.
 *
 * La convenzione e' «nessun vincolo = per tutti». E' l'unica che rende
 * innocua l'introduzione della tabella dei vincoli: finche' nessuno la
 * riempie, tutto si comporta come prima.
 */
function release_spetta_al_piano($riga, $piani_per_release, $piano_cliente) {
    $id = $riga['id'] ?? null;
    $ammessi = ($id !== null && isset($piani_per_release[$id]))
        ? $piani_per_release[$id] : [];

    if (empty($ammessi)) {
        return true;                 // per tutti
    }
    if (!$piano_cliente) {
        return false;                // riservata, e chi chiede non ha piano
    }
    return in_array($piano_cliente, $ammessi, true);
}

/**
 * La mappa [release_id => [slug del piano, ...]] letta dal database.
 *
 * Se la tabella non esiste ancora (installazione non aggiornata) si
 * restituisce una mappa vuota: tutte le release valgono per tutti, cioe'
 * il comportamento di prima. Un aggiornamento del sito non deve rompere
 * il download a chi non ha ancora applicato la migrazione.
 */
function carica_piani_per_release() {
    try {
        $righe = db_fetch_all(
            "SELECT rp.release_id, p.slug
               FROM release_plans rp
               JOIN plans p ON p.id = rp.plan_id"
        );
    } catch (Throwable $e) {
        error_log('release_plans non disponibile: ' . $e->getMessage());
        return [];
    }
    $mappa = [];
    foreach ($righe as $r) {
        $mappa[$r['release_id']][] = $r['slug'];
    }
    return $mappa;
}

/**
 * Verifica la licenza e restituisce il piano.
 *
 * Il token e' quello RSA rilasciato da /api.php (license_validate) e
 * conservato dall'applicazione; $machine_id e' l'impronta della macchina.
 * La verifica avviene in locale con license_lib.php (stessa chiave del
 * server), senza una chiamata HTTP verso se stessi.
 *
 * @return array [valida (bool), piano (string|null), errore (string|null)]
 */
function verifica_licenza($license_token, $machine_id) {
    if (!$license_token || !$machine_id) {
        return [false, null, 'Licenza mancante'];
    }
    require_once __DIR__ . '/license_lib.php';
    return lic_check_token($license_token, $machine_id);
}

