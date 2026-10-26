# gtmc-npi.eu — GTMC-NPI website

Marketing, distribution and **license server** website for **GTMC-NPI**, the intranet platform that runs the whole launch of a new product (NPI) and change management on one controlled workflow: projects and waves, tasks and dependencies, interactive Gantt, milestone readiness gates G0–G5, versioned documents, budget approvals, quality checklists, multi-level notifications, change management and the optional IATF 16949 rule profile.

It is the twin site of [gtmc-angajati.eu](https://gtmc-angajati.eu) (`../Angajati-Website-PHP`): same architecture, same design (dark glass, aurora indigo-violet, Space Grotesk + Inter), same staff back-office logic — content and license model adapted to GTMC-NPI.

Plain PHP 8.1+ and MySQL/MariaDB, no framework, no Composer. It runs on standard cPanel hosting.

## Structure

- `index.php`, `features.php`, `benefits.php`, `pricing.php`, `requirements.php`, `download.php`, `demo.php`, `about.php`, `security.php`: public pages.
- Module pages (`includes/feature_page.php` template, list in `includes/feature_list.php`): `projects`, `tasks`, `gantt`, `readiness`, `documents`, `budget`, `checklist`, `notifications`, `change`, `iatf`, `capacity`, `dashboards`.
- `privacy-policy.php`, `cookie-policy.php`, `terms-of-use.php`, `gdpr.php`: legal pages.
- `blog/`: articles in `blog/articles/*.json`, written in 6 languages.
- `auth/*`: registration, login, email verification, password reset.
- `portal/*`: customer area (licenses, trial key, downloads, proforma invoices, tickets).
- `api.php`: **license server** used by the app. It handles `?action=license_validate`, `license_deactivate` and `health`. The logic lives in `includes/license_lib.php`.
- `api/*`: activation, offline activation, updates, newsletter, tickets and payments.
- `staff/*`: back-office for licenses (create/edit/revoke, offline token, audit), proformas, releases, tickets, newsletter and pricing — **same logic as the Angajati staff portal** (roles `admin`, `manager`, `sales`).
- `sql/001_schema.sql`: the full consolidated schema (tables + seed + views + release targeting + triggers). One file, fresh install.
- `i18n/*.json`: translations (en, it, de, ro, fr, es), used through `t('section.key')`.
- `tools/`: CLI scripts (RSA key generation, staff user creation).
- `tests/`: CLI tests. Run them with `php tests/run.php`.

## License model (GTMC-NPI)

- Installation / license keys use the prefix `NPI-XXXXX-XXXXX-XXXXX-XXXXX`.
- Tiers: `trial` (14 days, 3 active projects, 5 users, 1 installation), `professional` (one company, no IATF/bridge/NRE, up to 3 installations), `enterprise` (unlimited, multi-company, IATF profile, client bridge, NRE, API, up to 5 installations).
- Plan limits live on `plans.max_projects` / `max_users` / `max_activations` and are editable from Staff → Pricing. Seed prices (990 / 1990 EUR) are placeholders: **confirm them before going live**.
- Signed token payload: `{v, key, company, level, max_users, max_projects, features, valid_until, fingerprint, iat, grace_days, offline}`. The GTMC-NPI web app verifies it with its configured public key (`GTMC_SECRETS_DIR`); the RSA keypair of this site must match it (see DEPLOY.md §5).

## Installation

See **[DEPLOY.md](DEPLOY.md)**.

## Local development

```bash
php -S localhost:8080
php tests/run.php        # requires the openssl and mbstring extensions
```

## Notes

- No secrets live in the repository. The private RSA key stays outside `public_html` (`LICENSE_RSA_PRIVATE_KEY_PATH`).
- Default license grace is 30 days (`LICENSE_DEFAULT_GRACE_DAYS`), matching the NPI spec: 30 days of tolerance after expiry, then read-only.
