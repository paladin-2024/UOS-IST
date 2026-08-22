#!/bin/sh
# Runs once, only on first container start (empty data volume), via the
# official postgres image's docker-entrypoint-initdb.d mechanism.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE istm_app;
    CREATE DATABASE istm_site;
EOSQL

gunzip -c /docker-entrypoint-initdb.d/istm_app.sql.gz | psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname istm_app
gunzip -c /docker-entrypoint-initdb.d/istm_site.sql.gz | psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname istm_site
