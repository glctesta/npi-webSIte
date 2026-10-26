# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Primario**: **produttori** (elettronica, elettromeccanica, automotive tier-2) che lanciano nuovi prodotti e gestiscono i change: valutano e acquistano **GTMC-NPI** (chiavi `NPI-…`): demo, prova, licenza per sede.
- **Cliente registrato**: usa il portale (`portal/`) per download, licenze, proforma e ticket.
- **Staff GTMC**: usa il back-office (`staff/`) per licenze, proforma, release e ticket.
- **La piattaforma GTMC-NPI** (intranet, backend Python/SQL Server) chiama `api.php?action=license_validate` per attivazione e heartbeat.

## Product Purpose

Sito **gtmc-npi.eu**: vetrina di **GTMC-NPI**, la piattaforma intranet che porta l'intero lancio di un prodotto nuovo e il change management su un solo workflow controllato — progetti e wave, task e dipendenze, Gantt interattivo, readiness di milestone calcolata dal sistema (porte G0–G5), documenti versionati con hash, budget con approvazioni, checklist qualità, notifiche multilivello, profilo IATF 16949 opzionale. Homepage = landing GTMC-NPI (hero "Every launch under control", registro "What you leave behind", "What you gain", ecosistema GTMC + hook di prezzo).

**Successo**: download prova, prenotazioni demo, acquisti/rinnovi licenza.

## Positioning

- "Every launch under control, every single day": readiness provata dal sistema, mai dichiarata a mano.
- Meno del costo di qualche ora di coordinamento ingegneristico al mese.
- Un solo workflow controllato per NPI e change: regole di blocco binarie, decisioni con istantanea di readiness, configurazione versionata e congelabile.
- Dati su SQL Server del cliente, in intranet, mai su cloud; il controllo licenze è l'unico contatto con questo sito.
- Parte dell'ecosistema GTMC (gtmc.ro, gtmc-mes.eu, gtmc-skills.eu, gtmc-meeting.eu, gtmc-hsb.eu, gtmc-angajati.eu).

## Operating Context

- B2B europeo: proforma → bonifico → caricamento pagamento → attivazione; Stripe opzionale.
- Chiavi `NPI-`; API licenze/aggiornamenti con token firmati RSA.
- PHP 8.1+ / MySQL, Apache, i18n JSON (en default + it, de, ro, fr, es).
- Sviluppo: `php -S localhost:8080`; test: `php tests/run.php`.
- Newsletter con iscrizione footer e campagne da staff.

## Capabilities and Constraints

**Confermate:** pagine pubbliche (index, features, benefits, pricing, requirements, download, demo, about, security), 12 pagine modulo NPI, blog, auth, portale clienti, API (licenze, pagamenti, proforma, ticket, newsletter), staff portal, legali, sitemap.

**Aperte:** prezzi (seed 990/1990 EUR da confermare), contenuto blog (vuoto), og-default definitiva.

## Brand Commitments

- **GTMC-NPI**, dominio **gtmc-npi.eu**, produttore **GTMC SRL**.
- Logo in `static/images/`; 6 lingue con parità; chiavi `NPI-`.
- Design: dark glass, aurora indigo-violet, Space Grotesk + Inter (identico a gtmc-angajati.eu).
- Non citare mai il testo della norma IATF 16949 (copyright): solo numeri di requisito.

## Evidence on Hand

- `version.json`, `uploads/releases/` per distribuzione.
- Schema `sql/001_schema.sql` (consolidato).
- Copy i18n `i18n/*.json`; `DEPLOY.md` per cPanel.
- Progetto gemello di riferimento: `C:\Users\User\PythonProjetcs\Python\Angajati-Website-PHP` (gtmc-angajati.eu).

**Da non fabbricare:** nessuna testimonianza, case study o numero installazioni non verificati; nessun prezzo definitivo senza conferma.

## Product Principles

1. **Fiducia B2B**: licenze, GDPR, sicurezza first-class.
2. **Parità multilingua**: 6 lingue o non esiste.
3. **Self-service**: download → prova → attivazione, proforma come coda umana.
4. **Ecosistema GTMC**: parte della famiglia, non prodotto isolato.
5. **Parità col gemello Angajati**: stessa logica staff, stesso protocollo licenze — diverge solo nei contenuti e nel modello di limiti (progetti/utenti/installs).
