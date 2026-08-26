# Deployment (shared VPS: host nginx + dockerized PHP-FPM/Postgres)

This box already runs its own nginx (serving other, unrelated projects) and
its own Postgres (serving 2 other projects). This stack does **not** touch
either of those -- it only adds:

- A dedicated Postgres **container** for ISTM (internal-only, no host port
  published, so it can't collide with the Postgres already running here).
  One-off schema fixes that land after the initial `postgres-init` bootstrap
  live in `deploy/migrations/` -- run each new file there once against the
  production DB (they're written to be idempotent, safe to re-run).
- Two PHP-FPM **containers** (`gestion`, `portail`), each reachable only from
  `127.0.0.1` on ports `9101`/`9102`.
- Three **host** nginx vhost files (`nginx-host/*.conf`) that go into the
  existing nginx's `/etc/nginx/conf.d/`, proxying to those two ports.

| Subdomain | Proxies to | Docroot |
|---|---|---|
| `ucg-butembo.net` | `127.0.0.1:9101` (gestion) | `Projet_E-Gestion/` |
| `std.ucg-butembo.net` | `127.0.0.1:9101` (same container) | `Projet_E-Gestion/dossiers/` |
| `portail.ucg-butembo.net` | `127.0.0.1:9102` (portail) | `projet_website/` |

Everything below assumes the repo is checked out at **`/opt/ucg-butembo`**
on the host. If you used a different path, update it consistently in:
`docker-compose.yml` (both `volumes:` lines) and all three `nginx-host/*.conf`
files (`root` and the bind-mount paths must match exactly, on purpose -- see
the comment in `docker-compose.yml`).

## First-time setup

1. **Clone the repo** to `/opt/ucg-butembo` (as root or a user with access to
   `/opt`):
   ```
   git clone <this-repo-url> /opt/ucg-butembo
   cd /opt/ucg-butembo
   ```

2. **Install PHP dependencies on the host** (the container image is
   runtime-only; code is bind-mounted, so `vendor/` must exist on the host
   checkout before the container starts). No PHP is required on the host for
   this -- just Docker, using the `composer:2` image as a one-off tool:
   ```
   docker run --rm -v /opt/ucg-butembo/Projet_E-Gestion:/app -w /app composer:2 install --no-dev --optimize-autoloader
   ```
   (`projet_website` has no `composer.json`, nothing to do there.)

3. **App secrets**: copy `.env.example` to `.env` in both `Projet_E-Gestion/`
   and `projet_website/`:
   ```
   DB_DRIVER=pgsql
   DB_HOST=postgres
   DB_PORT=5432
   DB_NAME=istm_app        # or istm_site for projet_website/.env
   DB_USER=istm             # must match deploy/.env's POSTGRES_USER
   DB_PASS=<same as deploy/.env's POSTGRES_PASSWORD>
   ```
   Also set `STUDENT_PORTAL_URL=https://std.ucg-butembo.net` in
   `projet_website/.env`.

4. **Compose secrets**: copy `deploy/.env.example` to `deploy/.env`, set a
   real `POSTGRES_PASSWORD` (must match the app `.env` files above).

5. **Bring the containers up**:
   ```
   cd /opt/ucg-butembo/deploy
   docker compose up -d --build
   ```
   On first run (empty `pgdata` volume), `postgres-init/00-init.sh` creates
   `istm_app`/`istm_site` and loads the real migrated data
   (`istm_app.sql.gz`/`istm_site.sql.gz`). Runs once; `docker compose down -v`
   to force a reload (destroys the volume).

6. **Wire up the host nginx**:
   ```
   cp /opt/ucg-butembo/deploy/nginx-host/*.conf /etc/nginx/conf.d/
   nginx -t && systemctl reload nginx
   ```

7. **`uploads/` directories**: create them if they don't exist yet
   (`mkdir -p /opt/ucg-butembo/Projet_E-Gestion/uploads /opt/ucg-butembo/projet_website/uploads`)
   -- they're bind-mounted as part of each app's full directory, so whatever's
   on the host is what the app sees; nothing container-specific to manage.

8. **DNS**: point `ucg-butembo.net`, `std.ucg-butembo.net`, and
   `portail.ucg-butembo.net` at this VPS's IP.

9. **TLS**: the vhosts above are HTTP-only. Once DNS has propagated, run
   certbot once for all three hosts together (single SAN cert):
   ```
   certbot --nginx -d ucg-butembo.net -d std.ucg-butembo.net -d portail.ucg-butembo.net
   ```
   certbot edits each `nginx-host/*.conf` in place to add the `listen 443
   ssl` block, cert paths, and the http->https redirect.

## Notes / open items

- SELinux is disabled on this host, so no `semanage`/`setsebool` steps are
  needed for nginx to reach the php-fpm containers over localhost.
- `gestion` and the student portal share one PHP-FPM container (same
  codebase, same `vendor/`, same DB) -- nginx just points two different
  server blocks at two different docroots within it (`/` vs `/dossiers`).
- UOS is intentionally not part of this deployment -- only ISTM is in scope.
- This was validated offline (nginx config syntax, the Postgres
  init/restore script against a real container) but the actual PHP-FPM
  containers have not been end-to-end tested against a live nginx yet --
  do a real smoke test (login flows on all 3 subdomains) right after step 6.
