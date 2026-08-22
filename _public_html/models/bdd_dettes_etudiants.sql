-- ========================================
-- SYSTÈME DE GESTION DES DETTES ÉTUDIANTS
-- ========================================

-- Table pour stocker les dettes des étudiants
CREATE TABLE IF NOT EXISTS `dette_etudiant` (
  `id_dette` int(11) NOT NULL AUTO_INCREMENT,
  `matricule` varchar(255) NOT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `UE_idUE` int(11) NOT NULL,
  `semestre_idsemestre` int(11) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `note_obtenue` decimal(5,2) DEFAULT NULL COMMENT 'Note obtenue avant de monter avec dette',
  `credits_ecue` int(11) NOT NULL COMMENT 'Nombre de crédits de l''ECUE',
  `statut` enum('En cours','Validée','Annulée') DEFAULT 'En cours',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `note_rachat` decimal(5,2) DEFAULT NULL COMMENT 'Note obtenue après rachat',
  `session_rachat` int(11) DEFAULT NULL COMMENT 'Session où la dette a été rachetée',
  `annee_rachat` int(11) DEFAULT NULL COMMENT 'Année académique du rachat',
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur qui a créé l''enregistrement',
  PRIMARY KEY (`id_dette`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour l'historique des modifications des dettes
CREATE TABLE IF NOT EXISTS `dette_historique` (
  `id_historique` int(11) NOT NULL AUTO_INCREMENT,
  `id_dette` int(11) NOT NULL,
  `action` enum('Creation','Modification','Validation','Annulation') NOT NULL,
  `details` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id_historique`),
  KEY `fk_historique_dette` (`id_dette`),
  KEY `fk_historique_dette_user` (`idUser`),
  CONSTRAINT `fk_historique_dette` FOREIGN KEY (`id_dette`) REFERENCES `dette_etudiant` (`id_dette`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les évaluations de rachat des dettes
CREATE TABLE IF NOT EXISTS `dette_evaluation` (
  `id_evaluation` int(11) NOT NULL AUTO_INCREMENT,
  `id_dette` int(11) NOT NULL,
  `type_evaluation` enum('CC','EX','TP','TD') NOT NULL,
  `note` decimal(5,2) NOT NULL,
  `date_evaluation` date NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_encodage` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id_evaluation`),
  KEY `fk_evaluation_dette` (`id_dette`),
  KEY `fk_evaluation_session` (`session_idsession`),
  KEY `fk_evaluation_annee` (`annee_acad_idannee_acad`),
  KEY `fk_evaluation_user` (`idUser`),
  CONSTRAINT `fk_evaluation_dette` FOREIGN KEY (`id_dette`) REFERENCES `dette_etudiant` (`id_dette`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les bulletins de dettes générés
CREATE TABLE IF NOT EXISTS `dette_bulletin` (
  `id_bulletin` int(11) NOT NULL AUTO_INCREMENT,
  `matricule` varchar(255) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_generation` datetime DEFAULT current_timestamp(),
  `chemin_fichier` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id_bulletin`),
  KEY `fk_bulletin_promotion` (`promotion_idpromotion`),
  KEY `fk_bulletin_session` (`session_idsession`),
  KEY `fk_bulletin_annee` (`annee_acad_idannee_acad`),
  KEY `fk_bulletin_user` (`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
