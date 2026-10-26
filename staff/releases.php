<?php
/**
 * staff/releases.php — pubblicare una versione.
 *
 * PRIMA DI QUESTA PAGINA, pubblicare significava: caricare lo zip via FTP
 * nella cartella giusta con il nome giusto, poi aprire phpMyAdmin e
 * scrivere a mano una INSERT in `releases`, poi — da quando esistono le
 * versioni per piano — altre righe a mano in `release_plans`. Tre passaggi
 * che si possono sbagliare in silenzio, e il modo in cui te ne accorgi e'
 * un cliente che scrive «l'aggiornamento non si scarica».
 *
 * DUE STRADE PER IL PACCHETTO
 * ---------------------------
 * Il file pesa una dozzina di megabyte, e su hosting condiviso
 * `upload_max_filesize` e' spesso fermo a 2. Quindi: o lo si carica da qui,
 * o lo si mette via FTP in uploads/updates/ e la pagina lo riconosce da
 * sola. Il limite vero del server e' scritto in cima, cosi' non si scopre
 * a meta' del caricamento.
 */
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_role(['admin']);
require_once __DIR__ . '/includes/staff_functions.php';
require_once __DIR__ . '/../includes/releases_admin.php';

$page_title = 'Releases';

$limite = limite_caricamento();


// ═══════════════════════════════════════════════════════════════════════
//  Azioni
// ═══════════════════════════════════════════════════════════════════════

