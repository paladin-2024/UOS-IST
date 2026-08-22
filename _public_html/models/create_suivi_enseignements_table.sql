-- Table pour le suivi des enseignements
CREATE TABLE IF NOT EXISTS `suivi_enseignements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idECUE` int(11) NOT NULL,
  `enseignant_id` int(11) DEFAULT NULL,
  `date_cours` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `type_cours` enum('CM','TD','TP','Evaluation') NOT NULL DEFAULT 'CM',
  `salle` varchar(100) DEFAULT NULL,
  `commentaire` text DEFAULT NULL COMMENT 'Matières enseignées durant la séance',
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur qui a enregistré',
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ecue` (`idECUE`),
  KEY `idx_enseignant` (`enseignant_id`),
  KEY `idx_date_cours` (`date_cours`),
  KEY `idx_annee_acad` (`annee_acad_idannee_acad`),
  KEY `idx_user` (`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ajouter les contraintes de clés étrangères si les tables existent
ALTER TABLE `suivi_enseignements`
  ADD CONSTRAINT `fk_suivi_ecue` FOREIGN KEY (`idECUE`) REFERENCES `ecue` (`idECUE`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_suivi_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `agent` (`idAgent`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_suivi_annee` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_suivi_user` FOREIGN KEY (`idUser`) REFERENCES `t_users` (`idUser`) ON DELETE RESTRICT ON UPDATE CASCADE;