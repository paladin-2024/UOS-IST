# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A monolithic PHP (no framework) university management system for ISTM (`ucg-butembo.net`), made of three independently-deployed apps in this one repo:

- **`Projet_E-Gestion/`** — the main admin/ERP app (academics, finance, HR, stock, library, deliberations, etc.). Docroot for `ucg-butembo.net`.
- **`Projet_E-Gestion/dossiers/`** — a self-contained student-document sub-app with its own `index.php` front controller, `config/`, `controllers/`, `models/`. Docroot for `std.ucg-butembo.net`, but it's nested inside `Projet_E-Gestion/` and shares its `uploads/` conventions.
- **`projet_website/`** — the public marketing site. Docroot for `portail.ucg-butembo.net`. No `composer.json`; no dependencies.

**`_public_html/`** is a large, separately-tracked, *diverging* mirror of the above (mostly `Projet_E-Gestion/`, plus its own `dossiers/models/DossierModel.php` not present elsewhere). It is not referenced by `deploy/` or the GitHub workflows and is not the deployment target — treat it as legacy/parallel and do not assume changes made there are live, or that changes to `Projet_E-Gestion/` should be mirrored there. If a task isn't explicitly about `_public_html`, work in `Projet_E-Gestion/` / `projet_website/` only.

Database is PostgreSQL (migrated from a MySQL-era schema — see "Postgres quirks" below).

## Commands

There's no build step, bundler, package.json, or test suite — this is plain PHP served directly by PHP-FPM/nginx (or `php -S` locally).

**Lint** (`.github/workflows/ci.yml`'s `php-lint` job; relies on `php -l`'s exit code, not its stdout text, and skips `*/vendor/*` — two committed, separately-vendored third-party bundles live under `Projet_E-Gestion/assets/`):
```bash
find Projet_E-Gestion -name "*.php" -not -path "*/vendor/*" -print0 | xargs -0 -P "$(nproc)" php -l
find projet_website -name "*.php" -print0 | xargs -0 -P "$(nproc)" php -l
```
To lint a single file: `php -l path/to/file.php`.

**Migrations sanity** (`ci.yml`'s `migrations-sanity` job): spins up a throwaway Postgres, restores `deploy/postgres-init/istm_app.sql.gz`, and applies every file in `deploy/migrations/*.sql` twice (catches a broken/conflicting migration, and one that isn't actually idempotent despite claiming to be) — this is real CI coverage now, not just local testing.

**Install PHP deps** (`Projet_E-Gestion` only — `phpoffice/phpspreadsheet`, `endroid/qr-code`, `chillerlan/php-qrcode`, `tecnickcom/tcpdf`, `phpmailer/phpmailer`):
```bash
cd Projet_E-Gestion && composer install
```

**Run locally**: point a PHP-FPM/dev server at `Projet_E-Gestion/` (or `projet_website/`) as docroot, with a `.env` (copy from `.env.example`) pointing `DB_*` at a Postgres instance. `dossiers/` needs its own `.env`/config too if touched in isolation. If using PHP's built-in server (`php -S`), run it **from inside `Projet_E-Gestion/`** as `php -S host:port router.php` — that file exists specifically because `php -S` ignores `.htaccess` entirely, so without it a pretty-URL redirect (e.g. the post-login `Location: ../index`) falls back to serving `index.php` with an empty `$_GET['view']` instead of routing it, silently re-rendering the login page and making a *successful* login look broken.

**Full stack (mirrors production)**: `cd deploy && docker compose up -d --build` — see `deploy/README.md` for first-time setup (Postgres container init from `deploy/postgres-init/*.sql.gz`, nginx vhosts, secrets). Production deploy is automatic on push to `main` via `.github/workflows/deploy.yml` (SSH + `git reset --hard origin/main` + `docker compose up -d --build` + reload nginx) once CI passes.

**Deploy does not run migrations.** `deploy.yml` only pulls code and rebuilds containers — it never touches `deploy/migrations/`. If a PR adds a migration the new/fixed code depends on, **run it against production manually before (or immediately after) merging to `main`**, or the deployed code will hit the same errors it was written to fix, just from missing schema instead of missing allowlist entries. (Pattern used successfully on 2026-09-10: SSH to the VPS, `git fetch origin main`, pipe `git show origin/main:deploy/migrations/<file>.sql` into `docker exec -i deploy-postgres-1 sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d istm_app'` for each new migration, confirm no errors, *then* merge the code PR to `main` — or merge first and run migrations within the next minute or two, since the affected code paths are all previously-broken features, not regressions of working ones.)

**Important deploy trap** (bit them once, 2026-08-27): `deploy.yml` copies `deploy/nginx-host/*.conf` over the live nginx config on every deploy to `main`. Certbot edits the *live* files in place to add HTTPS, not the repo's copies — so after ever running `certbot`, the certbot-modified files must be copied back into `deploy/nginx-host/` and committed, or the next deploy silently wipes HTTPS for all three subdomains.

## Architecture

**No router, no framework, no ORM.** Each app is a flat collection of PHP scripts:

- `index.php` is a thin front controller for **views only**: `?view=academique/foo` maps to `views/academique/foo.php`, gated by an allowlist in `config/allowed_views.php` (`.` in the view name maps to `_` for the allowlist check, but the real file uses the original path — see `index.php`). Adding a new page = add the `.php` file under `views/` **and** add its dotted path to `config/allowed_views.php`, or it 403s.
- **`controller/`** (865+ files in `Projet_E-Gestion`) holds action endpoints hit directly by forms/AJAX (`controller/create_etudiant.php`, `controller/get_students.php`, etc.) — one file per action, not grouped into controller classes. Naming is a de facto convention: `create_*` / `add_*` (insert), `update_*` / `edit_*` (update), `delete_*` / `remove_*` (delete), `get_*` (AJAX JSON/HTML fetch), `export_*` (Excel/PDF export), `validate_*` (workflow approval). When adding a new action, follow the existing verb prefix rather than inventing a new naming scheme.
- **`models/`** are plain PHP classes (`User`, `Etudiant`, `Universite`, `Deliberation`, ...) that take a `Connexion::getInstance()->getPDO()` and run hand-written SQL directly (no query builder). Business logic and SQL live together in the model methods; controllers stay thin and mostly marshal `$_POST`/`$_GET` into model calls.
- **`config/chargement.php`**'s `charger()` does `require_once` on every file in `models/*.php` — new model files are auto-loaded, no registration needed.
- **`config/Connexion.php`** is a singleton PDO wrapper (`Connexion::getInstance()->getPDO()`), driven entirely by env vars loaded via `config/env.php`'s hand-rolled `.env` parser (`loadEnv()`, no external dotenv lib).
- Auth/session guarding is via `utils/Security.php`'s `AppSecurity` (`verifySession()`, `requireRole($roles)`, session timeout/regeneration, CSRF token helper) — used inconsistently across controllers (older ones roll their own `$_SESSION` checks).
- The `dossiers/` sub-app is a separate mini-MVC with its own front controller (`dossiers/index.php`, a `switch` on `$_GET['action']`) and its own `controllers/*Controller.php` + `models/` — don't confuse its `$_SESSION['dossier_*']` keys or routing style with the parent app's.

**Critical gotcha — `views/405.php`:** this file is `require_once`'d as the very first line of ~330 controllers, by convention, as a "reject disallowed HTTP methods" guard. It **must** return immediately (no output) for GET/POST and only render its 405 HTML page for other methods — because these controllers `header()`/redirect later, and any output already flushed causes a fatal "headers already sent" in production (PHP-FPM doesn't buffer like some local dev servers do). If you ever touch this file, re-verify the early-return guard is intact; a regression here breaks a huge fraction of the app's write paths at once (this exact bug already shipped once, commit `38377bd`).

**Postgres quirks to watch for**: the schema was migrated from MySQL and uses **camelCase, mixed-case column/table names** in places (e.g. `"designationPromotion"`, `"idRole"`), which Postgres folds to lowercase unless double-quoted — SQL in `models/` frequently needs `"camelCaseColumn"` quoting that plain lowercase names don't. This applies to `SELECT ... AS alias` just as much as real column references: an unquoted `AS someCamelCaseAlias` gets silently lowercased, so `$row['someCamelCaseAlias']` in the calling PHP returns nothing — a whole class of "field just doesn't show up" bugs (183 instances of this were found and fixed across ~30 files in one pass; if you're adding a new query with a mixed-case alias, quote it). When writing new queries against existing tables, check an existing query against the same table first rather than guessing casing.

**Missing IDENTITY on migrated tables**: several tables carried over from the MySQL migration have a primary key column but no `IDENTITY`/sequence/default — AUTO_INCREMENT didn't survive the port for these. An `INSERT` that (correctly, by MySQL habits) omits the PK column fails with a NOT NULL violation. `deploy/migrations/2026-09-10-missing-pk-identity-fix.sql` fixes the ones found so far (course creation, leave requests, internship reports, student import, e-card logging, reception, task comments) via a generalized per-table loop — if you add a new migrated table and inserts into it start failing the same way, extend that loop's `VALUES` list rather than writing a new one-off migration.

