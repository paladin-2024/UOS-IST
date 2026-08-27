-- suivi_enseignements -- referenced by views/enseignement/suivi_enseignements.php
-- (linked from the newly-restored "Enseignements" sidebar module) but never
-- captured in the original MySQL->Postgres schema migration. Found while
-- verifying the restored sidebar links actually work. Idempotent
-- (CREATE TABLE IF NOT EXISTS), safe to re-run.

CREATE TABLE IF NOT EXISTS suivi_enseignements (
    id                      SERIAL PRIMARY KEY,
    "idECUE"                INTEGER NOT NULL REFERENCES ecue("idECUE") ON DELETE CASCADE,
    enseignant_id           INTEGER REFERENCES agent("idAgent") ON DELETE SET NULL,
    date_cours              DATE NOT NULL,
    heure_debut             TIME NOT NULL,
    heure_fin               TIME NOT NULL,
    type_cours              VARCHAR(15) NOT NULL DEFAULT 'CM'
                            CHECK (type_cours IN ('CM','TD','TP','Evaluation')),
    salle                   VARCHAR(100),
    commentaire             TEXT,
    annee_acad_idannee_acad INTEGER NOT NULL REFERENCES annee_acad(idannee_acad) ON DELETE CASCADE,
    "idUser"                INTEGER NOT NULL REFERENCES t_users("idUser"),
    date_creation           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_suivi_enseignements_ecue ON suivi_enseignements("idECUE");
CREATE INDEX IF NOT EXISTS idx_suivi_enseignements_enseignant ON suivi_enseignements(enseignant_id);
CREATE INDEX IF NOT EXISTS idx_suivi_enseignements_date ON suivi_enseignements(date_cours);
CREATE INDEX IF NOT EXISTS idx_suivi_enseignements_annee ON suivi_enseignements(annee_acad_idannee_acad);
