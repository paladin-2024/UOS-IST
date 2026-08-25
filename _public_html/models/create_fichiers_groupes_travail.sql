-- Table pour stocker les fichiers PDF/Word par groupe pour les travaux pratiques
-- Utilisée quand l'option "Un fichier par groupe" est activée dans un TP de groupe

CREATE TABLE IF NOT EXISTS fichiers_groupes_travail (
    id SERIAL PRIMARY KEY,
    id_devoir INT NOT NULL,
    numero_groupe INT NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    date_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_devoir_groupe UNIQUE (id_devoir, numero_groupe),
    CONSTRAINT fk_fgt_devoir FOREIGN KEY (id_devoir) REFERENCES devoirs(iddevoir) ON DELETE CASCADE
);