function _torna($messaggio = null, $tipo = 'success') {
    if ($messaggio !== null) {
        flash($messaggio, $tipo);
    }
    header('Location: /staff/releases.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        _torna('Sessione scaduta: riprova.', 'error');
    }

    $azione = $_POST['azione'] ?? '';

    // ── Attiva / disattiva ─────────────────────────────────────────────
    if ($azione === 'stato') {
        $id = (int)($_POST['id'] ?? 0);
        $attiva = !empty($_POST['attiva']) ? 1 : 0;
        db_query('UPDATE releases SET is_active = ? WHERE id = ?', [$attiva, $id]);
        _torna($attiva ? 'Release attivata.' : 'Release disattivata.');
    }

    // ── Salva (nuova o modifica) ───────────────────────────────────────
    if ($azione === 'salva') {
        $id = ($_POST['id'] ?? '') !== '' ? (int)$_POST['id'] : null;
        $versione = trim($_POST['version'] ?? '');
        $e_aggiornamento = !empty($_POST['is_update']);

        // Il doppione si cerca solo fra le release dello STESSO tipo: la
        // stessa versione puo' esistere come installazione e come
        // aggiornamento (uq_releases_versione_tipo, migrazione 015). In
        // modifica la riga stessa si esclude: la sua versione resta sua.
        $sql_doppioni = 'SELECT version FROM releases WHERE is_update = ?';
        $parametri_doppioni = [$e_aggiornamento ? 1 : 0];
        if ($id !== null) {
            $sql_doppioni .= ' AND id <> ?';
            $parametri_doppioni[] = $id;
        }
        $esistenti = array_map(function ($r) { return $r['version']; },
                               db_fetch_all($sql_doppioni, $parametri_doppioni));
        $errori = valida_release($_POST, $esistenti, $id);
        if ($errori) {
            _torna(implode(' ', $errori), 'error');
        }

        $piani_validi = array_map(function ($p) { return (int)$p['id']; },
                                  elenco_piani());
        $piani = piani_scelti($_POST['plans'] ?? [], $piani_validi);

        // ── Il pacchetto ───────────────────────────────────────────────
        //
        // Tre tipi, due cartelle: un aggiornamento e' uno zip di codice
        // oppure l'immagine docker esportata (.tar.gz, soluzione B), entrambi
        // in uploads/updates/; un'installazione completa e' l'eseguibile che
        // si porta dietro Python, in uploads/releases/.
        $cartella = $e_aggiornamento ? cartella_aggiornamenti()
                                     : cartella_installazioni();
        $caricato = $_FILES['pacchetto'] ?? null;
        $ha_provato_a_caricare = $caricato
            && ($caricato['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        // Il formato si deduce dal nome del file caricato, ma solo dentro
        // la funzione dedicata: la destinazione non e mai il nome scelto
        // da chi carica, resta calcolata dalla versione.
        $tipo_pacchetto = tipo_pacchetto_caricato($caricato, $e_aggiornamento);
        $destinazione = ($e_aggiornamento && $tipo_pacchetto === 'targz')
            ? cartella_aggiornamenti() . nome_pacchetto_docker($versione)
            : percorso_del_file($versione, $e_aggiornamento);

        if ($ha_provato_a_caricare) {
            list($ok, $errore) = valida_pacchetto_caricato(
                $caricato, $limite, $tipo_pacchetto);
            if (!$ok) {
                _torna($errore, 'error');
            }

            // Il pacchetto dichiara la propria versione: pubblicare quello
            // della 1.5.0 sotto il numero 1.6.0 e' lo sbaglio piu' facile
            // da fare e il piu' difficile da scoprire. Vale per gli zip
            // (aggiornamento.json) e per le immagini docker (tag nel
            // manifest.json); dentro un .exe non si puo' leggere.
            $dentro = $e_aggiornamento
                ? versione_dentro_il_pacchetto($caricato['tmp_name'],
                                               $tipo_pacchetto)
                : null;
            if ($dentro !== null && $dentro !== $versione
                    && empty($_POST['ignora_versione_interna'])) {
                _torna('Il pacchetto dichiara la versione ' . $dentro
                     . ' ma lo stai pubblicando come ' . $versione
                     . '. Correggi, oppure spunta «pubblica comunque».',
                       'error');
            }

            if (!is_dir($cartella) && !@mkdir($cartella, 0755, true)) {
                _torna('Non riesco a creare ' . $cartella . '.', 'error');
            }
            if (!@move_uploaded_file($caricato['tmp_name'], $destinazione)) {
                _torna('Non riesco a scrivere in '
                     . cartella_pubblica($e_aggiornamento)
                     . ': controlla i permessi della cartella.', 'error');
            }
        }

        // Registrato senza allegato: il pacchetto reale e' quello gia' sul
        // disco, caricato via FTP. Un aggiornamento puo' essere l'immagine
        // docker (.tar.gz) OPPURE lo zip di codice: si guarda quale dei due
        // esiste, esattamente come fara' download-update.php alla consegna.
        // (Prima si assumeva lo zip: la pagina avvisava «manca il file» davanti
        // a un .tar.gz perfettamente al suo posto, e lasciava vuoti file_path
        // e file_size.)
        if (!$ha_provato_a_caricare && $e_aggiornamento) {
            $destinazione = percorso_pacchetto_esistente($versione);
        }

        // La dimensione si legge dal file, non si chiede a chi pubblica:
        // un numero digitato a mano e' un numero che prima o poi mente.
        $dimensione = is_file($destinazione)
            ? dimensione_leggibile(filesize($destinazione)) : '';
        $percorso_pubblico = is_file($destinazione)
            ? cartella_pubblica($e_aggiornamento)
              . basename($destinazione) : null;

        $campi = [
            'version'          => $versione,
            'release_date'     => trim($_POST['release_date'] ?? '') ?: null,
            'changelog'        => trim($_POST['changelog'] ?? ''),
            'min_requirements' => trim($_POST['min_requirements'] ?? ''),
            'is_active'        => !empty($_POST['is_active']) ? 1 : 0,
            'is_update'        => $e_aggiornamento ? 1 : 0,
            // Ha senso solo per gli aggiornamenti: le installazioni
            // complete sono sempre «obbligatorie» per chi le scarica.
            'is_mandatory'     => ($e_aggiornamento && !empty($_POST['is_mandatory'])) ? 1 : 0,
        ];

        try {
            if ($id === null) {
                $id = (int)db_insert(
                    'INSERT INTO releases (version, release_date, changelog,
                         file_path, file_size, min_requirements, is_active,
                         is_update, is_mandatory)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$campi['version'], $campi['release_date'], $campi['changelog'],
                     $percorso_pubblico, $dimensione, $campi['min_requirements'],
                     $campi['is_active'], $campi['is_update'],
                     $campi['is_mandatory']]);
            } else {
                db_query(
                    'UPDATE releases SET version = ?, release_date = ?,
                         changelog = ?, min_requirements = ?, is_active = ?,
                         is_update = ?, is_mandatory = ?' .
                    ($percorso_pubblico ? ', file_path = ?, file_size = ?' : '') .
                    ' WHERE id = ?',
                    $percorso_pubblico
                        ? [$campi['version'], $campi['release_date'],
                           $campi['changelog'], $campi['min_requirements'],
                           $campi['is_active'], $campi['is_update'],
                           $campi['is_mandatory'],
                           $percorso_pubblico, $dimensione, $id]
                        : [$campi['version'], $campi['release_date'],
                           $campi['changelog'], $campi['min_requirements'],
                           $campi['is_active'], $campi['is_update'],
                           $campi['is_mandatory'], $id]);
            }
            salva_piani_della_release($id, $piani);
        } catch (Exception $e) {
            error_log('Salvataggio release fallito: ' . $e->getMessage());
            _torna('Salvataggio non riuscito. Il dettaglio e nel log del server.',
                   'error');
        }

        $atteso = $e_aggiornamento
            ? nome_pacchetto_docker($versione) . ' oppure '
              . nome_del_file($versione, true)
            : nome_del_file($versione, false);
        $nota = is_file($destinazione)
            ? '' : ' Attenzione: manca ancora il file ' . $atteso . ' in '
                 . cartella_pubblica($e_aggiornamento)
                 . ', quindi il download non funzionera.';
        _torna('Release ' . $versione . ' salvata.' . $nota,
               $nota ? 'warning' : 'success');
    }

    _torna('Azione sconosciuta.', 'error');
}


