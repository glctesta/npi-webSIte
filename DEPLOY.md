# DEPLOY — gtmc-npi.eu

Deployment guide for the GTMC-NPI website and license server on a cPanel (Apache + PHP + MySQL) host. Same procedure as gtmc-angajati.eu, adapted to this product.

## 1. Requirements

- **PHP 8.1+** with these extensions: `openssl` (license signing), `pdo_mysql`, `mbstring`, `json`, `curl`, `zip`, `fileinfo`.
- **MySQL 5.7+ / MariaDB 10.3+**, utf8mb4.
- Apache with `mod_rewrite` and `mod_headers`, since the `.htaccess` rules depend on both.
- An SSL certificate for `gtmc-npi.eu` and `www.gtmc-npi.eu`. cPanel AutoSSL is fine.

## 2. Upload the files

Upload the repository content into `public_html/`. Do **not** upload `.git/`, `tests/` or `tools/` if you can avoid it. The root `.htaccess` blocks web access to `includes/ i18n/ sql/ tools/ tests/ cron/ keys/` and to every `*.pem / *.sql / *.md / .env` file anyway.

Writable folders: `uploads/releases`, `uploads/updates`, `uploads/tickets` and `uploads/payments` (already present, empty). Permission `755` is enough.

## 3. Database

1. In cPanel → MySQL Databases, create the database `r145004gtmc_npi` and a DB user, then grant it ALL PRIVILEGES. (The `r145004` part is the cPanel account prefix; adjust to the real account.)
2. In phpMyAdmin, import **`sql/001_schema.sql`** — the only migration file. It creates all tables, the `npi` product, the 3 plans (trial / professional / enterprise), release 1.0.0, the release-targeting table (`release_plans`), the version-sort function and triggers, and the monitoring views.

The newsletter tables (`newsletter_subscribers`, `newsletter_campaigns`, `newsletter_deliveries`) are created automatically by `includes/newsletter.php` on first use.

## 4. `.env` (outside public_html)

Copy `.env.example` to `/home/USER/.env`, one level **above** `public_html`, and fill in:

| Key | Notes |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Use the cPanel-prefixed names |
| `SITE_URL` | `https://gtmc-npi.eu` |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | `mail.gtmc-npi.eu`, 465, `info@gtmc-npi.eu` |
| `SUPPORT_EMAIL`, `STAFF_EMAIL` | `help@gtmc-npi.eu`, `info@gtmc-npi.eu` |
| `LICENSE_RSA_PRIVATE_KEY_PATH` | `/home/USER/keys/license_private.pem` (§5) |
| `LICENSE_DEFAULT_GRACE_DAYS` | `30`: days of tolerance after expiry (NPI spec: then read-only) |
| `LICENSE_MASTER_KEY` | A long random string, e.g. `openssl rand -hex 32` |
| `STRIPE_*`, `PAYPAL_*` | Optional |

Then run `chmod 600 /home/USER/.env`.

## 5. License signing key (IMPORTANT)

The GTMC-NPI web app verifies every token with the **public key configured on its side** (`GTMC_SECRETS_DIR`). The site must sign with the matching **private key**.

### Option A: dedicated new key pair (recommended for a new product)

```bash
php tools/generate_rsa_keys.php /home/USER/keys/license_private.pem
```

This writes the private key (3072-bit, chmod 600) and refuses any path inside the website folder. It prints the public key: configure it in the GTMC-NPI backend (`backend/services/license_service.py` side, secrets dir) before the first activation.

### Option B: reuse an existing GTMC key

If the app side already embeds/holds a public key, copy the matching private key over SFTP to `/home/USER/keys/license_private.pem` (outside `public_html`), `chmod 700` the folder and `chmod 600` the file.

Check: `https://gtmc-npi.eu/api.php?action=health` must return `"status":"ok","signing":true`. In the staff portal, "Offline token" must not show the "key not configured" warning.

**Symptom when this step is skipped**: license activation answers `HTTP 500 {error:"server_error"}` for every valid key.

## 6. Staff user, cron, mail

```bash
cd ~/public_html
php tools/create_staff.php info@gtmc-npi.eu "Admin Name" admin   # prompts for the password (min 12 chars)
```

Staff portal: `https://gtmc-npi.eu/staff/`. The roles are `admin`, `manager` and `sales` — identical logic to the Angajati staff portal.

**No SSH/Terminal on your host?** Run the CLI tools as one-shot cron jobs (cPanel → Cron Jobs, "Once Per Minute", delete the cron after it runs): see **DEPLOY_FTP.md §5-bis**. The RSA tool also writes the public key to `/home/USER/keys/license_public.pem` so you can read it from File Manager.

Cron (cPanel → Cron Jobs): license expiry reminders at 30, 15 and 7 days:

```
0 6 * * * /usr/local/bin/php /home/USER/public_html/cron/cron_license_expiry.php >> /home/USER/logs/cron_expiry.log 2>&1
```

Mail: create the mailboxes `info@` and `help@`, and set up SPF, DKIM and DMARC in cPanel → Email Deliverability.

Releases: upload the GTMC-NPI deployment package to `uploads/releases/` (and update packages to `uploads/updates/`) over FTP or from Staff → Releases. The names must match the entries in the staff portal.

## 7. License server endpoint

App protocol (same shape as gtmc-angajati.eu, fields adapted):

- `POST https://gtmc-npi.eu/api.php?action=license_validate`. JSON body: `{license_key, fingerprint, hostname, app_version, protocol, projects, users}`.
  - Success: `200 {success:true, token}`.
  - Errors: `400 bad_request`, `404 invalid_key`, `403 revoked | expired | activation_limit | machine_deactivated`.
- `POST /api.php?action=license_deactivate {license_key, fingerprint}` → `200 {success:true}`.
- `GET /api.php?action=health`.

## 8. Checks after deployment

- `https://gtmc-npi.eu/` loads in all 6 languages (`?lang=ro` and the others).
- `https://gtmc-npi.eu/includes/config.php`, `/.env` and `/sql/001_schema.sql` all return **403/404**.
- `curl -X POST "https://gtmc-npi.eu/api.php?action=license_validate" -d '{}' -H "Content-Type: application/json"` → `400 bad_request`.
- Staff → Pricing: **confirm the seed prices** (990 / 1990 EUR are placeholders) — changes are live immediately.
- Locally, before each deploy: `php tests/run.php` must report all suites passed.
- Search Console: submit `https://gtmc-npi.eu/sitemap.xml`.
