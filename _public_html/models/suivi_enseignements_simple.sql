-- Table pour le suivi des enseignements par les chefs de promotion (version simplifiée)
CREATE TABLE IF NOT EXISTS `suivi_enseignements` (
  `id_suivi` int(11) NOT NULL AUTO_INCREMENT,
  `idECUE` int(11) NOT NULL COMMENT 'ID de la matière/ECUE',
  `date_cours` date NOT NULL COMMENT 'Date de la séance de cours',
  `heure_debut` time NOT NULL COMMENT 'Heure de début du cours',
  `heure_fin` time NOT NULL COMMENT 'Heure de fin du cours',
  `type_cours` enum('CM','TD','TP','Evaluation') NOT NULL DEFAULT 'CM' COMMENT 'Type de cours',
  `enseignant_id` int(11) DEFAULT NULL COMMENT 'ID de l\'enseignant (optionnel)',
  `salle` varchar(100) DEFAULT NULL COMMENT 'Salle de cours',
  `commentaire` text DEFAULT NULL COMMENT 'Commentaires ou observations',
  `annee_acad_idannee_acad` int(11) NOT NULL COMMENT 'Année académique',
  `date_encodage` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur ayant créé l\'enregistrement',
  PRIMARY KEY (`id_suivi`),
  KEY `idx_ecue` (`idECUE`),
  KEY `idx_enseignant` (`enseignant_id`),
  KEY `idx_annee_acad` (`annee_acad_idannee_acad`),
  KEY `idx_date_cours` (`date_cours`),
  KEY `idx_user` (`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Suivi des enseignements';