// ═══════════════════════════════════════════════════════════════════════
//  Lettura
// ═══════════════════════════════════════════════════════════════════════

$piani = elenco_piani();
$release = elenco_release();
$piani_per_release = carica_piani_per_release();

// carica_piani_per_release() restituisce SLUG, non id: e' quello che
// serve a release_spetta_al_piano(), che confronta con il piano dichiarato
// dalla licenza. Qui servono per mostrare i nomi leggibili.
$nome_dello_slug = [];
foreach ($piani as $p) {
    $nome_dello_slug[$p['slug']] = $p['name'];
}

// ── Modifica: riapre il modulo pieno dei valori di una release ────────
// Cambiare una spunta dimenticata (obbligatoria, stato, piani) o
// sostituire il pacchetto non deve richiedere di cancellare la riga e
// ricrearla: la release pubblicata si modifica sul posto.
$in_modifica = null;
$id_piani_scelti = [];
if (isset($_GET['modifica'])) {
    $in_modifica = db_fetch('SELECT * FROM releases WHERE id = ?',
                            [(int)$_GET['modifica']]);
    if (!$in_modifica) {
        _torna('Release non trovata.', 'error');
    }
    $slug_a_id = [];
    foreach ($piani as $p) {
        $slug_a_id[$p['slug']] = (int)$p['id'];
    }
    foreach ($piani_per_release[(int)$in_modifica['id']] ?? [] as $slug) {
        if (isset($slug_a_id[$slug])) {
            $id_piani_scelti[] = $slug_a_id[$slug];
        }
    }
}

// Pacchetti presenti nella cartella ma non registrati: quasi sempre un
// caricamento via FTP a cui manca il passaggio nel database. Il confronto
// e' per versione E tipo: la 1.1.0 registrata come aggiornamento non copre
// il Setup.exe 1.1.0 che aspetta in uploads/releases/.
$registrate = [];
foreach ($release as $r) {
    $registrate[((int)$r['is_update']) . ':' . $r['version']] = true;
}
$orfani = [];
// [cartella, modello, espressione, etichetta, is_update]
$da_guardare = [
    [cartella_aggiornamenti(), 'GTMC-NPI-Update-v*.zip',
     '/GTMC-NPI-Update-v(.+)\.zip$/',      'aggiornamento', 1],
    [cartella_aggiornamenti(), 'GTMC-NPI-Update-v*.tar.gz',
     '/GTMC-NPI-Update-v(.+)\.tar\.gz$/', 'aggiornamento docker', 1],
    [cartella_installazioni(), 'GTMC-NPI-Setup-v*.exe',
     '/GTMC-NPI-Setup-v(.+)\.exe$/',       'installazione', 0],
    [cartella_aggiornamenti(), 'GTMC-Servanga-App-v*.zip',
     '/GTMC-Servanga-App-v(.+)\.zip$/',        'aggiornamento (alias)', 1],
    [cartella_aggiornamenti(), 'GTMC-Servanga-Update-v*.zip',
     '/GTMC-Servanga-Update-v(.+)\.zip$/',     'aggiornamento (alias)', 1],
    [cartella_aggiornamenti(), 'GTMC-Servanga-Update-v*.tar.gz',
     '/GTMC-Servanga-Update-v(.+)\.tar\.gz$/', 'aggiornamento docker (alias)', 1],
    [cartella_installazioni(), 'GTMC-Servanga-Setup-v*.exe',
     '/GTMC-Servanga-Setup-v(.+)\.exe$/',      'installazione (alias)', 0],
];
foreach ($da_guardare as list($dove, $modello, $espressione, $tipo, $e_agg)) {
    foreach (glob($dove . $modello) ?: [] as $file) {
        if (preg_match($espressione, basename($file), $m)
                && !isset($registrate[$e_agg . ':' . $m[1]])) {
            $orfani[] = ['versione' => $m[1], 'tipo' => $tipo,
                         'dimensione' => filesize($file)];
        }
    }
}