**Call-site/signature drift on `models/` methods — check every call site whenever you touch a method signature.** Because `controller/` files call model methods positionally (no named args, no static analysis, no test suite), a model method's parameter list drifting out of sync with its call site(s) is a recurring, silent bug class here — distinct from but often found alongside the Postgres column-casing issue above, since both stem from the schema/code having been restructured over time without every dependent site being updated. Two shapes, found across `frais_controller.php` (commit `2188324`) and a follow-up app-wide audit of `Universite.php`/`Ecue.php`/`Deliberation.php`/`Etudiant.php`/`Agent.php` (2026-09-22, `Deliberation::createDeliberation`, `Universite::createStudent`/`addPreparatoireStudent`, `Agent::updatePresence`, `DependanceServiceFrais::addDependance`):
- **Too few args for the method's required params** → fatal `ArgumentCountError` on PHP 8+, first hit the first time that code path executes. Easy to spot once reached, but may sit unreached for a long time (e.g. `update_presence.php` was never linked from any form and had this bug with zero live impact).
- **Right count, wrong positional slot** (usually after a signature gains/loses a param and one call site wasn't updated), or **more args than the method declares** (PHP does *not* error on excess args — they're just silently discarded, e.g. `Universite::createStudent()`'s extra `adresse`/`personne_contact`/`telephone_contact` args were dropped for years with zero error). Both are invisible to `php -l` and often invisible to the user too: the row gets written, just with wrong/missing data in some columns, or a typed column receives a string/ID meant for a different param and throws a PDOException that looks unrelated to the real cause.
- **Before flagging a call site as broken, confirm which class is actually instantiated** — this codebase has multiple classes with same-named methods and *different* signatures (`Universite::createDeliberation()` vs `Deliberation::createDeliberation()`, `Universite::createFrais()` vs `Frais::createFrais()`, `Soutenance::programmerSoutenance()` vs the unused `DepotSoutenance::programmerSoutenance()`) — grepping for `->methodName(` without tracing the preceding `new ClassName()` (and watching for local reassignment shadowing an earlier `new` in the same function) produces false positives.
- When you change a method's parameter list, `grep -rn '\->methodName(' --include="*.php" Projet_E-Gestion --exclude-dir=vendor` and check every call site's arg count *and* order, not just that it still runs.

**Standalone `Swal.fire()` echoes need the library loaded AND a `<body>` tag — this was a huge, app-wide bug, not a one-off.** `views/include/head.php`/`head_2.php` (the shared header most pages include) load `assets/js/sweetalert.min.js` from disk, so most pages are fine. But any `controller/*.php` action script whose error/result path is hit via a plain full-page form POST (not AJAX) renders its own response as a full document, and never includes that shared header — so it must load SweetAlert2 itself. A full app-wide audit (2026-09-22) found **213 controllers** doing `echo "<script>...Swal.fire(...)...</script>"` with **no library load and no `<body>` wrapper at all** — nearly every core write action in the app (`create_etudiant.php`, `create_user.php`, `change_password.php`, `update_etudiant.php`, and 209 more, across students/finance/HR/stock/deliberations). Two independent failure modes, both silently blank with zero visible error:
- No `<script src="assets/js/sweetalert.min.js">` (relative path from `controller/` is `../assets/js/sweetalert.min.js`) → `Swal` is undefined → throws immediately.
- Library loaded but no `<body>` opened first → browser parses a script-only response into an implicit `<head>`, `document.body` is null when SweetAlert2 tries to attach its modal → throws.

All 213 were fixed the same mechanical way: `echo "<script>` → `echo "<!DOCTYPE html><body><script src=\"../assets/js/sweetalert.min.js\"></script><script>` (verified in a real browser, not just `php -l` — this class of bug is invisible to `php -l` and to a plain `curl`, since the server-side response is a valid 200 either way; only actually rendering it in a browser and checking `document.body`/`typeof Swal` shows the crash). **If you add a new controller whose error path echoes a bare `Swal.fire()` and is reachable via a plain form POST, follow this exact pattern from the start** — this bug class has now shipped and been re-discovered three times in one day (login, profile/courriel, then the full 213-file sweep) before someone finally cross-referenced every plain-POST form target against every bare-Swal-echo controller instead of fixing them one report at a time.

**Security posture**: `.htaccess` sets CSP/HSTS/X-Frame-Options and blocks `.env`/`.sql`/`.git` etc. `models/SecurityUtils.php` (student e-card signing/hologram/verification) and `models/User.php` handle HMAC signing and image-upload validation respectively — follow their existing patterns (`hash_hmac`, `hash_equals` for constant-time comparison, `AppSecurity::validateImageUpload`) rather than introducing new ad hoc crypto/validation.

## Gitignore-relevant notes

- `**/config/flexpay.php` and `**/config/Config.php` are gitignored (still-hardcoded secrets not yet migrated to `.env`) — if you need them, they must already exist on disk locally; don't recreate them from scratch without checking with the user.
- `*.sql` is gitignored repo-wide (dumps are too large / contain prod data). `deploy/migrations/*.sql` and `deploy/postgres-init/*.sql.gz` are nonetheless tracked — they were committed before/despite the ignore rule, and already-tracked files stay tracked regardless of a later-added pattern. New `.sql` files you create elsewhere will silently not be added unless you `git add -f`.
- `Projet_E-Gestion/dossiers/uploads/*` is gitignored except `.gitkeep` (student-submitted documents, not code).
