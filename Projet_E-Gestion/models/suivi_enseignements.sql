-- Table pour le suivi des enseignements par les chefs de promotion
CREATE TABLE IF NOT EXISTS `suivi_enseignements` (
  `id_suivi` int(11) NOT NULL AUTO_INCREMENT,
  `chef_promotion_id` int(11) NOT NULL COMMENT 'ID de l\'étudiant chef de promotion',
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
  KEY `fk_suivi_chef_promotion` (`chef_promotion_id`),
  KEY `fk_suivi_ecue` (`idECUE`),
  KEY `fk_suivi_enseignant` (`enseignant_id`),
  KEY `fk_suivi_annee_acad` (`annee_acad_idannee_acad`),
  KEY `idx_date_cours` (`date_cours`),
  KEY `idx_chef_date` (`chef_promotion_id`, `date_cours`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Suivi des enseignements par les chefs de promotion';

-- Contraintes de clés étrangères
ALTER TABLE `suivi_enseignements`
  ADD CONSTRAINT `fk_suivi_chef_promotion` FOREIGN KEY (`chef_promotion_id`) REFERENCES `chef_promotion` (`id_chef`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_suivi_ecue` FOREIGN KEY (`idECUE`) REFERENCES `ecue` (`idECUE`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_suivi_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `agent` (`idAgent`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_suivi_annee_acad` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE;