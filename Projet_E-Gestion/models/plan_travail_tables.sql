-- ========================================
-- NOUVELLES TABLES POUR LA GESTION DES PLANS DE TRAVAIL
-- ========================================

-- Table principale des plans de travail soumis par les étudiants
CREATE TABLE IF NOT EXISTS `plan_travail` (
  `idplan_travail` int(11) NOT NULL AUTO_INCREMENT,
  `idsujets` int(11) NOT NULL,
  `titre_plan` varchar(500) NOT NULL,
  `introduction` text DEFAULT NULL,
  `problematique` text DEFAULT NULL,
  `objectifs` text DEFAULT NULL,
  `methodologie` text DEFAULT NULL,
  `statut_validation` enum('En attente','Validé','Rejeté','Modifié') NOT NULL DEFAULT 'En attente',
  `commentaire_directeur` text DEFAULT NULL,
  `date_soumission` datetime NOT NULL DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL COMMENT 'ID du directeur qui valide',
  `version` int(11) NOT NULL DEFAULT 1,
  `idUser` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a créé/modifié',
  PRIMARY KEY (`idplan_travail`),
  KEY `idx_plan_sujet` (`idsujets`),
  KEY `idx_plan_statut` (`statut_validation`),
  KEY `idx_plan_validateur` (`idValidateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des chapitres du plan de travail
CREATE TABLE IF NOT EXISTS `chapitre_plan` (
  `idchapitre_plan` int(11) NOT NULL AUTO_INCREMENT,
  `idplan_travail` int(11) NOT NULL,
  `numero_chapitre` int(11) NOT NULL,
  `titre_chapitre` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `objectifs_chapitre` text DEFAULT NULL,
  `ordre_affichage` int(11) NOT NULL DEFAULT 1,
  `statut` enum('En attente','En cours','Terminé','En révision') NOT NULL DEFAULT 'En attente',
  `deadline` date DEFAULT NULL,
  `date_attribution_deadline` datetime DEFAULT NULL,
  `commentaire_deadline` text DEFAULT NULL,
  `pourcentage_avancement` int(11) DEFAULT 0,
  `date_soumission` datetime DEFAULT NULL,
  `fichier_chapitre` varchar(255) DEFAULT NULL,
  `commentaire_directeur` text DEFAULT NULL,
  `note_chapitre` decimal(4,2) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idchapitre_plan`),
  KEY `idx_chapitre_plan` (`idplan_travail`),
  KEY `idx_chapitre_numero` (`numero_chapitre`),
  KEY `idx_chapitre_deadline` (`deadline`),
  KEY `idx_chapitre_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des sous-sections d'un chapitre
CREATE TABLE IF NOT EXISTS `section_chapitre` (
  `idsection_chapitre` int(11) NOT NULL AUTO_INCREMENT,
  `idchapitre_plan` int(11) NOT NULL,
  `numero_section` varchar(20) NOT NULL COMMENT 'Ex: 1.1, 1.2, 2.1, etc.',
  `titre_section` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `ordre_affichage` int(11) NOT NULL DEFAULT 1,
  `deadline` date DEFAULT NULL,
  `statut` enum('En attente','En cours','Terminé','En révision') NOT NULL DEFAULT 'En attente',
  `pourcentage_avancement` int(11) DEFAULT 0,
  `fichier_section` varchar(255) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idsection_chapitre`),
  KEY `idx_section_chapitre` (`idchapitre_plan`),
  KEY `idx_section_numero` (`numero_section`),
  KEY `idx_section_deadline` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour l'historique des validations de plans
CREATE TABLE IF NOT EXISTS `plan_validation_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idplan_travail` int(11) NOT NULL,
  `statut` enum('En attente','Validé','Rejeté','Modifié') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL COMMENT 'Directeur qui effectue l\'action',
  `version_plan` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_history_plan` (`idplan_travail`),
  KEY `idx_history_date` (`date_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les deadlines assignées par le directeur