include __DIR__ . '/includes/staff_header.php';
?>

<div class="welcome-banner">
    <h1>Releases</h1>
    <p>Pubblica una versione dell'applicazione GTMC-NPI e decidi a quali piani spetta.</p>
</div>

<div class="glass-card" style="padding:1.25rem;margin-bottom:1.5rem;">
    <p style="margin:0;">
        Gli <strong>aggiornamenti</strong> si generano dal repository NPI con
        <code>python crea_pacchetto_aggiornamento.py</code>; le
        <strong>installazioni complete</strong> dal repository NPI-Installer
        con <code>python build.py</code>.
        Limite di caricamento di questo server:
        <strong><?= htmlspecialchars(dimensione_leggibile($limite)) ?></strong><?php
        if ($limite && $limite < 15 * 1048576): ?> — probabilmente
        <em>non basta</em>: carica via FTP e poi registra la versione qui
        sotto senza allegare il file<?php endif; ?>.
    </p>
</div>

<?php if ($orfani): ?>
<div class="staff-alert staff-alert-warning">
    <strong>Pacchetti presenti ma non pubblicati:</strong>
    <?php foreach ($orfani as $o): ?>
        <?= htmlspecialchars($o['versione']) ?>
        (<?= htmlspecialchars($o['tipo']) ?>,
         <?= htmlspecialchars(dimensione_leggibile($o['dimensione'])) ?>)&nbsp;
    <?php endforeach; ?>
    — finche' non hanno una riga qui sotto, nessuno li vede.
</div>
<?php endif; ?>


<!-- ── Nuova release / Modifica ─────────────────────────────────────── -->
<div class="glass-card" style="padding:1.5rem;margin-bottom:1.5rem;">
    <div class="card-header"><h3><?= $in_modifica
        ? 'Modifica della ' . htmlspecialchars($in_modifica['version'])
        : 'Nuova release' ?></h3></div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="azione" value="salva">
        <?php if ($in_modifica): ?>
        <input type="hidden" name="id" value="<?= (int)$in_modifica['id'] ?>">
        <?php endif; ?>

        <div class="staff-grid-2" style="gap:1rem;">
            <label>Versione
                <input type="text" name="version" required placeholder="1.6.0"
                       pattern="[0-9][A-Za-z0-9._-]*" class="form-control"
                       value="<?= htmlspecialchars($in_modifica['version'] ?? '') ?>">
            </label>
            <label>Data
                <input type="date" name="release_date" class="form-control"
                       value="<?= htmlspecialchars($in_modifica['release_date'] ?? date('Y-m-d')) ?>">
            </label>
        </div>

        <label style="display:block;margin-top:1rem;">Note di rilascio
            <textarea name="changelog" rows="6" class="form-control"
                      placeholder="Una riga per novita'. Il cliente le legge
                                   nella striscia dentro l'applicazione."><?= htmlspecialchars($in_modifica['changelog'] ?? '') ?></textarea>
        </label>

        <label style="display:block;margin-top:1rem;">Requisiti minimi
            <input type="text" name="min_requirements" class="form-control"
                   placeholder="Windows 10+, SQL Server 2019+, Python 3.11+"
                   value="<?= htmlspecialchars($in_modifica['min_requirements'] ?? '') ?>">
        </label>

        <label style="display:block;margin-top:1rem;">File
            <input type="file" name="pacchetto" accept=".zip,.exe,.tar.gz"
                   class="form-control">
            <small>
                Aggiornamento: <code>GTMC-NPI-Update-v&lt;versione&gt;.zip</code>
                oppure <code>GTMC-NPI-Update-v&lt;versione&gt;.tar.gz</code>
                (immagine docker, si genera con <code>docker save gtmc-npi:v&lt;versione&gt; | gzip</code>)
                in <code>uploads/updates/</code>.<br>
                Installazione completa:
                <code>GTMC-NPI-Setup-v&lt;versione&gt;.exe</code> in
                <code>uploads/releases/</code> — sono ottanta megabyte, quasi
                sempre va via FTP.<br>
                Se il file e' gia' al suo posto con il nome giusto, lascia vuoto<?php if ($in_modifica): ?>;
                in modifica il file pubblicato resta quello che c'e' (il campo
                si aggiorna solo se ne alleghi uno nuovo)<?php endif; ?>.
            </small>
        </label>

        <fieldset style="margin-top:1.25rem;border:0;padding:0;">
            <legend><strong>A chi spetta</strong></legend>
            <p style="margin:.25rem 0 .75rem;">
                <small>Nessuna spunta = <strong>per tutti</strong>. Spuntarli
                tutti significa la stessa cosa e viene salvato cosi'.</small>
            </p>
            <?php foreach ($piani as $p): ?>
                <label style="margin-right:1.25rem;">
                    <input type="checkbox" name="plans[]" value="<?= (int)$p['id'] ?>"
                        <?= in_array((int)$p['id'], $id_piani_scelti, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($p['name']) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div style="margin-top:1.25rem;">
            <label style="margin-right:1.5rem;">
                <input type="checkbox" name="is_update"
                    <?= ($in_modifica['is_update'] ?? 1) ? 'checked' : '' ?>>
                E' un aggiornamento (togli la spunta per un'installazione completa)
            </label>
            <label style="margin-right:1.5rem;">
                <input type="checkbox" name="is_mandatory"
                    <?= !empty($in_modifica['is_mandatory']) ? 'checked' : '' ?>>
                Aggiornamento obbligatorio (l'installer del cliente lo segnala
                e chiede conferma prima di lasciarlo saltare)
            </label>
            <label style="margin-right:1.5rem;">
                <input type="checkbox" name="is_active"
                    <?= ($in_modifica['is_active'] ?? 1) ? 'checked' : '' ?>> Attiva
            </label>
            <label>
                <input type="checkbox" name="ignora_versione_interna">
                Pubblica comunque anche se il pacchetto dichiara un'altra versione
            </label>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:1.25rem;">
            <?= $in_modifica ? 'Salva modifiche' : 'Pubblica' ?>
        </button>
        <?php if ($in_modifica): ?>
        <a class="btn btn-outline" href="/staff/releases.php">Annulla</a>
        <?php endif; ?>
    </form>
