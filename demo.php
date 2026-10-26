<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/license_lib.php';

// Percorso sicuro per tornare alla pagina da cui l'utente e' arrivato al form
// demo: accetta solo path relativi dello stesso host, mai domini esterni.
function demo_return_path(?string $url): string {
    $fallback = '/demo.php';
    if (!$url) return $fallback;
    if (preg_match('/[\x00-\x1f\x7f]/', $url)) return $fallback;
    $parts = @parse_url($url);
    if ($parts === false) return $fallback;
    if (!empty($parts['port']) || !empty($parts['user']) || !empty($parts['pass'])) return $fallback;
    if (!empty($parts['host'])) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || strcasecmp((string)$parts['host'], $host) !== 0) return $fallback;
    }
    $path = $parts['path'] ?? '';
    if ($path === '' || $path[0] !== '/' || strpos($path, '//') === 0) return $fallback;
    $query = $parts['query'] ?? '';
    return $path . ($query !== '' ? '?' . $query : '');
}

// Pagine di servizio: come origine di ritorno non hanno senso (dopo una
// richiesta demo non si riporta l'utente su login/registrazione/portal...).
function demo_origin_allowed(string $path): bool {
    if (stripos($path, '/download.php') === 0) return false;
    return preg_match('#^/(auth|portal|staff|api|cron)(/|$)#i', $path) !== 1;
}

// Pagina di provenienza: primo il parametro esplicito (link "from"), poi la
// pagina precedente ricordata in sessione (config.php), poi il Referer — il
// primo che porta a un percorso valido diverso dal fallback vince.
function demo_origin_path(): string {
    $fallback = '/demo.php';
    $candidates = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $candidates[] = $_POST['from'] ?? null;
    }
    $candidates[] = $_GET['from'] ?? null;
    $candidates[] = $_SESSION['prev_page'] ?? null;
    $candidates[] = $_SERVER['HTTP_REFERER'] ?? null;
    foreach ($candidates as $c) {
        if ($c === null || $c === '') continue;
        $safe = demo_return_path((string)$c);
        if ($safe !== $fallback && demo_origin_allowed($safe)) return $safe;
    }
    return $fallback;
}

// Telefono "reale": 8-15 cifre, niente lettere, niente cifre tutte uguali,
// niente sequenze banali (12345678, 87654321). La prova vera richiederebbe
// un OTP via SMS; qui si filtrano gli input inventati.
function phone_plausible(string $phone): bool {
    $trimmed = trim($phone);
    if ($trimmed === '' || preg_match('/[A-Za-z]/', $trimmed)) return false;
    $digits = preg_replace('/\D+/', '', $trimmed);
    $len = strlen($digits);
    if ($len < 8 || $len > 15) return false;
    if (preg_match('/^(\d)\1+$/', $digits)) return false;
    $asc = true; $desc = true;
    for ($i = 1; $i < $len; $i++) {
        if ((int)$digits[$i] !== (int)$digits[$i - 1] + 1) $asc = false;
        if ((int)$digits[$i] !== (int)$digits[$i - 1] - 1) $desc = false;
    }
    if ($asc || $desc) return false;
    return true;
}

// Domini di posta temporanea: un indirizzo su questi non porta a nessuno.
function email_domain_blocked(string $email): bool {
    static $blocked = [
        'mailinator.com', 'yopmail.com', 'yopmail.fr', 'guerrillamail.com',
        'guerrillamail.net', 'sharklasers.com', 'grr.la', '10minutemail.com',
        '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'throwawaymail.com',
        'trashmail.com', 'getnada.com', 'dispostable.com', 'maildrop.cc',
        'mailnesia.com', 'emailondeck.com', 'fakeinbox.com', 'moakt.com',
    ];
    $domain = strtolower(substr(strrchr(trim($email), '@') ?: '', 1));
    if ($domain === '') return true;
    foreach ($blocked as $b) {
        if ($domain === $b || str_ends_with($domain, '.' . $b)) return true;
    }
    return false;
}

// Il dominio di posta deve esistere davvero (MX, altrimenti A/AAAA).
function email_domain_resolves(string $email): bool {
    $domain = substr(strrchr(trim($email), '@') ?: '', 1);
    if ($domain === '' || !str_contains($domain, '.')) return false;
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
}

