-- Idempotent migration for two DB objects introduced by application code
-- during the MySQL->Postgres syntax sweep, that don't exist on databases
-- created before this date (only ever applied via docker-entrypoint-initdb.d
-- on a fresh volume, so existing production data needs this run manually).
-- Safe to re-run.

-- 1. fichiers_groupes_travail (istm_app) -- used by
--    controller/travaux_cours_controller.php for ON CONFLICT upserts.
CREATE TABLE IF NOT EXISTS fichiers_groupes_travail (
    id            SERIAL PRIMARY KEY,
    id_devoir     INTEGER NOT NULL REFERENCES devoirs(iddevoir) ON DELETE CASCADE,
    numero_groupe INTEGER NOT NULL,
    fichier       VARCHAR(255) NOT NULL,
    date_upload   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_devoir_groupe UNIQUE (id_devoir, numero_groupe)
);

-- 2. configuration_moyenne (istm_app) -- used by
--    models/Evaluation.php::configureFormules() for ON CONFLICT upserts.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'configuration_moyenne_unique'
    ) THEN
        ALTER TABLE configuration_moyenne
            ADD CONSTRAINT configuration_moyenne_unique
            UNIQUE ("idECUE", session_idsession, annee_acad_id);
    END IF;
END $$;