</div>


<!-- ── Elenco ───────────────────────────────────────────────────────── -->
<div class="glass-card table-card">
    <div class="card-header"><h3>Pubblicate</h3></div>
    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Versione</th>
                    <th>Data</th>
                    <th>Pacchetto</th>
                    <th>A chi spetta</th>
                    <th>Tipo</th>
                    <th>Stato</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$release): ?>
                <tr><td colspan="7" class="text-center text-muted">
                    Nessuna release.</td></tr>
            <?php else: foreach ($release as $r):
                // Per gli aggiornamenti il pacchetto reale puo' essere
                // l'immagine docker (.tar.gz) o lo zip: si guarda il disco
                // come fa chi consegna, altrimenti un .tar.gz presente
                // apparirebbe come «manca».
                $file = $r['is_update']
                    ? percorso_pacchetto_esistente($r['version'])
                    : percorso_del_file($r['version'], $r['is_update']);
                $c_e = is_file($file);
                $suoi = $piani_per_release[(int)$r['id']] ?? [];
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['version']) ?></strong></td>
                    <td><?= htmlspecialchars($r['release_date'] ?? '—') ?></td>
                    <td>
                        <?php if ($c_e): ?>
                            <?= htmlspecialchars(dimensione_leggibile(filesize($file))) ?>
                        <?php else: ?>
                            <?= badge_html('manca', 'danger') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$suoi): ?>
                            tutti
                        <?php else:
                            $nomi = [];
                            foreach ($suoi as $slug) {
                                $nomi[] = $nome_dello_slug[$slug] ?? $slug;
                            }
                            echo htmlspecialchars(implode(', ', $nomi));
                        endif; ?>
                    </td>
                    <td><?= $r['is_update']
                            ? 'aggiornamento'
                              . (!empty($r['is_mandatory'])
                                  ? ' <strong>(obbligatoria)</strong>' : '')
                            : 'installazione' ?></td>
                    <td><?= badge_html($r['is_active'] ? 'attiva' : 'ferma',
                                       $r['is_active'] ? 'success' : 'warning') ?></td>
                    <td>
                        <a class="btn btn-outline btn-sm"
                           href="/staff/releases.php?modifica=<?= (int)$r['id'] ?>">Modifica</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token"
                                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="azione" value="stato">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="attiva"
                                   value="<?= $r['is_active'] ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-outline btn-sm">
                                <?= $r['is_active'] ? 'Ferma' : 'Attiva' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/staff_footer.php'; ?>