// Chiave di licenza per la richiesta demo confermata. Il programma di
// installazione la chiede allo step "Licență": qui si riusa l'account
// utente (creandolo coi dati del form se non esiste) e una licenza
// attiva esistente, emettendo una trial NPI- a 14 giorni solo se non
// ce n'e' nessuna. Idempotente: riaprire il link mostra la stessa chiave.
function demo_issue_license_key(array $req): array {
    $user = db_fetch("SELECT * FROM users WHERE email = ?", [$req['email']]);
    if (!$user) {
        // Password casuale: l'account si sblocca con "password dimenticata".
        $parts = preg_split('/\s+/u', trim((string)($req['fullname'] ?? '')), 2);
        $first = ($parts[0] ?? '') !== '' ? $parts[0] : 'Demo';
        $last  = ($parts[1] ?? '') !== '' ? $parts[1] : '';
        db_insert(
            "INSERT INTO users (email, password_hash, first_name, last_name, company, phone, vat_id, email_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $req['email'],
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                mb_substr($first, 0, 100),
                mb_substr($last, 0, 100),
                $req['company'] ?: null,
                $req['phone'] ?: null,
                $req['vat'] ?: null,
            ]
        );
        $user = db_fetch("SELECT * FROM users WHERE email = ?", [$req['email']]);
    } elseif (!(int)$user['email_verified']) {
        // Ha appena dimostrato che la casella e' sua.
        db_query("UPDATE users SET email_verified = 1 WHERE id = ?", [$user['id']]);
    }

    // 1) la licenza gia' collegata alla richiesta, 2) una qualunque attiva
    // non scaduta dell'utente, 3) altrimenti trial nuova.
    $lic = null;
    if (!empty($req['license_id'])) {
        $lic = db_fetch("SELECT * FROM licenses WHERE id = ? AND status = 'active'", [(int)$req['license_id']]) ?: null;
    }
    if (!$lic) {
        $lic = db_fetch(
            "SELECT * FROM licenses
             WHERE user_id = ? AND status = 'active'
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id DESC LIMIT 1",
            [$user['id']]
        ) ?: null;
    }
    if (!$lic) {
        $trial = db_fetch("SELECT id, is_active FROM plans WHERE slug = 'trial' LIMIT 1");
        if ($trial) {
            if ((int)($trial['is_active'] ?? 0) !== 1) {
                db_query("UPDATE plans SET is_active = 1 WHERE id = ?", [$trial['id']]);
            }
            $trial_id = (int)$trial['id'];
        } else {
            $product = db_fetch("SELECT id FROM products WHERE slug = ? LIMIT 1", [PRODUCT_SLUG]);
            $product_id = $product
                ? (int)$product['id']
                : (int)db_insert(
                    "INSERT INTO products (slug, name, description, is_active) VALUES (?, 'GTMC-NPI', 'NPI & change management under control.', 1)",
                    [PRODUCT_SLUG]
                );
            $trial_id = (int)db_insert(
                "INSERT INTO plans (product_id, slug, name, price_eur, billing_period, sort_order, is_active, features)
                 VALUES (?, 'trial', 'Trial', 0.00, 'trial', 0, 1, ?)",
                [$product_id, '["14-day evaluation","3 active projects","5 users","Single installation"]']
            );
        }
        do {
            $license_key = lic_generate_key();
        } while (db_fetch("SELECT id FROM licenses WHERE license_key = ?", [$license_key]));
        $lic_id = db_insert(
            "INSERT INTO licenses (user_id, plan_id, license_key, status, company_name, starts_at, expires_at, grace_days, max_activations)
             VALUES (?, ?, ?, 'active', ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), ?, 1)",
            [$user['id'], $trial_id, $license_key, $req['company'] ?: ($user['company'] ?? null), LICENSE_DEFAULT_GRACE_DAYS]
        );
        lic_audit($lic_id, $license_key, 'demo:trial_created', null, 'demo request ' . (int)$req['id']);
        $lic = db_fetch("SELECT * FROM licenses WHERE id = ?", [$lic_id]);
    }

    if ((int)($req['license_id'] ?? 0) !== (int)$lic['id']) {
        db_query("UPDATE demo_requests SET license_id = ? WHERE id = ?", [(int)$lic['id'], (int)$req['id']]);
    }

    return [$lic, $user];
}

$return_to = demo_origin_path();

