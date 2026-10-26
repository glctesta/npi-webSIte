# DEPLOY_FTP — caricare gtmc-npi.eu via FTP

Procedura per caricare il sito (repo `npi-webSIte`) sullo spazio cPanel di `gtmc-npi.eu`.
Tre metodi: **A** zip + estrazione da cPanel (più veloce, consigliato per il primo
caricamento), **B** FileZilla (visuale), **C** script PowerShell (`tools/deploy-ftp.ps1`,
ideale per gli aggiornamenti da riga di comando).

---

## 0. Dati servono

| Dato | Esempio | Note |
|---|---|---|
| Host FTP | `ftp.gtmc-npi.eu` | oppure l'IP del server (cPanel → FTP Accounts) |
| Porta | `21` | FTPS esplicito se disponibile |
| Utente | `r145004` o `r145004@gtmc-npi.eu` | il prefisso `r145004` è quello dell'account cPanel: **sostituiscilo con il tuo** |
| Password | — | da cPanel → FTP Accounts |
| Cartella remota | `public_html/` | destinazione del sito |

⚠️ Mai caricare via FTP: `.env`, `keys/`, `*.pem`, `*.key` — restano fuori da `public_html` (§5).

**Cosa NON va caricato** (il sito non lo usa o è riservato):
`.git/`, `tests/`, `tools/`, `sql/`, `*.md`, `.gitignore`, `.gitkeep`, `.env*`.
Il `.htaccess` blocca comunque l'accesso web a questi percorsi, ma non caricarli è più pulito.
Il file `.htaccess` **va caricato** (è il file che protegge `includes/ i18n/ sql/ cron/` ecc.).
`i18n/` e `includes/` **vanno caricati** anche se protetti dall'accesso web: il PHP li legge da disco.

---

## Metodo A — zip + estrazione (consigliato)

1. **Crea lo zip locale** (Git Bash, dalla cartella del repo):
   ```bash
   cd /c/Users/User/PythonProjetcs/Python/NPI-Website-PHP
   zip -r ../gtmc-npi-site.zip . -x ".git/*" "tests/*" "tools/*" "sql/*" "*.md" \
       ".gitignore" ".gitkeep" "*.pem" "*.key" ".env*"
   ```
   Ottieni `C:\Users\User\PythonProjetcs\Python\gtmc-npi-site.zip`.

2. **Carica via cPanel File Manager**: login cPanel → *File Manager* → apri `public_html`
   → *Upload* → seleziona lo zip (oppure trascinalo).

3. **Estrai**: tasto destro sullo zip in `public_html` → *Extract*. Se `public_html`
   non è vuoto, verifica prima che non ci siano file vecchi da non sovrascrivere
   (il primo caricamento deve trovare `public_html` pulito o contenente solo
   `.well-known/` per il certificato AutoSSL: non cancellare quello).

4. **Cancella lo zip** dal server dopo l'estrazione.

5. Prosegui con la **configurazione** (§5).

---

## Metodo B — FileZilla

1. Scarica FileZilla Client → *File* → *Site Manager* → *New site*:
   - Protocollo: `FTP - File Transfer Protocol` (spunta **Encryption: "Use explicit FTP over TLS"** se disponibile)
   - Host: `ftp.gtmc-npi.eu`, Porta: `21`
   - Tipo di accesso: `Normale`, utente e password cPanel.
2. Pannello sinistro (locale): apri `C:\Users\User\PythonProjetcs\Python\NPI-Website-PHP`.
3. Pannello destro (remoto): apri `public_html/`.
4. **Primo caricamento**: seleziona tutto tranne le esclusioni del §0
   (tieni `Ctrl` per multi-selezione: `.git`, `tests`, `tools`, `sql`, i `.md`,
   `.gitignore`, `.env.example` — quest'ultimo si carica volendo, tanto il server
   lo legge solo da `/home/USER/.env`) → trascina su `public_html`.
