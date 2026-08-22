CREATE TABLE IF NOT EXISTS `agent` (
  `idAgent` int(11) NOT NULL,
  `noms` varchar(245) DEFAULT NULL,
  `lieuNaissance` varchar(45) DEFAULT NULL,
  `dateNaissance` date DEFAULT NULL,
  `sexe` varchar(45) DEFAULT NULL,
  `etatCivil` varchar(45) DEFAULT NULL,
  `niveauEtude` varchar(45) DEFAULT NULL,
  `telephone` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `photo` varchar(200) DEFAULT NULL,
  `adresse_avenue` varchar(255) DEFAULT NULL,
  `adresse_quartier` varchar(255) DEFAULT NULL,
  `adresse_commune` varchar(255) DEFAULT NULL,
  `conjoint` varchar(255) DEFAULT NULL,
  `contact_urgence` varchar(255) DEFAULT NULL,
  `degre_parente_urgence` varchar(100) DEFAULT NULL,
  `telephone_urgence` varchar(50) DEFAULT NULL,
  `etablissement_formation` varchar(255) DEFAULT NULL,
  `filiere_formation` varchar(255) DEFAULT NULL,
  `annee_obtention_diplome` int(11) DEFAULT NULL,
  `annee_engagement` int(11) DEFAULT NULL,
  `reference_acte_engagement` varchar(255) DEFAULT NULL,
  `prime_locale` tinyint(1) DEFAULT 0,
  `salaire_etat` tinyint(1) DEFAULT 0,
  `prime_institutionnelle` tinyint(1) DEFAULT 0,
  `codeAgent` varchar(200) DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `type_agent` enum('Enseignant','Administratif','Recherche') NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT current_timestamp(),
  `idStructure` int(11) NOT NULL,
  `idService` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
CREATE TABLE IF NOT EXISTS `bureau_jury_deliberation` (
  `idbureau` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `numero_decision` varchar(100) NOT NULL,
  `date_creation` date NOT NULL,
  `date_decision` date NOT NULL,
  `president_id` int(11) NOT NULL,
  `secretaire_id` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `commentaire` text DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `configuration_deliberation` (
  `idconfig` int(11) NOT NULL,
  `idbureau` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `compensation_intra_ue` tinyint(1) DEFAULT 0 COMMENT 'Autoriser la compensation entre ECUE d''une même UE',
  `seuil_compensation_intra_ue` decimal(4,2) DEFAULT 8.00 COMMENT 'Note minimale pour la compensation intra-UE',
  `compensation_inter_ue` tinyint(1) DEFAULT 0 COMMENT 'Autoriser la compensation entre UE d''un même semestre',
  `seuil_compensation_inter_ue` decimal(4,2) DEFAULT 8.00 COMMENT 'Note minimale pour la compensation inter-UE',
  `exiger_meme_credit_ue` tinyint(1) DEFAULT 1 COMMENT 'Exiger que les UE aient le même crédit pour compensation',
  `compensation_inter_semestre` tinyint(1) DEFAULT 0 COMMENT 'Autoriser la compensation entre UE de semestres différents',
  `seuil_compensation_inter_semestre` decimal(4,2) DEFAULT 8.00 COMMENT 'Note minimale pour la compensation inter-semestre',
  `limiter_compensation_annee` tinyint(1) DEFAULT 1 COMMENT 'Limiter la compensation aux semestres de la même année',
  `note_passage` decimal(4,2) DEFAULT 10.00 COMMENT 'Note minimale pour valider un ECUE/UE',
  `pourcentage_passage_semestre` decimal(5,2) DEFAULT 50.00 COMMENT 'Pourcentage minimum de réussite pour valider un semestre',
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  `calculer_moyenne_avec_notes_vides` tinyint(1) DEFAULT 0 COMMENT 'Calculer la moyenne même si certaines notes sont vides'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `configuration_deliberation` 
ADD COLUMN `heures_par_credit` int(11) DEFAULT 25 
COMMENT 'Nombre d''heures équivalent à 1 crédit';


-- --------------------------------------------------------

--
-- Structure de la table `configuration_moyenne`
--

CREATE TABLE IF NOT EXISTS `configuration_moyenne` (
  `id` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `formule_cc` text NOT NULL,
  `formule_ex` text NOT NULL,
  `ponderation_cc` decimal(5,2) NOT NULL DEFAULT 0.40,
  `ponderation_ex` decimal(5,2) NOT NULL DEFAULT 0.60,
  `idUser` int(11) NOT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `configuration_universite`
--

CREATE TABLE IF NOT EXISTS `configuration_universite` (
  `id` int(11) NOT NULL,
  `type_etablissement` enum('Institut Supérieur','Université') NOT NULL,
  `nom` varchar(255) NOT NULL,
  `sigle` varchar(50) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `pays` varchar(100) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `site_web` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `ministere_tutelle` varchar(255) DEFAULT NULL,
  `nom_responsable` varchar(255) DEFAULT NULL,
  `titre_responsable` varchar(100) DEFAULT NULL,
  `signature_responsable` varchar(255) DEFAULT NULL,
  `cachet` varchar(255) DEFAULT NULL,
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cotes_grille` (
  `idpoints` int(11) NOT NULL,
  `CC` decimal(10,2) DEFAULT NULL,
  `EX` decimal(10,2) DEFAULT NULL,
  `MF` decimal(10,2) DEFAULT NULL COMMENT 'Moyenne Finale calculée',
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `date_compilation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ecue` (
  `idECUE` int(11) NOT NULL,
  `designationECUE` varchar(245) DEFAULT NULL,
  `CMI` float DEFAULT NULL,
  `TD` float DEFAULT NULL,
  `TP` float DEFAULT NULL,
  `UE_idUE` int(11) NOT NULL,
  `idCreateur` int(11) DEFAULT NULL,
  `estVisible` tinyint(1) DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecue_notes_verrouillage`
--

CREATE TABLE IF NOT EXISTS `ecue_notes_verrouillage` (
  `id` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `idsession` int(11) NOT NULL,
  `idannee_acad` int(11) NOT NULL,
  `date_verrouillage` datetime NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `enseignant_ecue` (
  `idenseignant_ecue` int(11) NOT NULL,
  `poste` varchar(145) DEFAULT NULL,
  `idAgent` int(11) DEFAULT NULL,
  `idECUE` int(11) DEFAULT NULL,
  `anneeAcad` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `etudiant` (
  `idetudiant` int(11) NOT NULL,
  `matricule` varchar(245) DEFAULT NULL,
  `noms` varchar(245) NOT NULL,
  `lieuNaissance` varchar(145) DEFAULT NULL,
  `dateNaissance` date DEFAULT NULL,
  `adressemail` varchar(45) DEFAULT NULL,
  `telephone` varchar(45) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `personne_contact` varchar(255) DEFAULT NULL,
  `telephone_contact` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `pwd` varchar(250) DEFAULT NULL,
  `sexe` varchar(100) DEFAULT NULL,
  `nationalite` varchar(100) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `etudiant` ADD `est_actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indique si l\'inscription est active pour l\'année en cours';


CREATE TABLE IF NOT EXISTS `evaluations` (
  `idevaluation` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_evaluation` date NOT NULL,
  `idECUE` int(11) NOT NULL,
  `idType` int(11) NOT NULL,
  `ponderation` decimal(5,2) NOT NULL DEFAULT 1.00,
  `session_idsession` int(11) NOT NULL,
  `est_visible` tinyint(1) DEFAULT 0,
  `annee_acad_id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `evaluations` ADD `note_max` decimal(5,2) NOT NULL DEFAULT 20.00 AFTER `ponderation`;


CREATE TABLE IF NOT EXISTS `historique_cotes` (
  `idhistorique` int(11) NOT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `cc_avant` decimal(10,2) DEFAULT NULL,
  `ex_avant` decimal(10,2) DEFAULT NULL,
  `mf_avant` decimal(10,2) DEFAULT NULL,
  `cc_apres` decimal(10,2) DEFAULT NULL,
  `ex_apres` decimal(10,2) DEFAULT NULL,
  `mf_apres` decimal(10,2) DEFAULT NULL,
  `motif` text DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `historique_notes` (
  `idhistorique` int(11) NOT NULL,
  `iddeliberation` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `UE_idUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `note_avant` decimal(5,2) DEFAULT NULL COMMENT 'Note avant délibération',
  `note_apres` decimal(5,2) DEFAULT NULL COMMENT 'Note après délibération',
  `type_modification` enum('Compensation intra-UE','Compensation inter-UE','Compensation inter-semestre','Décision jury','Correction') NOT NULL,
  `justification` text DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `journal_activites` (
  `id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL COMMENT 'Type d''utilisateur: etudiant, enseignant, admin',
  `user_id` int(11) NOT NULL COMMENT 'ID de l''utilisateur',
  `type_activite` varchar(50) NOT NULL COMMENT 'Type d''activité: devoir, cours, etc.',
  `id_element` int(11) NOT NULL COMMENT 'ID de l''élément concerné',
  `description` text DEFAULT NULL COMMENT 'Description de l''activité',
  `date_activite` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `points` (
  `idpoints` int(11) NOT NULL,
  `coteObtenu` decimal(10,2) DEFAULT NULL,
  `typeEvaluation` int(11) DEFAULT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `annee_acad_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `promotion` (
  `idpromotion` int(11) NOT NULL,
  `designationPromotion` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `cycle` enum('Premier','Deuxieme','Troisieme','') NOT NULL,
  `orientation_idorientation` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `est_terminale` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `recours` (
  `id_recours` int(11) NOT NULL,
  `matricule` varchar(20) NOT NULL,
  `id_ecue` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `id_annee_acad` int(11) NOT NULL,
  `motif` enum('Omission de cote','Calcul inexact','Autre') NOT NULL,
  `description` text DEFAULT NULL,
  `preuve` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `statut` enum('En attente','En traitement','Approuvé','Rejeté') DEFAULT 'En attente',
  `id_createur` int(11) NOT NULL,
  `est_paye` tinyint(4) NOT NULL DEFAULT 0,
  `reference_paiement` varchar(100) DEFAULT NULL COMMENT 'Référence du paiement',
  `date_paiement` date DEFAULT NULL COMMENT 'Date à laquelle le paiement a été effectué',
  `date_modification` datetime DEFAULT NULL COMMENT 'Date de dernière modification',
  `id_modificateur` int(11) DEFAULT NULL COMMENT 'ID de l''utilisateur ayant effectué la dernière modification'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recours_historique`
--

CREATE TABLE IF NOT EXISTS `recours_historique` (
  `id_historique` int(11) NOT NULL,
  `id_recours` int(11) NOT NULL COMMENT 'ID du recours concerné',
  `action` varchar(50) NOT NULL COMMENT 'Type d''action effectuée',
  `details` text DEFAULT NULL COMMENT 'Détails supplémentaires sur l''action',
  `date_action` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date et heure de l''action',
  `id_utilisateur` int(11) NOT NULL COMMENT 'ID de l''utilisateur ayant effectué l''action'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historique des actions effectuées sur les recours';

CREATE TABLE IF NOT EXISTS `recours_reponse` (
  `id_reponse` int(11) NOT NULL,
  `id_recours` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_reponse` datetime DEFAULT current_timestamp(),
  `id_enseignant` int(11) DEFAULT NULL,
  `valide_jury` tinyint(1) DEFAULT 0,
  `id_validateur` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `nouvelle_note_cc` decimal(5,2) DEFAULT NULL,
  `nouvelle_note_ex` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `section` (
  `idsection` int(11) NOT NULL,
  `designationSection` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `idAnnee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `semestre`
--

CREATE TABLE IF NOT EXISTS `semestre` (
  `idsemestre` int(11) NOT NULL,
  `numeroSemestre` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `promotion_idpromotion` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `session` (
  `idsession` int(11) NOT NULL,
  `designSession` varchar(45) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 

CREATE TABLE IF NOT EXISTS `typeevaluation` (
  `idType` int(11) NOT NULL,
  `designationT` varchar(155) NOT NULL,
  `categorie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `t_users` (
  `idUser` int(255) NOT NULL,
  `idRole` int(255) NOT NULL,
  `nomUser` varchar(255) NOT NULL,
  `loginUser` varchar(255) NOT NULL,
  `pw` varchar(255) NOT NULL,
  `imageUser` varchar(255) NOT NULL,
  `etatUser` int(255) NOT NULL,
  `dernier_connexion` date DEFAULT NULL,
  `idAgent` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ue` (
  `idUE` int(11) NOT NULL,
  `codeUE` varchar(45) DEFAULT NULL,
  `designationUE` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `semestre_idsemestre` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `moyenne_ue` (
  `idmoyenne_ue` int(11) NOT NULL,
  `idUE` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `moyenne_brute` decimal(5,2) DEFAULT NULL COMMENT 'Moyenne calculée avant délibération',
  `moyenne_deliberee` decimal(5,2) DEFAULT NULL COMMENT 'Moyenne après délibération',
  `est_validee` tinyint(1) DEFAULT 0 COMMENT 'Indique si l''UE est validée',
  `credits_obtenus` int(11) DEFAULT 0,
  `type_validation` enum('Normale','Compensation intra-semestre','Compensation inter-semestre','Décision jury') DEFAULT 'Normale',
  `date_calcul` datetime DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `moyenne_semestre` (
  `idmoyenne_semestre` int(11) NOT NULL,
  `idsemestre` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `moyenne_brute` decimal(5,2) DEFAULT NULL,
  `moyenne_deliberee` decimal(5,2) DEFAULT NULL,
  `est_valide` tinyint(1) DEFAULT 0,
  `credits_obtenus` int(11) DEFAULT 0,
  `credits_total` int(11) DEFAULT 0,
  `date_calcul` datetime DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `moyenne_annuelle` (
  `idmoyenne_annuelle` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `moyenne_brute` decimal(5,2) DEFAULT NULL,
  `moyenne_deliberee` decimal(5,2) DEFAULT NULL,
  `est_admis` tinyint(1) DEFAULT 0,
  `credits_obtenus` int(11) DEFAULT 0,
  `credits_total` int(11) DEFAULT 0,
  `mention` enum('Passable','Assez Bien','Bien','Très Bien','Excellent') DEFAULT NULL,
  `date_calcul` datetime DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `etudiant_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idetudiant` int(11) NOT NULL,
  `type_document` varchar(50) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `date_ajout` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_etudiant_documents_etudiant` (`idetudiant`),
  CONSTRAINT `fk_etudiant_documents_etudiant` FOREIGN KEY (`idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `jury_membre_autorisations` (
  `id_autorisation` int(11) NOT NULL AUTO_INCREMENT,
  `idbureau` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_autorisation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id_autorisation`),
  UNIQUE KEY `unique_autorisation` (`idbureau`, `idAgent`, `idECUE`, `session_idsession`, `annee_acad_idannee_acad`),
  KEY `fk_autorisations_bureau` (`idbureau`),
  KEY `fk_autorisations_agent` (`idAgent`),
  KEY `fk_autorisations_ecue` (`idECUE`),
  KEY `fk_autorisations_session` (`session_idsession`),
  KEY `fk_autorisations_annee` (`annee_acad_idannee_acad`),
  KEY `fk_autorisations_user` (`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Table pour stocker les palmarès archivés
CREATE TABLE IF NOT EXISTS `palmares_archive` (
  `id_palmares` int(11) NOT NULL AUTO_INCREMENT,
  `designation` varchar(255) NOT NULL COMMENT 'Désignation/titre du palmarès',
  `description` text DEFAULT NULL,
  `annee_academique` varchar(100) NOT NULL COMMENT 'Année académique (peut ne pas exister dans le système)',
  `promotion` varchar(255) NOT NULL COMMENT 'Promotion concernée',
  `session` varchar(100) NOT NULL COMMENT 'Session concernée',
  `fichier_scanne` varchar(255) DEFAULT NULL COMMENT 'Chemin vers le fichier scanné',
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur qui a créé/modifié',
  `annee_acad_idannee_acad` int(11) DEFAULT NULL COMMENT 'Référence optionnelle à une année existante',
  `promotion_idpromotion` int(11) DEFAULT NULL COMMENT 'Référence optionnelle à une promotion existante',
  `session_idsession` int(11) DEFAULT NULL COMMENT 'Référence optionnelle à une session existante',
  PRIMARY KEY (`id_palmares`),
  KEY `fk_palmares_user` (`idUser`),
  KEY `fk_palmares_annee` (`annee_acad_idannee_acad`),
  KEY `fk_palmares_promotion` (`promotion_idpromotion`),
  KEY `fk_palmares_session` (`session_idsession`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour stocker les résultats des étudiants dans les palmarès archivés
CREATE TABLE IF NOT EXISTS `palmares_etudiant` (
  `id_palmares_etudiant` int(11) NOT NULL AUTO_INCREMENT,
  `id_palmares` int(11) NOT NULL,
  `nom_complet` varchar(255) NOT NULL COMMENT 'Nom complet de l étudiant',
  `pourcentage` decimal(5,2) NOT NULL COMMENT 'Pourcentage obtenu',
  `mention` enum('Passable','Assez Bien','Bien','Très Bien','Excellent','Distinction','Grande Distinction','La Plus Grande Distinction') DEFAULT NULL,
  `rang` int(11) DEFAULT NULL COMMENT 'Position dans le classement',
  `matricule` varchar(100) DEFAULT NULL COMMENT 'Matricule si disponible',
  `idetudiant` int(11) DEFAULT NULL COMMENT 'Référence optionnelle à un étudiant existant',
  `commentaire` text DEFAULT NULL,
  `credit_obtenu` int(11) DEFAULT NULL,
  `credit_total` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_palmares_etudiant`),
  KEY `fk_palmares_etudiant_palmares` (`id_palmares`),
  KEY `fk_palmares_etudiant_etudiant` (`idetudiant`),
  CONSTRAINT `fk_palmares_etudiant_palmares` FOREIGN KEY (`id_palmares`) REFERENCES `palmares_archive` (`id_palmares`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour l'historique des modifications des palmarès
CREATE TABLE IF NOT EXISTS `palmares_historique` (
  `id_historique` int(11) NOT NULL AUTO_INCREMENT,
  `id_palmares` int(11) NOT NULL,
  `action` enum('Creation','Modification','Suppression') NOT NULL,
  `details` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id_historique`),
  KEY `fk_historique_palmares` (`id_palmares`),
  KEY `fk_historique_user` (`idUser`),
  CONSTRAINT `fk_historique_palmares` FOREIGN KEY (`id_palmares`) REFERENCES `palmares_archive` (`id_palmares`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

