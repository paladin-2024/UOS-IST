# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

This is a working directory (not a git repo) containing hosting exports for two universities'
web presences — **ISTM** and **UOS** — each running the *same* PHP codebase against its own
database. There are three distinct applications plus their raw `.zip` backups and `.sql` dumps:

- **`projet_website/`** — public-facing institution website (news, events, staff, formations,
  pre-inscription/admissions forms, a small admin panel under `views/admin/`).
- **`Projet_E-Gestion/`** and **`_public_html/`** — the internal school-management system
  (grades/bulletins, HR, finance, stock, library, deliberations, student records). These two
  directories are near-identical copies of the same app (diff only in the `dossiers/` module) —
  treat `Projet_E-Gestion/` as the more complete one; `_public_html/` looks like an older/deployed
  snapshot lacking `dossiers/`. **Confirm which one you're meant to edit before making changes**,
  and check whether a change needs to be mirrored to the other.
- **`*.sql` dumps** at the repo root map to per-institution databases:
  `istm_bdd_app.sql` → `u421683743_istm`, `istm_site_bdd.sql` → `u421683743_istmsite`,
  `uos_bdd_app.sql` → `u421683743_uos`, `uos_bdd_site.sql` → `u421683743_uossite`.

There is no version control, no test suite, no JS/CSS build pipeline, and no CI here. "Development"
in this repo means editing PHP files directly and (optionally) running `composer install`.

## Working here safely

- **DB credentials are hardcoded in plaintext** in each app's `config/Connexion.php` (not read
  from `.env`), one set per institution/app. Never paste these into commit messages, logs, or
  anywhere they'd leave this machine.
- `.gitignore` in `Projet_E-Gestion/` implies these files are meant to stay untracked if this ever
  becomes a real git repo: `config/flexpay.php`, `config/.env`, `config/Config.php`.
- Since there's no VCS, there's no diff/rollback safety net — before editing an existing file,
  consider whether the change should be mirrored across `Projet_E-Gestion/` and `_public_html/`,
  and keep in mind the `.sql` dumps are large (up to ~440MB); avoid reading them in full.

## Commands

```bash
# Per-app PHP dependency install (each app has its own composer.json/lock)
cd Projet_E-Gestion && composer install
cd projet_website && composer install   # if a composer.json exists there

# Local run (no dev server config exists; e.g. quick smoke test)
php -S localhost:8000 -t Projet_E-Gestion
```

There is no lint, build, or test command configured in this repo — no `package.json`,
`phpunit.xml`, or CI config exist. Composer libraries in use: `phpoffice/phpspreadsheet`,
`endroid/qr-code`, `chillerlan/php-qrcode`, `tecnickcom/tcpdf`, `phpmailer/phpmailer`.

## Architecture (per app: `Projet_E-Gestion/`, `_public_html/`, `projet_website/`)

Each app is a hand-rolled MVC-ish structure with **no framework**:

- **`index.php`** is the single entry point / front controller. It calls `config/Connexion.php`
  (PDO singleton `Connexion::getInstance()->getPDO()`) and `config/chargement.php` (`charger()`,
  which `require_once`s every file in `models/*.php` — all models are always loaded, globally).
- **Routing is `?view=<path>`-based**, rewritten to pretty URLs by `.htaccess`
  (`RewriteRule ^(.*)$ index.php?view=$1`). `index.php` maps `view` to `views/<view>.php` and, in
  `Projet_E-Gestion`/`_public_html`, validates it against an **allowlist** in
  `config/allowed_views.php` (~500 entries) before including it — adding a new view requires
  adding it to that allowlist or it 403s.
- **`views/`** are grouped by business domain as subfolders (`academique/`, `grh/`, `finance/`,
  `budget/`, `stock/`, `deliberation/`, `laboratoire/`, `bibliotheque/`, etc.) — mirrors the
  institution's administrative departments, not a technical layering.
- **`controller/`** (in the E-Gestion app) holds ~850 standalone action scripts (one file per
  form submission / AJAX action, e.g. `addCategory.php`, `add_payment.php`), each independently
  doing `session_start()`, including the models/config it needs, and handling its own POST logic
  inline (no shared request-dispatch layer). `.htaccess` excludes `controller/` and `api/` paths
  from the view-rewrite rule so these are hit directly.
- **`api_controller/`** is a separate, newer-style JSON REST API (used by the student-facing
  mobile/SPA flows — login, dashboard, courses, schedule, paiements, résultats) with proper
  `Content-Type: application/json`, CORS headers, and token-based auth (`auth.php`). This is
  architecturally distinct from `controller/` (HTML/redirect-based, session-cookie auth) — don't
  conflate the two when adding endpoints.
- **`models/`** are plain PHP classes wrapping PDO queries per domain entity (`Etudiant.php`,
  `Enseignant.php`, `Comptabilite.php`, `Deliberation.php`, `FlexPay.php` for payment gateway
  integration, etc.) — no ORM, no query builder.
- **`dossiers/`** (only in `Projet_E-Gestion/`) is a self-contained sub-application (its own
  `index.php`, `config/`, `controllers/`, `models/`, `views/`, `uploads/`) for document
  submission/review — more conventional MVC than the rest of the app, don't assume its
  conventions apply elsewhere.
- Session-based auth uses `$_SESSION['id']` / role checks scattered per-controller rather than
  centralized middleware — when adding a protected action, check how sibling files in the same
  `controller/` or `views/` subfolder guard access rather than inventing a new pattern.

`projet_website/` is simpler: `controller/get_*.php` / `process_*.php` scripts return data to the
front end, `views/admin/` is a lightweight CMS for managing site content, and `includes/visitor_tracking.php`
logs visits on every request.

## Conventions to follow when editing

- New view files go under the matching domain subfolder in `views/`, and (E-Gestion/`_public_html`)
  must be added to `config/allowed_views.php` or they'll 403.
- New form-submission endpoints follow the existing `controller/<verb>_<entity>.php` naming
  (e.g. `add_x.php`, `update_x.php`, `delete_x.php`) and the inline session-start +
  require-models-then-handle-POST pattern already used by neighboring files — match it rather
  than introducing a new request-handling style.
- User-facing feedback in `controller/` scripts is done via inline `<script>Swal.fire(...)</script>`
  echoed into the response, not JSON — follow this for HTML-flow controllers; use JSON responses
  only in `api_controller/`.