5. Attiva *Transfer → Keep timestamps* a piacere; modalità passiva è il default.
6. **Aggiornamenti successivi**: trascina solo i file/carte modificati e conferma
   la sovrascrittura. FileZilla può confrontare le cartelle (*Server → Compare directories*).

---

## Metodo C — script PowerShell (aggiornamenti rapidi)

`tools/deploy-ftp.ps1` carica ricorsivamente il repo applicando da solo tutte le
esclusioni del §0 (e crea le cartelle remote mancanti).

Prova in dry-run (elenca cosa caricerebbe, senza connettersi):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools\deploy-ftp.ps1 `
  -FtpHost ftp.gtmc-npi.eu -FtpUser r145004 `
  -PromptPass -DryRun
```

Caricamento reale (FTPS esplicito, passivo, password richiesta in modo sicuro):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools\deploy-ftp.ps1 `
  -FtpHost ftp.gtmc-npi.eu -FtpUser r145004 -PromptPass -UseSsl
```

Con `-RemoteDir public_html` (default) e `-LocalRoot .` (default = cartella corrente).
Per un aggiornamento di un solo file aggiungi `-Filter "staff/releases.php"`.

---

## 5. Configurazione dopo il primo caricamento

La procedura completa è in **DEPLOY.md** (§3–§8); qui il sintetico:

1. **DB**: cPanel → MySQL Databases → crea `r145004gtmc_npi` + utente con ALL PRIVILEGES;
   phpMyAdmin → importa `sql/001_schema.sql` (scaricalo dal repo, non serve caricarlo).
2. **`.env`**: copia `.env.example` in `/home/USER/.env` (una cartella SOPRA `public_html`,
   via File Manager o SSH), compila DB/SITE_URL/SMTP/chiavi, `chmod 600`.
3. **Chiave RSA** (importante: senza, l'attivazione licenze risponde 500):
   cPanel → Terminal (o SSH):
   ```bash
   cd ~/public_html && php tools/generate_rsa_keys.php /home/USER/keys/license_private.pem
   ```
   (il tool rifiuta percorsi dentro public_html; nota: se hai caricato via FTP senza
   `tools/`, carica temporaneamente `tools/generate_rsa_keys.php`, esegui, poi cancellalo).
   La chiave **pubblica** stampata va configurata nel backend GTMC-NPI.
4. **Staff**: `php tools/create_staff.php info@gtmc-npi.eu "Nome Cognome" admin`
   (chiede la password, min 12 caratteri).
5. **Cron**: cPanel → Cron Jobs → `0 6 * * * /usr/local/bin/php /home/USER/public_html/cron/cron_license_expiry.php >> /home/USER/logs/cron_expiry.log 2>&1`
6. **Permessi**: `uploads/releases`, `uploads/updates`, `uploads/tickets`, `uploads/payments`
   scrivibili (755 basta con PHP come stesso utente).

## 6. Verifiche post-caricamento

- `https://gtmc-npi.eu/` apre in tutte le 6 lingue (`?lang=ro`, `?lang=it`, …).
- `https://gtmc-npi.eu/includes/config.php`, `/.env`, `/sql/001_schema.sql` → **403/404**.
- `https://gtmc-npi.eu/api.php?action=health` → `{"status":"ok","signing":true}` dopo la chiave RSA.
- `curl -X POST "https://gtmc-npi.eu/api.php?action=license_validate" -d '{}' -H "Content-Type: application/json"` → `400 bad_request`.
- Staff → Pricing: conferma i prezzi seed (990/1990 € sono segnaposto).
- Search Console: invia `https://gtmc-npi.eu/sitemap.xml`.

## Rollback

Il primo caricamento è su un dominio nuovo: per ripartire puliti basta cancellare
in File Manager i file estratti in `public_html` (mantieni `.well-known/`) e ricaricare.
Dopo il go-live, tieni una copia dello zip della versione in esercizio: ricaricarlo
sovrascrive in modo identico (gli `uploads/` e il DB non si toccano).
