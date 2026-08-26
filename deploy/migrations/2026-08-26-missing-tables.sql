-- Tables referenced by real application forms/controllers that were never
-- captured in the original MySQL->Postgres schema migration (the "plan de
-- travail" thesis-chapter workflow, appointment scheduling, document
-- requests, and one notifications table). Found via a full form-controller
-- audit. Idempotent (CREATE TABLE IF NOT EXISTS), safe to re-run.

-- 1. rendez_vous / type_rendez_vous -- reception appointment scheduling
CREATE TABLE IF NOT EXISTS rendez_vous (
    "idRendez_vous"      SERIAL PRIMARY KEY,
    "Agent_idAgent"      INTEGER NOT NULL REFERENCES agent("idAgent"),
    "Service_idService"  INTEGER NOT NULL REFERENCES service("idService"),
    contact_externe      VARCHAR(255),
    email_externe        VARCHAR(255),
    telephone_externe    VARCHAR(20),
    date_rendez_vous     DATE NOT NULL,
    heure_debut          TIME NOT NULL,
    heure_fin            TIME NOT NULL,
    objet                VARCHAR(500) NOT NULL,
    description          TEXT,
    lieu                 VARCHAR(255),
    statut_rendez_vous   VARCHAR(20) DEFAULT 'planifie'
                         CHECK (statut_rendez_vous IN ('planifie','confirme','reporte','annule','termine')),
    type_rendez_vous     VARCHAR(100),
    priorite             VARCHAR(10) DEFAULT 'normale'
                         CHECK (priorite IN ('basse','normale','haute','urgente')),
    rappel_active        SMALLINT DEFAULT 1,
    delai_rappel         INTEGER DEFAULT 30,
    commentaires         TEXT,
    date_creation        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cree_par             INTEGER NOT NULL,
    modifie_par          INTEGER
);

CREATE TABLE IF NOT EXISTS type_rendez_vous (
    "idType_rendez_vous" SERIAL PRIMARY KEY,
    designation          VARCHAR(100) NOT NULL,
    description          TEXT,
    duree_defaut         INTEGER DEFAULT 60,
    couleur              VARCHAR(7) DEFAULT '#007bff',
    actif                SMALLINT DEFAULT 1,
    "Service_idService"  INTEGER REFERENCES service("idService")
);

