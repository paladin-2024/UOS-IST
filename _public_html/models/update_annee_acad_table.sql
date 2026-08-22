-- Script pour ajouter la colonne est_active à la table annee_acad si elle n'existe pas déjà

-- Vérifier et ajouter la colonne est_active si elle n'existe pas
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'annee_acad'
    AND COLUMN_NAME = 'est_active'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE annee_acad ADD COLUMN est_active INT(11) NOT NULL DEFAULT 0 COMMENT "Indique si l\'année académique est active (1) ou non (0)"',
    'SELECT "La colonne est_active existe déjà dans la table annee_acad" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optionnel : Mettre à jour les données existantes pour avoir une année active par défaut
-- (Vous pouvez décommenter cette partie si nécessaire)
/*
UPDATE annee_acad SET est_active = 0;
UPDATE annee_acad 
SET est_active = 1 
WHERE idannee_acad = (
    SELECT idannee_acad 
    FROM (
        SELECT idannee_acad 
        FROM annee_acad 
        ORDER BY dateCreation DESC 
        LIMIT 1
    ) AS latest_year
);
*/