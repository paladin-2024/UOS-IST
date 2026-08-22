-- Script pour vérifier et corriger les tables de gestion des dettes
-- ========================================================

-- Vérifier si la table dette_etudiant existe avec toutes les colonnes nécessaires
-- Si des colonnes manquent, les ajouter

-- Vérifier la table dette_etudiant
ALTER TABLE `dette_etudiant` 
    MODIFY COLUMN `session_rachat` int(11) DEFAULT NULL COMMENT 'Session où la dette a été rachetée',
    MODIFY COLUMN `annee_rachat` int(11) DEFAULT NULL COMMENT 'Année académique du rachat';

-- Vérifier la table dette_evaluation
-- S'assurer que toutes les colonnes existent
ALTER TABLE `dette_evaluation`
    MODIFY COLUMN `session_idsession` int(11) NOT NULL,
    MODIFY COLUMN `annee_acad_idannee_acad` int(11) NOT NULL;

-- Vérifier la table dette_historique
-- S'assurer que la colonne action accepte toutes les valeurs nécessaires
ALTER TABLE `dette_historique`
    MODIFY COLUMN `action` enum('Creation','Modification','Validation','Annulation','Enregistrement notes') NOT NULL;

-- Vérifier que la table cotes_grille existe
CREATE TABLE IF NOT EXISTS `cotes_grille` (
  `idpoints` int(11) NOT NULL AUTO_INCREMENT,
  `CC` decimal(10,2) DEFAULT NULL,
  `EX` decimal(10,2) DEFAULT NULL,
  `MF` decimal(10,2) DEFAULT NULL COMMENT 'Moyenne Finale calculée',
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `date_compilation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL,
  PRIMARY KEY (`idpoints`),
  KEY `idx_cotes_matricule` (`matricule`),
  KEY `idx_cotes_ecue` (`ECUE_idECUE`),
  KEY `idx_cotes_session` (`session_idsession`),
  KEY `idx_cotes_annee` (`annee_acad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vérifier que la table session existe
CREATE TABLE IF NOT EXISTS `session` (
  `idsession` int(11) NOT NULL AUTO_INCREMENT,
  `designSession` varchar(45) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  PRIMARY KEY (`idsession`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vérifier que la table annee_acad existe et a la colonne est_active
ALTER TABLE `annee_acad` 
    ADD COLUMN IF NOT EXISTS `est_active` tinyint(1) DEFAULT 0;

-- Mettre à jour une année académique comme active si aucune n'est active
UPDATE `annee_acad` 
SET `est_active` = 1 
WHERE `idannee_acad` = (SELECT MAX(`idannee_acad`) FROM (SELECT * FROM `annee_acad`) AS temp)
AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `annee_acad`) AS temp2 WHERE `est_active` = 1);

-- Ajouter des sessions par défaut si elles n'existent pas
INSERT IGNORE INTO `session` (`idsession`, `designSession`, `description`, `dateCreation`) VALUES
(1, 'Session 1', 'Première session', NOW()),
(2, 'Session 2', 'Deuxième session', NOW()),
(3, 'Session spéciale', 'Session spéciale de rattrapage', NOW());