CREATE TABLE IF NOT EXISTS `deadline_assignment` (
  `iddeadline` int(11) NOT NULL AUTO_INCREMENT,
  `idchapitre_plan` int(11) DEFAULT NULL,
  `idsection_chapitre` int(11) DEFAULT NULL,
  `type_element` enum('chapitre','section','plan_global') NOT NULL,
  `deadline` date NOT NULL,
  `description_deadline` text DEFAULT NULL,
  `priorite` enum('Faible','Moyenne','Haute','Critique') NOT NULL DEFAULT 'Moyenne',
  `statut_deadline` enum('Active','Reportée','Terminée','Annulée') NOT NULL DEFAULT 'Active',
  `date_attribution` datetime NOT NULL DEFAULT current_timestamp(),
  `idDirecteur` int(11) NOT NULL,
  `notification_etudiant` tinyint(1) DEFAULT 0,
  `date_notification` datetime DEFAULT NULL,
  `rappel_active` tinyint(1) DEFAULT 1,
  `jours_rappel` int(11) DEFAULT 7 COMMENT 'Rappel X jours avant',
  PRIMARY KEY (`iddeadline`),
  KEY `idx_deadline_chapitre` (`idchapitre_plan`),
  KEY `idx_deadline_section` (`idsection_chapitre`),
  KEY `idx_deadline_date` (`deadline`),
  KEY `idx_deadline_directeur` (`idDirecteur`),
  KEY `idx_deadline_statut` (`statut_deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les échanges et commentaires sur les chapitres
CREATE TABLE IF NOT EXISTS `echange_chapitre` (
  `idechange_chapitre` int(11) NOT NULL AUTO_INCREMENT,
  `idchapitre_plan` int(11) NOT NULL,
  `type_auteur` enum('Directeur','Encadreur','Etudiant') NOT NULL,
  `idAuteur` int(11) NOT NULL,
  `commentaire` text NOT NULL,
  `fichier_joint` varchar(255) DEFAULT NULL,
  `type_fichier` varchar(50) DEFAULT NULL,
  `statut_lecture` enum('Non lu','Lu','Traité') NOT NULL DEFAULT 'Non lu',
  `date_echange` datetime NOT NULL DEFAULT current_timestamp(),
  `reponse_a` int(11) DEFAULT NULL COMMENT 'ID de l\'échange parent si c\'est une réponse',
  PRIMARY KEY (`idechange_chapitre`),
  KEY `idx_echange_chapitre` (`idchapitre_plan`),
  KEY `idx_echange_auteur` (`idAuteur`, `type_auteur`),
  KEY `idx_echange_date` (`date_echange`),
  KEY `idx_echange_reponse` (`reponse_a`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour le suivi des notifications
CREATE TABLE IF NOT EXISTS `notification_plan` (
  `idnotification` int(11) NOT NULL AUTO_INCREMENT,
  `destinataire_id` int(11) NOT NULL,
  `type_destinataire` enum('Etudiant','Directeur','Encadreur') NOT NULL,
  `type_notification` enum('Nouveau plan','Plan validé','Plan rejeté','Deadline assignée','Deadline proche','Chapitre soumis','Commentaire ajouté') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `idplan_travail` int(11) DEFAULT NULL,
  `idchapitre_plan` int(11) DEFAULT NULL,
  `iddeadline` int(11) DEFAULT NULL,
  `statut_lecture` tinyint(1) DEFAULT 0,
  `date_notification` datetime NOT NULL DEFAULT current_timestamp(),
  `date_lecture` datetime DEFAULT NULL,
  PRIMARY KEY (`idnotification`),
  KEY `idx_notif_destinataire` (`destinataire_id`, `type_destinataire`),
  KEY `idx_notif_type` (`type_notification`),
  KEY `idx_notif_plan` (`idplan_travail`),
  KEY `idx_notif_chapitre` (`idchapitre_plan`),
  KEY `idx_notif_statut` (`statut_lecture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- CONTRAINTES DE CLÉS ÉTRANGÈRES
-- ========================================

-- Plan de travail lié au sujet validé
ALTER TABLE `plan_travail` 
ADD CONSTRAINT `fk_plan_sujet` FOREIGN KEY (`idsujets`) REFERENCES `sujets` (`idsujets`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_plan_validateur` FOREIGN KEY (`idValidateur`) REFERENCES `agent` (`idAgent`) ON DELETE SET NULL;

-- Chapitres liés au plan
ALTER TABLE `chapitre_plan` 
ADD CONSTRAINT `fk_chapitre_plan` FOREIGN KEY (`idplan_travail`) REFERENCES `plan_travail` (`idplan_travail`) ON DELETE CASCADE;

-- Sections liées aux chapitres
ALTER TABLE `section_chapitre` 
ADD CONSTRAINT `fk_section_chapitre` FOREIGN KEY (`idchapitre_plan`) REFERENCES `chapitre_plan` (`idchapitre_plan`) ON DELETE CASCADE;

-- Historique de validation
ALTER TABLE `plan_validation_history` 
ADD CONSTRAINT `fk_history_plan` FOREIGN KEY (`idplan_travail`) REFERENCES `plan_travail` (`idplan_travail`) ON DELETE CASCADE;

-- Deadlines assignées
ALTER TABLE `deadline_assignment` 
ADD CONSTRAINT `fk_deadline_chapitre` FOREIGN KEY (`idchapitre_plan`) REFERENCES `chapitre_plan` (`idchapitre_plan`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_deadline_section` FOREIGN KEY (`idsection_chapitre`) REFERENCES `section_chapitre` (`idsection_chapitre`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_deadline_directeur` FOREIGN KEY (`idDirecteur`) REFERENCES `agent` (`idAgent`) ON DELETE CASCADE;

-- Échanges sur les chapitres
ALTER TABLE `echange_chapitre` 
ADD CONSTRAINT `fk_echange_chapitre` FOREIGN KEY (`idchapitre_plan`) REFERENCES `chapitre_plan` (`idchapitre_plan`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_echange_reponse` FOREIGN KEY (`reponse_a`) REFERENCES `echange_chapitre` (`idechange_chapitre`) ON DELETE SET NULL;

-- Notifications
ALTER TABLE `notification_plan` 
ADD CONSTRAINT `fk_notif_plan` FOREIGN KEY (`idplan_travail`) REFERENCES `plan_travail` (`idplan_travail`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_notif_chapitre` FOREIGN KEY (`idchapitre_plan`) REFERENCES `chapitre_plan` (`idchapitre_plan`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_notif_deadline` FOREIGN KEY (`iddeadline`) REFERENCES `deadline_assignment` (`iddeadline`) ON DELETE CASCADE;

-- ========================================
-- INDEX POUR OPTIMISATION
-- ========================================

-- Index composites pour les requêtes fréquentes
ALTER TABLE `plan_travail` ADD INDEX `idx_plan_sujet_statut` (`idsujets`, `statut_validation`);
ALTER TABLE `chapitre_plan` ADD INDEX `idx_chapitre_plan_ordre` (`idplan_travail`, `ordre_affichage`);
ALTER TABLE `chapitre_plan` ADD INDEX `idx_chapitre_deadline_statut` (`deadline`, `statut`);
ALTER TABLE `section_chapitre` ADD INDEX `idx_section_chapitre_ordre` (`idchapitre_plan`, `ordre_affichage`);
ALTER TABLE `deadline_assignment` ADD INDEX `idx_deadline_active` (`deadline`, `statut_deadline`);
ALTER TABLE `echange_chapitre` ADD INDEX `idx_echange_chapitre_date` (`idchapitre_plan`, `date_echange`);
ALTER TABLE `notification_plan` ADD INDEX `idx_notif_destinataire_statut` (`destinataire_id`, `type_destinataire`, `statut_lecture`);

-- ========================================
-- VUES UTILES
-- ========================================

-- Vue pour avoir un résumé des plans de travail avec infos du sujet
CREATE OR REPLACE VIEW `v_plan_travail_resume` AS
SELECT 
    pt.idplan_travail,
    pt.titre_plan,
    pt.statut_validation as statut_plan,
    pt.date_soumission,
    pt.date_validation,
    pt.version,
    s.idsujets,
    s.intitule as sujet_intitule,
    s.idDirecteur,
    s.idEncadreur,
    s.etudiant_idetudiant,
    CONCAT(e.noms, ' ', e.postnom, ' ', e.prenom) as nom_etudiant,
    e.matricule,
    CONCAT(a_dir.noms, ' ', a_dir.postnom, ' ', a_dir.prenom) as nom_directeur,
    CONCAT(a_enc.noms, ' ', a_enc.postnom, ' ', a_enc.prenom) as nom_encadreur,
    sp.designation as specialisation,
    COUNT(cp.idchapitre_plan) as nb_chapitres,
    COUNT(CASE WHEN cp.statut = 'Terminé' THEN 1 END) as nb_chapitres_termines,
    AVG(cp.pourcentage_avancement) as avancement_moyen
FROM plan_travail pt
JOIN sujets s ON pt.idsujets = s.idsujets
JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
LEFT JOIN agent a_dir ON s.idDirecteur = a_dir.idAgent
LEFT JOIN agent a_enc ON s.idEncadreur = a_enc.idAgent
LEFT JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
LEFT JOIN chapitre_plan cp ON pt.idplan_travail = cp.idplan_travail
GROUP BY pt.idplan_travail;

-- Vue pour les deadlines à venir
CREATE OR REPLACE VIEW `v_deadlines_prochaines` AS
SELECT 
    da.iddeadline,
    da.deadline,
    da.description_deadline,
    da.priorite,
    da.type_element,
    cp.titre_chapitre,
    cp.numero_chapitre,
    pt.titre_plan,
    s.intitule as sujet_intitule,
    CONCAT(e.noms, ' ', e.postnom, ' ', e.prenom) as nom_etudiant,
    e.matricule,
    CONCAT(a.noms, ' ', a.postnom, ' ', a.prenom) as nom_directeur,
    DATEDIFF(da.deadline, CURDATE()) as jours_restants
FROM deadline_assignment da
LEFT JOIN chapitre_plan cp ON da.idchapitre_plan = cp.idchapitre_plan
LEFT JOIN plan_travail pt ON cp.idplan_travail = pt.idplan_travail
LEFT JOIN sujets s ON pt.idsujets = s.idsujets
LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
LEFT JOIN agent a ON s.idDirecteur = a.idAgent
WHERE da.statut_deadline = 'Active'
AND da.deadline >= CURDATE()
ORDER BY da.deadline ASC;
