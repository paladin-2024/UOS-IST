-- Schema for the "dossiers" (Espace Étudiants) module.
--
-- These tables never existed in the original MySQL dump this project was
-- migrated from -- the feature was apparently deployed straight to
-- production at some point without its schema ever being captured in a
-- dump or migration file. Inferred from how dossiers/models/DossierModel.php
-- actually queries these tables; applied by hand to istm_app during the
-- Postgres migration. Run this against a fresh database to reproduce it.
--
--   psql -d istm_app -f Projet_E-Gestion/dossiers/models/schema.sql

CREATE TABLE IF NOT EXISTS dn_type_document (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    designation VARCHAR(255) NOT NULL,
    description TEXT,
    cycle_requis VARCHAR(100),
    est_obligatoire SMALLINT NOT NULL DEFAULT 1,
    ordre_affichage INTEGER NOT NULL DEFAULT 0,
    est_actif SMALLINT NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS dn_dossier (
    id SERIAL PRIMARY KEY,
    etudiant_idetudiant INTEGER NOT NULL,
    annee_acad_idannee_acad INTEGER NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_cours',
    commentaire_admin TEXT,
    date_validation TIMESTAMP,
    validateur_id INTEGER,
    pourcentage_completion INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS dn_dossier__etudiant ON dn_dossier (etudiant_idetudiant, annee_acad_idannee_acad);

CREATE TABLE IF NOT EXISTS dn_document (
    id SERIAL PRIMARY KEY,
    dossier_id INTEGER NOT NULL REFERENCES dn_dossier(id) ON DELETE CASCADE,
    type_document_id INTEGER NOT NULL REFERENCES dn_type_document(id),
    nom_fichier_original VARCHAR(255) NOT NULL,
    nom_fichier_stocke VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    taille_fichier INTEGER,
    type_mime VARCHAR(100),
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    commentaire_validation TEXT,
    validateur_id INTEGER,
    date_validation TIMESTAMP,
    date_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS dn_document__dossier ON dn_document (dossier_id);
CREATE INDEX IF NOT EXISTS dn_document__type ON dn_document (type_document_id);

CREATE TABLE IF NOT EXISTS dn_journal (
    id SERIAL PRIMARY KEY,
    dossier_id INTEGER,
    document_id INTEGER,
    utilisateur_type VARCHAR(20) NOT NULL,
    utilisateur_id INTEGER,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    date_action TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS dn_journal__dossier ON dn_journal (dossier_id);
