-- ========================================
-- TABLES LIÉES AU SECTEUR DE LA RECHERCHE
-- ========================================

-- Table principale des unités de recherche
CREATE TABLE IF NOT EXISTS `unite_recherche` (
  `idunite_recherche` int(11) NOT NULL,
  `designation_UR` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des spécialisations de recherche
CREATE TABLE IF NOT EXISTS `specialisation` (
  `idSpecialisation` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `dateCreation` datetime NOT NULL,
  `idUnite_recherche` int(11) NOT NULL,
  `idorientation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des sujets de recherche (mémoires, thèses)
CREATE TABLE IF NOT EXISTS `sujets` (
  `idsujets` int(11) NOT NULL,
  `intitule` text NOT NULL,
  `etatSujet` varchar(145) DEFAULT 'Encours',
  `idDirecteur` int(11) DEFAULT NULL,
  `idEncadreur` int(11) DEFAULT NULL,
  `etudiant_idetudiant` int(11) DEFAULT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `cycle` enum('Premier','Deuxieme','Troisieme','') NOT NULL,
  `idSpecialisation` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `statut_validation` enum('En attente','Validé','Rejeté','Modifié') NOT NULL DEFAULT 'En attente',
  `commentaire_commission` text DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des travaux scientifiques
CREATE TABLE IF NOT EXISTS `travaux_scientifiques` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `type_document` enum('Mémoire','Thèse','Rapport de stage','Article scientifique','Projet tutoré','Livre','Cours','Mémoire Master Complémentaire') NOT NULL,
  `nom_auteur` varchar(255) DEFAULT NULL,
  `type_auteur` enum('Etudiant','Enseignant','Autre') NOT NULL,
  `orientation_id` int(11) DEFAULT NULL,
  `specialisation_id` int(11) DEFAULT NULL,
  `annee_academique_id` int(11) DEFAULT NULL,
  `directeur_id` int(11) DEFAULT NULL,
  `mots_cles` text DEFAULT NULL,
  `resume` text DEFAULT NULL,
  `fichier_path` varchar(255) DEFAULT NULL,
  `date_depot` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut` enum('En attente','Validé','Rejeté') DEFAULT 'En attente',
  `est_public` tinyint(1) DEFAULT 0,
  `doi` varchar(100) DEFAULT NULL,
  `revue` varchar(255) DEFAULT NULL,
  `volume` varchar(50) DEFAULT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `pages` varchar(50) DEFAULT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `pays` varchar(100) DEFAULT NULL,
  `est_payant` tinyint(1) DEFAULT 0,
  `idfrais` int(11) DEFAULT NULL,
  `anneeThese` varchar(255) DEFAULT NULL,
  `universiteThese` varchar(255) DEFAULT NULL,
  `faculteThese` varchar(255) DEFAULT NULL,
  `specialisationThese` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE LIAISON ET ASSOCIATION
-- ========================================

-- Association unités de recherche - sections
CREATE TABLE IF NOT EXISTS `unite_recherche_section` (
  `idur_section` int(11) NOT NULL,
  `idunite_recherche` int(11) NOT NULL,
  `idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Association unités de recherche - orientations
CREATE TABLE IF NOT EXISTS `unite_recherche_orientation` (
  `idur_orientation` int(11) NOT NULL AUTO_INCREMENT,
  `idunite_recherche` int(11) NOT NULL,
  `idorientation` int(11) NOT NULL,
  PRIMARY KEY (`idur_orientation`),
  KEY `fk_uro_unite` (`idunite_recherche`),
  KEY `fk_uro_orientation` (`idorientation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Spécialisations des enseignants
CREATE TABLE IF NOT EXISTS `enseignant_specialisation` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idSpecialisation` int(11) NOT NULL,
  `dateAffectation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE GESTION DES TÂCHES DE RECHERCHE
-- ========================================

-- Tâches liées aux sujets de recherche
CREATE TABLE IF NOT EXISTS `taches` (
  `idtaches` int(11) NOT NULL,
  `dateTache` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fichierTache` varchar(145) DEFAULT NULL,
  `validation` varchar(145) DEFAULT NULL,
  `pourcentage_avancement` int(11) DEFAULT 0,
  `date_validation` datetime DEFAULT NULL,
  `commentaire_validation` text DEFAULT NULL,
  `sujets_idsujets` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Échanges sur les tâches
CREATE TABLE IF NOT EXISTS `echanges_taches` (
  `idechange` int(11) NOT NULL,
  `dateEchange` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `fichierJoint` varchar(145) DEFAULT NULL,
  `taches_idtaches` int(11) NOT NULL,
  `type_auteur` enum('Directeur','Encadreur','Etudiant') NOT NULL,
  `idAuteur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Conversations liées aux sujets
CREATE TABLE IF NOT EXISTS `conversation` (
  `idconversation` int(11) NOT NULL,
  `sujets_idsujets` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE DÉPÔT ET SOUTENANCE
-- ========================================

-- Dépôts de mémoires
CREATE TABLE IF NOT EXISTS `depot_memoire` (
  `iddepot_memoire` int(11) NOT NULL,
  `dateDepot` date DEFAULT NULL,
  `fichier` varchar(145) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `sujets_idsujets` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dépôts de rapports
CREATE TABLE IF NOT EXISTS `depot_rapport` (
  `iddepot_rapport` int(11) NOT NULL,
  `dateDepot` date DEFAULT NULL,
  `titre` text DEFAULT NULL,
  `lieu_stage` varchar(245) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `encadreur` int(11) NOT NULL,
  `etudiant_idetudiant` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Soutenances
CREATE TABLE IF NOT EXISTS `soutenance` (
  `idsoutenance` int(11) NOT NULL,
  `date_soutenance` datetime NOT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `sujets_idsujets` int(11) NOT NULL,
  `statut` enum('Programmée','Terminée','Reportée','Annulée') NOT NULL DEFAULT 'Programmée',
  `jury_id` int(11) DEFAULT NULL,
  `note_finale` float DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Composition des jurys
CREATE TABLE IF NOT EXISTS `jury_soutenance` (
  `idjury` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `role` enum('Président','Secrétaire','Membre','Lecteur 1','Lecteur 2') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lecteurs pour les soutenances
CREATE TABLE IF NOT EXISTS `lecteurs_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `est_premier_lecteur` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notes des soutenances
CREATE TABLE IF NOT EXISTS `notes_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `type_notation` enum('Lecteur','Directeur') NOT NULL,
  `note_fond` decimal(4,2) DEFAULT NULL,
  `note_forme` decimal(4,2) DEFAULT NULL,
  `note_soutenance` decimal(4,2) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_notation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Validation des notes de soutenance
CREATE TABLE IF NOT EXISTS `validation_notes_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `est_valide` tinyint(1) DEFAULT 0,
  `date_validation` datetime DEFAULT NULL,
  `id_validateur` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `est_visible` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES FINANCIÈRES DE LA RECHERCHE
-- ========================================

-- Frais de soutenance
CREATE TABLE IF NOT EXISTS `frais_soutenance` (
  `idfrais_soutenance` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `devise` varchar(10) DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `estObligatoire` tinyint(1) DEFAULT 1,
  `dateCreation` datetime DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `annee_acad_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Paiements des frais de soutenance
CREATE TABLE IF NOT EXISTS `paiement_soutenance` (
  `idpaiement_soutenance` int(11) NOT NULL,
  `etudiant_id` int(11) DEFAULT NULL,
  `frais_soutenance_id` int(11) DEFAULT NULL,
  `montantPaye` decimal(10,2) NOT NULL,
  `referencePaiement` varchar(100) DEFAULT NULL,
  -- Continuation de la table paiement_soutenance
  `datePaiement` datetime DEFAULT NULL,
  `estComplet` tinyint(1) DEFAULT 0,
  `modePaiement` varchar(50) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `annee_acad_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE SUIVI ET STATISTIQUES
-- ========================================

-- Historique de validation des sujets
CREATE TABLE IF NOT EXISTS `sujet_validation_history` (
  `id` int(11) NOT NULL,
  `idsujets` int(11) NOT NULL,
  `status` enum('En attente','Validé','Rejeté','Modifié') NOT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Statistiques de recherche
CREATE TABLE IF NOT EXISTS `statistique_recherche` (
  `idstat` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `idsection` int(11) DEFAULT NULL,
  `idenseignant` int(11) DEFAULT NULL,
  `nb_travaux_diriges` int(11) DEFAULT 0,
  `nb_travaux_encadres` int(11) DEFAULT 0,
  `nb_travaux_termines` int(11) DEFAULT 0,
  `nb_travaux_en_cours` int(11) DEFAULT 0,
  `date_calcul` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Consultations des travaux scientifiques
CREATE TABLE `consultations` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `travail_id` int(11) DEFAULT NULL,
  `date_consultation` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES D'INFORMATIONS COMPLÉMENTAIRES
-- ========================================

-- Informations de recherche des agents
CREATE TABLE IF NOT EXISTS `research_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `unite_recherche` varchar(255) DEFAULT NULL,
  `projet_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Informations des enseignants
CREATE TABLE IF NOT EXISTS `teacher_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `specialisation` varchar(255) DEFAULT NULL,
  `domaine_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE LABORATOIRES
-- ========================================

-- Laboratoires de recherche
CREATE TABLE IF NOT EXISTS `laboratoire` (
  `idlabo` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `responsable_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `annee_acad_id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `ref_latitude` DECIMAL(10, 8) AFTER `idUser`,
  `ref_longitude` DECIMAL(11, 8) AFTER `ref_latitude`,
  `geo_verification_active` BOOLEAN DEFAULT TRUE AFTER `ref_longitude`,
  PRIMARY KEY (`idlabo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Autorisations d'accès aux laboratoires
CREATE TABLE IF NOT EXISTS `autorisation_labo` (
  `idautorisation` int(11) NOT NULL AUTO_INCREMENT,
  `idlabo` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `niveau_autorisation` enum('Admin','Utilisateur') NOT NULL DEFAULT 'Utilisateur',
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`idautorisation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Séances de laboratoire
CREATE TABLE IF NOT EXISTS `seance_labo` (
  `idseance_labo` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `date_seance` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `description` text DEFAULT NULL,
  `qrcode` varchar(255) DEFAULT NULL,
  `idlabo` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `annee_acad_id` int(11) NOT NULL,
  `ref_latitude` DECIMAL(10, 8),
  `ref_longitude` DECIMAL(11, 8),
  `geo_verification_active` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`idseance_labo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Présences aux séances de laboratoire
CREATE TABLE IF NOT EXISTS `presence_labo` (
  `idpresence_labo` int(11) NOT NULL AUTO_INCREMENT,
  `idseance_labo` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `heure_arrivee` datetime NOT NULL,
  `statut` enum('Présent','Retard','Absent','Excusé') NOT NULL DEFAULT 'Présent',
  `commentaire` text DEFAULT NULL,
  `methode_enregistrement` enum('QR Code','Manuel') NOT NULL DEFAULT 'QR Code',
  `ip_address` VARCHAR(45),
  `latitude` DECIMAL(10, 8),
  `longitude` DECIMAL(11, 8),
  `idUser` int(11) DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idpresence_labo`),
  UNIQUE KEY `idx_unique_presence_labo` (`idseance_labo`, `idetudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE SÉCURITÉ ET FRAUDE
-- ========================================

-- Tentatives de fraude dans les présences
CREATE TABLE IF NOT EXISTS `tentatives_fraude_presence` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `idseance` INT NOT NULL,
    `type_seance` ENUM('cours', 'labo') NOT NULL,
    `matricule_tente` VARCHAR(50),
    `date_tentative` DATETIME NOT NULL,
    `details` TEXT,
    INDEX (`ip_address`),
    INDEX (`idseance`, `type_seance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- VUES ET INDEX RECOMMANDÉS
-- ========================================

-- Index pour optimiser les requêtes de recherche
ALTER TABLE `sujets` ADD INDEX `idx_sujets_specialisation` (`idSpecialisation`);
ALTER TABLE `sujets` ADD INDEX `idx_sujets_directeur` (`idDirecteur`);
ALTER TABLE `sujets` ADD INDEX `idx_sujets_encadreur` (`idEncadreur`);
ALTER TABLE `sujets` ADD INDEX `idx_sujets_etudiant` (`etudiant_idetudiant`);
ALTER TABLE `sujets` ADD INDEX `idx_sujets_statut` (`statut_validation`);
ALTER TABLE `sujets` ADD INDEX `idx_sujets_cycle` (`cycle`);

ALTER TABLE `travaux_scientifiques` ADD INDEX `idx_travaux_type` (`type_document`);
ALTER TABLE `travaux_scientifiques` ADD INDEX `idx_travaux_auteur` (`type_auteur`);
ALTER TABLE `travaux_scientifiques` ADD INDEX `idx_travaux_orientation` (`orientation_id`);
ALTER TABLE `travaux_scientifiques` ADD INDEX `idx_travaux_specialisation` (`specialisation_id`);
ALTER TABLE `travaux_scientifiques` ADD INDEX `idx_travaux_statut` (`statut`);

ALTER TABLE `taches` ADD INDEX `idx_taches_sujet` (`sujets_idsujets`);
ALTER TABLE `taches` ADD INDEX `idx_taches_validation` (`validation`);

ALTER TABLE `soutenance` ADD INDEX `idx_soutenance_sujet` (`sujets_idsujets`);
ALTER TABLE `soutenance` ADD INDEX `idx_soutenance_statut` (`statut`);
ALTER TABLE `soutenance` ADD INDEX `idx_soutenance_date` (`date_soutenance`);

-- ========================================
-- CLÉS PRIMAIRES MANQUANTES
-- ========================================

ALTER TABLE `unite_recherche` ADD PRIMARY KEY (`idunite_recherche`);
ALTER TABLE `specialisation` ADD PRIMARY KEY (`idSpecialisation`);
ALTER TABLE `sujets` ADD PRIMARY KEY (`idsujets`);
ALTER TABLE `travaux_scientifiques` ADD PRIMARY KEY (`id`);
ALTER TABLE `unite_recherche_section` ADD PRIMARY KEY (`idur_section`);
ALTER TABLE `enseignant_specialisation` ADD PRIMARY KEY (`id`);
ALTER TABLE `taches` ADD PRIMARY KEY (`idtaches`);
ALTER TABLE `echanges_taches` ADD PRIMARY KEY (`idechange`);
ALTER TABLE `conversation` ADD PRIMARY KEY (`idconversation`);
ALTER TABLE `depot_memoire` ADD PRIMARY KEY (`iddepot_memoire`);
ALTER TABLE `depot_rapport` ADD PRIMARY KEY (`iddepot_rapport`);
ALTER TABLE `soutenance` ADD PRIMARY KEY (`idsoutenance`);
ALTER TABLE `jury_soutenance` ADD PRIMARY KEY (`idjury`);
ALTER TABLE `lecteurs_soutenance` ADD PRIMARY KEY (`id`);
ALTER TABLE `notes_soutenance` ADD PRIMARY KEY (`id`);
ALTER TABLE `validation_notes_soutenance` ADD PRIMARY KEY (`id`);
ALTER TABLE `frais_soutenance` ADD PRIMARY KEY (`idfrais_soutenance`);
ALTER TABLE `paiement_soutenance` ADD PRIMARY KEY (`idpaiement_soutenance`);
ALTER TABLE `sujet_validation_history` ADD PRIMARY KEY (`id`);
ALTER TABLE `statistique_recherche` ADD PRIMARY KEY (`idstat`);
ALTER TABLE `research_info` ADD PRIMARY KEY (`id`);
ALTER TABLE `teacher_info` ADD PRIMARY KEY (`id`);

-- ========================================
-- CONTRAINTES DE CLÉS ÉTRANGÈRES
-- ========================================

-- Contraintes pour les tables principales
ALTER TABLE `specialisation` ADD CONSTRAINT `fk_specialisation_unite` FOREIGN KEY (`idUnite_recherche`) REFERENCES `unite_recherche` (`idunite_recherche`) ON DELETE CASCADE;
ALTER TABLE `specialisation` ADD CONSTRAINT `fk_specialisation_orientation` FOREIGN KEY (`idorientation`) REFERENCES `orientation` (`idorientation`) ON DELETE CASCADE;

ALTER TABLE `sujets` ADD CONSTRAINT `fk_sujets_specialisation` FOREIGN KEY (`idSpecialisation`) REFERENCES `specialisation` (`idSpecialisation`) ON DELETE CASCADE;
ALTER TABLE `sujets` ADD CONSTRAINT `fk_sujets_directeur` FOREIGN KEY (`idDirecteur`) REFERENCES `agent` (`idAgent`) ON DELETE SET NULL;
ALTER TABLE `sujets` ADD CONSTRAINT `fk_sujets_encadreur` FOREIGN KEY (`idEncadreur`) REFERENCES `agent` (`idAgent`) ON DELETE SET NULL;
ALTER TABLE `sujets` ADD CONSTRAINT `fk_sujets_etudiant` FOREIGN KEY (`etudiant_idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE SET NULL;

-- Contraintes pour les tables de liaison
ALTER TABLE `unite_recherche_section` ADD CONSTRAINT `fk_urs_unite` FOREIGN KEY (`idunite_recherche`) REFERENCES `unite_recherche` (`idunite_recherche`) ON DELETE CASCADE;
ALTER TABLE `unite_recherche_section` ADD CONSTRAINT `fk_urs_section` FOREIGN KEY (`idsection`) REFERENCES `section` (`idsection`) ON DELETE CASCADE;

ALTER TABLE `enseignant_specialisation` ADD CONSTRAINT `fk_ens_spec_agent` FOREIGN KEY (`idAgent`) REFERENCES `agent` (`idAgent`) ON DELETE CASCADE;
ALTER TABLE `enseignant_specialisation` ADD CONSTRAINT `fk_ens_spec_specialisation` FOREIGN KEY (`idSpecialisation`) REFERENCES `specialisation` (`idSpecialisation`) ON DELETE CASCADE;

-- Contraintes pour les tâches et échanges
ALTER TABLE `taches` ADD CONSTRAINT `fk_taches_sujet` FOREIGN KEY (`sujets_idsujets`) REFERENCES `sujets` (`idsujets`) ON DELETE CASCADE;
ALTER TABLE `echanges_taches` ADD CONSTRAINT `fk_echanges_tache` FOREIGN KEY (`taches_idtaches`) REFERENCES `taches` (`idtaches`) ON DELETE CASCADE;

-- Contraintes pour les soutenances
ALTER TABLE `soutenance` ADD CONSTRAINT `fk_soutenance_sujet` FOREIGN KEY (`sujets_idsujets`) REFERENCES `sujets` (`idsujets`) ON DELETE CASCADE;
ALTER TABLE `jury_soutenance` ADD CONSTRAINT `fk_jury_soutenance` FOREIGN KEY (`idsoutenance`) REFERENCES `soutenance` (`idsoutenance`) ON DELETE CASCADE;
ALTER TABLE `jury_soutenance` ADD CONSTRAINT `fk_jury_enseignant` FOREIGN KEY (`idenseignant`) REFERENCES `agent` (`idAgent`) ON DELETE CASCADE;

-- Contraintes pour les laboratoires
ALTER TABLE `autorisation_labo` ADD CONSTRAINT `fk_autorisation_labo` FOREIGN KEY (`idlabo`) REFERENCES `laboratoire` (`idlabo`) ON DELETE CASCADE;
ALTER TABLE `autorisation_labo` ADD CONSTRAINT `fk_autorisation_agent` FOREIGN KEY (`idAgent`) REFERENCES `agent` (`idAgent-- Continuation des contraintes de clés étrangères
`) ON DELETE CASCADE;

ALTER TABLE `seance_labo` ADD CONSTRAINT `fk_seance_labo` FOREIGN KEY (`idlabo`) REFERENCES `laboratoire` (`idlabo`) ON DELETE CASCADE;
ALTER TABLE `presence_labo` ADD CONSTRAINT `fk_presence_seance_labo` FOREIGN KEY (`idseance_labo`) REFERENCES `seance_labo` (`idseance_labo`) ON DELETE CASCADE;
ALTER TABLE `presence_labo` ADD CONSTRAINT `fk_presence_etudiant_labo` FOREIGN KEY (`idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE;

-- Contraintes pour les dépôts
ALTER TABLE `depot_memoire` ADD CONSTRAINT `fk_depot_memoire_sujet` FOREIGN KEY (`sujets_idsujets`) REFERENCES `sujets` (`idsujets`) ON DELETE CASCADE;
ALTER TABLE `depot_rapport` ADD CONSTRAINT `fk_depot_rapport_etudiant` FOREIGN KEY (`etudiant_idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE;
ALTER TABLE `depot_rapport` ADD CONSTRAINT `fk_depot_rapport_encadreur` FOREIGN KEY (`encadreur`) REFERENCES `agent` (`idAgent`) ON DELETE CASCADE;

-- Contraintes pour les notes et validations
ALTER TABLE `notes_soutenance` ADD CONSTRAINT `fk_notes_soutenance` FOREIGN KEY (`idsoutenance`) REFERENCES `soutenance` (`idsoutenance`) ON DELETE CASCADE;
ALTER TABLE `notes_soutenance` ADD CONSTRAINT `fk_notes_enseignant` FOREIGN KEY (`idenseignant`) REFERENCES `agent` (`idAgent`) ON DELETE CASCADE;

ALTER TABLE `validation_notes_soutenance` ADD CONSTRAINT `fk_validation_soutenance` FOREIGN KEY (`idsoutenance`) REFERENCES `soutenance` (`idsoutenance`) ON DELETE CASCADE;
ALTER TABLE `validation_notes_soutenance` ADD CONSTRAINT `fk_validation_validateur` FOREIGN KEY (`id_validateur`) REFERENCES `agent` (`idAgent`) ON DELETE SET NULL;

-- Contraintes pour les frais et paiements
ALTER TABLE `paiement_soutenance` ADD CONSTRAINT `fk_paiement_etudiant` FOREIGN KEY (`etudiant_id`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE;
ALTER TABLE `paiement_soutenance` ADD CONSTRAINT `fk_paiement_frais` FOREIGN KEY (`frais_soutenance_id`) REFERENCES `frais_soutenance` (`idfrais_soutenance`) ON DELETE CASCADE;

-- Contraintes pour l'historique et statistiques
ALTER TABLE `sujet_validation_history` ADD CONSTRAINT `fk_validation_history_sujet` FOREIGN KEY (`idsujets`) REFERENCES `sujets` (`idsujets`) ON DELETE CASCADE;
ALTER TABLE `statistique_recherche` ADD CONSTRAINT `fk_stat_annee` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE;
ALTER TABLE `statistique_recherche` ADD CONSTRAINT `fk_stat_section` FOREIGN KEY (`idsection`) REFERENCES `section` (`idsection`) ON DELETE SET NULL;
ALTER TABLE `statistique_recherche` ADD CONSTRAINT `fk_stat_enseignant` FOREIGN KEY (`idenseignant`) REFERENCES `agent` (`idAgent`) ON DELETE SET NULL;

-- Contraintes pour les informations complémentaires
ALTER TABLE `research_info` ADD CONSTRAINT `fk_research_agent` FOREIGN KEY (`idAgent`) REFERENCES `agent` (`idAgent`) ON DELETE CASCADE;
ALTER TABLE `teacher_info` ADD CONSTRAINT `fk_teacher_agent` FOREIGN KEY (`idAgent`) REFERENCES `agent` (`idAgent`) ON DELETE CASCADE;

-- Contraintes pour les consultations
ALTER TABLE `consultations` ADD CONSTRAINT `fk_consultation_travail` FOREIGN KEY (`travail_id`) REFERENCES `travaux_scientifiques` (`id`) ON DELETE CASCADE;

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