-- 2. demandes_documents -- student document-request emails
CREATE TABLE IF NOT EXISTS demandes_documents (
    id                       SERIAL PRIMARY KEY,
    idetudiant               INTEGER NOT NULL REFERENCES etudiant(idetudiant) ON DELETE CASCADE,
    matricule                VARCHAR(50) NOT NULL,
    document_obligatoire_id  INTEGER NOT NULL REFERENCES documents_obligatoires(id) ON DELETE CASCADE,
    objet                    VARCHAR(255) NOT NULL,
    contenu                  TEXT NOT NULL,
    date_envoi               TIMESTAMP NOT NULL,
    email_envoye             SMALLINT NOT NULL DEFAULT 0,
    "idUser"                 INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_demandes_documents_etudiant ON demandes_documents(idetudiant);
CREATE INDEX IF NOT EXISTS idx_demandes_documents_matricule ON demandes_documents(matricule);
CREATE INDEX IF NOT EXISTS idx_demandes_documents_document ON demandes_documents(document_obligatoire_id);

-- 3. notifications_documents -- similar notification log for document actions
CREATE TABLE IF NOT EXISTS notifications_documents (
    id           SERIAL PRIMARY KEY,
    idetudiant   INTEGER NOT NULL REFERENCES etudiant(idetudiant) ON DELETE CASCADE,
    matricule    VARCHAR(255) NOT NULL,
    objet        VARCHAR(255) NOT NULL,
    contenu      TEXT NOT NULL,
    date_envoi   TIMESTAMP NOT NULL,
    "idUser"     INTEGER NOT NULL
);

-- 4. plan de travail (thesis work-plan) feature -- entirely missing schema
CREATE TABLE IF NOT EXISTS plan_travail (
    idplan_travail        SERIAL PRIMARY KEY,
    idsujets              INTEGER NOT NULL REFERENCES sujets(idsujets),
    titre_plan            VARCHAR(500) NOT NULL,
    introduction          TEXT,
    problematique         TEXT,
    objectifs             TEXT,
    methodologie          TEXT,
    statut_validation     VARCHAR(20) NOT NULL DEFAULT 'En attente'
                          CHECK (statut_validation IN ('En attente','Validé','Rejeté','Modifié')),
    commentaire_directeur TEXT,
    date_soumission       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_validation       TIMESTAMP,
    "idValidateur"        INTEGER,
    version               INTEGER NOT NULL DEFAULT 1,
    "idUser"              INTEGER
);

CREATE TABLE IF NOT EXISTS chapitre_plan (
    idchapitre_plan            SERIAL PRIMARY KEY,
    idplan_travail             INTEGER NOT NULL REFERENCES plan_travail(idplan_travail) ON DELETE CASCADE,
    numero_chapitre            INTEGER NOT NULL,
    titre_chapitre             VARCHAR(500) NOT NULL,
    description                TEXT,
    objectifs_chapitre         TEXT,
    ordre_affichage            INTEGER NOT NULL DEFAULT 1,
    statut                     VARCHAR(20) NOT NULL DEFAULT 'En attente'
                               CHECK (statut IN ('En attente','En cours','Terminé','En révision')),
    deadline                   DATE,
    date_attribution_deadline  TIMESTAMP,
    commentaire_deadline       TEXT,
    pourcentage_avancement     INTEGER DEFAULT 0,
    date_soumission            TIMESTAMP,
    fichier_chapitre           VARCHAR(255),
    commentaire_directeur      TEXT,
    note_chapitre              NUMERIC(4,2),
    "idUser"                   INTEGER,
    date_creation              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS deadline_assignment (
    iddeadline             SERIAL PRIMARY KEY,
    idchapitre_plan        INTEGER REFERENCES chapitre_plan(idchapitre_plan) ON DELETE CASCADE,
    idsection_chapitre     INTEGER,
    type_element           VARCHAR(20) NOT NULL CHECK (type_element IN ('chapitre','section','plan_global')),
    deadline               DATE NOT NULL,
    description_deadline  TEXT,
    priorite               VARCHAR(10) NOT NULL DEFAULT 'Moyenne'
                           CHECK (priorite IN ('Faible','Moyenne','Haute','Critique')),
    statut_deadline        VARCHAR(15) NOT NULL DEFAULT 'Active'
                           CHECK (statut_deadline IN ('Active','Reportée','Terminée','Annulée')),
    date_attribution       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "idDirecteur"          INTEGER NOT NULL,
    notification_etudiant SMALLINT DEFAULT 0,
    date_notification      TIMESTAMP,
    rappel_active          SMALLINT DEFAULT 1,
    jours_rappel           INTEGER DEFAULT 7
);

CREATE TABLE IF NOT EXISTS plan_validation_history (
    id             SERIAL PRIMARY KEY,
    idplan_travail INTEGER NOT NULL REFERENCES plan_travail(idplan_travail) ON DELETE CASCADE,
    statut         VARCHAR(20) NOT NULL CHECK (statut IN ('En attente','Validé','Rejeté','Modifié')),
    commentaire    TEXT,
    date_action    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "idUser"       INTEGER NOT NULL,
    version_plan   INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS echange_chapitre (
    idechange_chapitre SERIAL PRIMARY KEY,
    idchapitre_plan    INTEGER NOT NULL REFERENCES chapitre_plan(idchapitre_plan) ON DELETE CASCADE,
    type_auteur        VARCHAR(15) NOT NULL CHECK (type_auteur IN ('Directeur','Encadreur','Etudiant')),
    "idAuteur"         INTEGER NOT NULL,
    commentaire        TEXT NOT NULL,
    fichier_joint      VARCHAR(255),
    type_fichier       VARCHAR(50),
    statut_lecture     VARCHAR(10) NOT NULL DEFAULT 'Non lu'
                       CHECK (statut_lecture IN ('Non lu','Lu','Traité')),
    date_echange       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reponse_a          INTEGER
);

CREATE TABLE IF NOT EXISTS notification_plan (
    idnotification     SERIAL PRIMARY KEY,
    destinataire_id    INTEGER NOT NULL,
    type_destinataire  VARCHAR(15) NOT NULL CHECK (type_destinataire IN ('Etudiant','Directeur','Encadreur')),
    type_notification  VARCHAR(30) NOT NULL CHECK (type_notification IN (
                           'Nouveau plan','Plan validé','Plan rejeté','Plan à modifier','Deadline assignée',
                           'Deadline proche','Chapitre soumis','Chapitre validé','Chapitre en révision','Commentaire ajouté'
                       )),
    titre              VARCHAR(255) NOT NULL,
    message            TEXT NOT NULL,
    idplan_travail     INTEGER REFERENCES plan_travail(idplan_travail) ON DELETE CASCADE,
    idchapitre_plan    INTEGER REFERENCES chapitre_plan(idchapitre_plan) ON DELETE CASCADE,
    iddeadline         INTEGER REFERENCES deadline_assignment(iddeadline) ON DELETE CASCADE,
    statut_lecture     SMALLINT DEFAULT 0,
    date_notification  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_lecture       TIMESTAMP
);
