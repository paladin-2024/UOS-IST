# Deployment (Docker + nginx)

Three subdomains, two codebases, one Postgres instance:

| Subdomain | Service | Docroot |
|---|---|---|
| `ucg-butembo.wscsarl.info` | `gestion` | `Projet_E-Gestion/` |
| `std-ucg-butembo.wscsarl.info` | `gestion` (same container) | `Projet_E-Gestion/dossiers/` |
| `portail-ucg-butembo.wscsarl.info` | `portail` | `projet_website/` |

## First-time setup

1. **App secrets**: copy `.env.example` to `.env` in both `Projet_E-Gestion/` and
   `projet_website/`. For Docker, the DB connection values must be:
   ```
   DB_DRIVER=pgsql
   DB_HOST=postgres
   DB_PORT=5432
   DB_NAME=istm_app        # or istm_site for projet_website/.env
   DB_USER=istm            # must match deploy/.env's POSTGRES_USER
   DB_PASS=<same as deploy/.env's POSTGRES_PASSWORD>
   ```
   (`DB_HOST=/var/run/postgresql`/`DB_PORT=5433` from local dev only works
   because that's a Unix socket on the host machine -- inside Docker, the
   Postgres server is a separate container reachable by its service name.)

2. **Compose secrets**: copy `deploy/.env.example` to `deploy/.env` and set a
   real `POSTGRES_PASSWORD` (must match what you put in the two app `.env`
   files above).

3. **Bring the stack up**:
   ```
   cd deploy
   docker compose up -d --build
   ```
   On first run (empty `pgdata` volume), `postgres-init/00-init.sh` creates
   the `istm_app` and `istm_site` databases and loads
   `istm_app.sql.gz`/`istm_site.sql.gz` -- real dumps of the migrated data,
   not the original MySQL dumps. This only runs once; to force a reload,
   `docker compose down -v` first (destroys the volume).

4. **DNS**: point all three subdomains at this host's IP. nginx here is
   HTTP-only (port 80) -- for HTTPS, either put this behind a reverse proxy /
   load balancer that terminates TLS, or add certbot (webroot or DNS-01
   challenge) and a second `listen 443 ssl` server block per vhost. Not set
   up here since it depends on how DNS/the host is actually provisioned.

## Notes

- `gestion` and the student portal share one PHP-FPM container (same
  codebase, same `vendor/`, same DB) -- nginx just points two different
  server blocks at two different docroots within it (`/` vs `/dossiers`).
- `uploads/` on each app is a named bind mount, not baked into the image, so
  uploaded files survive container rebuilds.
- UOS is intentionally not part of this deployment -- only ISTM is in scope
  right now.