// Conferma della richiesta via link nell'email (doppio opt-in). Lookup
// idempotente come auth/verify.php: gli scanner anti-phishing pre-cliccano
// i link, quindi il successo non consuma il token (scade da solo per data).
//
// Alla conferma viene anche consegnata la chiave di licenza NPI- che
// l'installer chiede: per email (solo alla PRIMA conferma, cosi' un
// refresh del link non rispedisce messaggi) e qui a video, con il
// pulsante di copia — se l'email finisce in spam l'utente non si blocca.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['verify'])) {
    $verify_ok = false;
    $lic = null;
    $lic_user = null;
    $key_mail_failed = false;
    try {
        $row = db_fetch(
            "SELECT * FROM demo_requests WHERE verify_token = ?",
            [(string)$_GET['verify']]
        );
        $still_valid = $row
            && !empty($row['verify_token_expiry'])
            && strtotime($row['verify_token_expiry']) >= time();
        if ($row && ((int)$row['email_verified'] === 1 || $still_valid)) {
            $first_confirm = (int)$row['email_verified'] !== 1;
            if ($first_confirm) {
                db_query("UPDATE demo_requests SET email_verified = 1 WHERE id = ?", [$row['id']]);
            }
            $verify_ok = true;

            // Emissione separata dalla verifica: se il DB qui fallisce,
            // la conferma resta valida e si mostra il messaggio generico.
            try {
                [$lic, $lic_user] = demo_issue_license_key($row);
                if ($first_confirm && $lic) {
                    $name = trim($lic_user['first_name'] . ' ' . $lic_user['last_name']) ?: $lic_user['email'];
                    if (!send_demo_license_email($lic_user['email'], $name, $lic['license_key'], $lic['expires_at'])) {
                        $key_mail_failed = true;
                    }
                }
            } catch (Exception $e) {
                error_log('Demo license issue failed: ' . $e->getMessage());
                $lic = null;
            }
        }
    } catch (Exception $e) {
        error_log('Demo verify lookup failed: ' . $e->getMessage());
    }

    $page_title = t('nav.demo');
    require __DIR__ . '/includes/header.php';
    echo '<div class="auth-page"><div class="glass-card auth-card large reveal" style="text-align: center;">';
    if ($verify_ok) {
        echo '<div class="step-icon" style="margin: 0 auto 1rem;">✅</div>';
        echo '<h2>' . htmlspecialchars(t('demo.verify_success')) . '</h2>';

        if ($lic) {
            echo '<p style="line-height:1.7;">' . htmlspecialchars(t('demo.key_intro')) . '</p>';

            if ($key_mail_failed) {
                echo '<div style="margin:1.25rem 0 0;padding:0.9rem 1.1rem;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.35);border-left:4px solid #f59e0b;border-radius:8px;font-size:.85rem;color:#fbbf24;text-align:left;">'
                    . htmlspecialchars(t('demo.key_mail_failed')) . '</div>';
            }

            echo '<div style="margin:2rem 0;padding:1.5rem;background:rgba(0,0,0,.3);border:1px solid rgba(16,185,129,.4);border-radius:12px;">';
            echo '<p style="margin:0 0 .75rem;color:var(--text-muted,#94a3b8);font-size:.75rem;text-transform:uppercase;letter-spacing:1px;">' . htmlspecialchars(t('demo.key_label')) . '</p>';
            echo '<code id="demoLicenseKey" style="display:inline-block;font-family:monospace;font-size:1.3rem;letter-spacing:3px;color:#10b981;background:rgba(16,185,129,.08);padding:.9rem 1.2rem;border-radius:8px;word-break:break-all;">' . htmlspecialchars($lic['license_key']) . '</code>';
            echo '<div style="margin-top:1rem;">';
            echo '<button type="button" class="btn btn-primary" onclick="demoCopyKey()">📋 ' . htmlspecialchars(t('demo.key_copy')) . '</button>';
            echo '<span id="demoCopyFeedback" style="margin-left:.75rem;color:#10b981;font-weight:600;opacity:0;transition:opacity .2s;">✓ ' . htmlspecialchars(t('demo.key_copied')) . '</span>';
            echo '</div></div>';

            echo '<div style="text-align:left;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);border-left:4px solid #6366f1;border-radius:8px;padding:1rem 1.1rem;font-size:.85rem;line-height:1.7;color:var(--text-secondary,#cbd5e1);">';
            echo '<strong style="color:var(--primary-light,#818cf8);">' . htmlspecialchars(t('demo.key_why_title')) . '</strong> ' . htmlspecialchars(t('demo.key_why'));
            if (!empty($lic['expires_at'])) {
                echo '<br><strong style="color:var(--primary-light,#818cf8);">' . htmlspecialchars(t('demo.key_trial_label')) . '</strong> ' . htmlspecialchars(date('d.m.Y', strtotime($lic['expires_at'])) . ' — ' . t('demo.key_trial_days'));
            }
            echo '<br><strong style="color:var(--primary-light,#818cf8);">' . htmlspecialchars(t('demo.key_mail_note_label')) . '</strong> ' . htmlspecialchars(t('demo.key_mail_note'));
            echo '<br><strong style="color:var(--primary-light,#818cf8);">' . htmlspecialchars(t('demo.key_account_label')) . '</strong> ' . htmlspecialchars(t('demo.key_account'));
            echo '</div>';

            echo '<a href="/download.php" class="btn btn-primary" style="display:inline-block;margin-top:1.75rem;text-decoration:none;">⬇️ ' . htmlspecialchars(t('demo.key_download_btn')) . '</a>';

            echo '<script>
function demoCopyKey() {
    var t = document.getElementById("demoLicenseKey").textContent.trim();
    var fb = document.getElementById("demoCopyFeedback");
    function ok(){ fb.style.opacity = "1"; setTimeout(function(){ fb.style.opacity = "0"; }, 2500); }
    function fb2(){
        var ta = document.createElement("textarea");
        ta.value = t; ta.style.position = "fixed"; ta.style.opacity = "0";
        document.body.appendChild(ta); ta.select();
        try { document.execCommand("copy"); ok(); } catch (e) {}
        document.body.removeChild(ta);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(t).then(ok, fb2);
    } else { fb2(); }
}
</script>';
        } else {
            // Conferma ok ma emissione chiave non riuscita: il vecchio
            // messaggio generico, la chiave arrivera' dallo staff.
            echo '<p>' . htmlspecialchars(t('demo.verify_success_desc')) . '</p>';
        }
    } else {
        echo '<div class="step-icon" style="margin: 0 auto 1rem;">❌</div>';
        echo '<h2>' . htmlspecialchars(t('demo.verify_error')) . '</h2>';
        echo '<p>' . htmlspecialchars(t('demo.verify_error_desc')) . '</p>';
    }
    echo '</div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // Honeypot: real users never see/fill the "website" field
    if (!empty($_POST['website'])) {
        flash(t('demo.flash_check_email'), 'success');
        header('Location: ' . $return_to);
        exit;
    }
    $fullname = mb_substr(trim($_POST['fullname'] ?? ''), 0, 200);
    $email = trim($_POST['email'] ?? '');
    $company = mb_substr(trim($_POST['company'] ?? ''), 0, 200);
    $phone = mb_substr(trim($_POST['phone'] ?? ''), 0, 50);
    $vat = mb_substr(trim($_POST['vat'] ?? ''), 0, 50);
    $message = mb_substr(trim($_POST['message'] ?? ''), 0, 5000);
    $privacy = isset($_POST['privacy']) ? 1 : 0;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = '';
    }
    // CUI / partita IVA: almeno 4 caratteri alfanumerici (RO12345678, IT01234567890, ...)
    $vat_valid = (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\/-]{3,48}$/', $vat);
    $email_real = $email && !email_domain_blocked($email) && email_domain_resolves($email);
    $phone_real = phone_plausible($phone);

    if ($fullname && $email_real && $company && $phone_real && $vat_valid && $privacy) {
        $user_id = is_logged_in() ? ($_SESSION['user_id'] ?? null) : null;
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+48 hours'));
        try {
            db_insert(
                "INSERT INTO demo_requests (user_id, email, fullname, company, vat, phone, message, status, email_verified, verify_token, verify_token_expiry)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?)",
                [$user_id, $email, $fullname, $company, $vat, $phone, $message, $token, $expiry]
            );

            // Doppio opt-in: la richiesta conta solo dopo il clic sul link;
            // lang= cosi' la pagina di conferma parla la lingua della richiesta
            send_demo_verify_email($email, $fullname, SITE_URL . '/demo.php?verify=' . $token . '&lang=' . urlencode(get_lang()));

            // Optional: send a notification to support
            $subject = SITE_NAME . ' - New demo request from ' . $email;
            $body = '<p>A new demo request was submitted.</p>';
            $body .= '<p><strong>Name:</strong> ' . htmlspecialchars($fullname) . '<br>';
            $body .= '<strong>Email:</strong> ' . htmlspecialchars($email) . '<br>';
            $body .= '<strong>Company:</strong> ' . htmlspecialchars($company) . '<br>';
            $body .= '<strong>VAT / CUI:</strong> ' . htmlspecialchars($vat) . '<br>';
            $body .= '<strong>Phone:</strong> ' . htmlspecialchars($phone) . '<br>';
            $body .= '<strong>Message:</strong> ' . nl2br(htmlspecialchars($message)) . '</p>';

            try {
                $mailer = new SmtpMailer();
                $mailer->sendMail(SUPPORT_EMAIL, $subject, $body);
            } catch (Exception $e) {
                error_log('Demo notification email failed: ' . $e->getMessage());
            }

            flash(t('demo.flash_check_email'), 'success');
            // Come su gtmc-npi.eu: si torna alla pagina di provenienza,
            // senza passare dalla pagina di download.
            header('Location: ' . $return_to);
            exit;
        } catch (Exception $e) {
            error_log('Demo request insert failed: ' . $e->getMessage());
            // Non e' un errore di compilazione (spesso mancano le colonne
            // delle migrazioni 013/014): messaggio distinto, non il banner
            // "compila tutti i campi" che confonde l'utente.
            flash(t('common.error'), 'error');
        }
    } elseif (!$email_real) {
        flash(t('demo.error_email'), 'error');
    } elseif (!$phone_real) {
        flash(t('demo.error_phone'), 'error');
    } else {
        flash(t('demo.error_fields'), 'error');
    }
}

$page_title = t('nav.demo');
$meta_description = t('seo.demo_desc');
require __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="glass-card auth-card large demo-form reveal">
        <div class="auth-header">
            <h2><?= htmlspecialchars(t('demo.title')) ?></h2>
            <p><?= htmlspecialchars(t('demo.subtitle')) ?></p>
        </div>

        <form action="/demo.php" method="POST">
<?= csrf_field() ?>
            <input type="hidden" name="from" value="<?= htmlspecialchars($return_to, ENT_QUOTES) ?>">
            <div style="position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="grid-3" style="gap: 1rem; grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="fullname"><?= htmlspecialchars(t('demo.fullname')) ?></label>
                    <input type="text" id="fullname" name="fullname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email"><?= htmlspecialchars(t('demo.email')) ?></label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
            </div>

            <div class="grid-3" style="gap: 1rem; grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="company"><?= htmlspecialchars(t('demo.company')) ?></label>
                    <input type="text" id="company" name="company" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="vat"><?= htmlspecialchars(t('demo.vat')) ?></label>
                    <input type="text" id="vat" name="vat" class="form-control" required maxlength="50" placeholder="RO12345678">
                </div>
            </div>

            <div class="grid-3" style="gap: 1rem; grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="phone"><?= htmlspecialchars(t('demo.phone')) ?></label>
                    <input type="tel" id="phone" name="phone" class="form-control" required>
                </div>
                <div class="form-group"></div>
            </div>

            <div class="form-group">
                <label for="message"><?= htmlspecialchars(t('demo.message')) ?></label>
                <textarea id="message" name="message" class="form-control" rows="4"></textarea>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="privacy" name="privacy" required>
                <label for="privacy" style="margin: 0; font-weight: normal; font-size: 0.85rem;">
                    <?= htmlspecialchars(t('demo.gdpr_agree')) ?>
                    <a href="/gdpr" target="_blank" rel="noopener noreferrer" style="color: var(--primary-light, #818cf8); font-weight: 600; text-decoration: underline;"><?= htmlspecialchars(t('demo.gdpr_link')) ?></a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars(t('demo.submit')) ?></button>

            <div style="margin-top: 1.25rem; padding: 1rem 1.1rem; background: rgba(99,102,241,.08); border: 1px solid rgba(99,102,241,.25); border-left: 4px solid #6366f1; border-radius: 8px; font-size: 0.85rem; line-height: 1.7; color: var(--text-secondary, #cbd5e1); text-align: left;">
                <strong style="color: var(--primary-light, #818cf8);">📧 <?= htmlspecialchars(t('demo.flow_note_title')) ?></strong><br>
                <?= htmlspecialchars(t('demo.flow_note_body')) ?>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
