
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

CREATE TABLE `etudiant_historique` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `idetudiant` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `idannee_acad` int(11) NOT NULL,
  `date_inscription` date NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `agent_section`
--

CREATE TABLE IF NOT EXISTS `agent_section` (
  `idagent_section` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idsection` int(11) NOT NULL,
  `dateAffectation` datetime DEFAULT current_timestamp(),
  `estPrincipal` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `annee_acad`
--

CREATE TABLE IF NOT EXISTS `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
ALTER TABLE configuration_universite ADD COLUMN credit_heure INT NOT NULL DEFAULT 25;


-- --------------------------------------------------------

--
-- Structure de la table `conversation`
--

CREATE TABLE IF NOT EXISTS `conversation` (
  `idconversation` int(11) NOT NULL,
  `sujets_idsujets` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cotes_grille`
--

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

CREATE TABLE IF NOT EXISTS `deliberation` (
  `iddeliberation` int(11) NOT NULL,
  `idbureau` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `date_deliberation` datetime NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `proces_verbal` varchar(255) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `statut` enum('En préparation','Effectuée','Validée','Publiée') NOT NULL DEFAULT 'En préparation',
  `idUser` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `annee_acad_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `demande_conge`
--

CREATE TABLE IF NOT EXISTS `demande_conge` (
  `iddemande_conge` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idtype_conge` int(11) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `motif` text DEFAULT NULL,
  `document_justificatif` varchar(255) DEFAULT NULL,
  `statut` enum('En attente','Approuvé','Refusé','Annulé') NOT NULL DEFAULT 'En attente',
  `commentaire_decision` text DEFAULT NULL,
  `date_demande` datetime DEFAULT current_timestamp(),
  `date_decision` datetime DEFAULT NULL,
  `idDecideur` int(11) DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `depot_memoire` (
  `iddepot_memoire` int(11) NOT NULL,
  `dateDepot` date DEFAULT NULL,
  `fichier` varchar(145) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `sujets_idsujets` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `t_roles` (
  `idRole` int(255) NOT NULL,
  `nomRole` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `t_permissions` (
  `idPerm` int(255) NOT NULL,
  `idMod` int(255) NOT NULL,
  `codePerm` varchar(255) NOT NULL,
  `nomPerm` varchar(255) NOT NULL,
  `descPerm` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `t_user_permissions` (
  `idUP` int(255) NOT NULL,
  `idRole` int(255) NOT NULL,
  `idPerm` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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


CREATE TABLE IF NOT EXISTS `devoirs` (
  `iddevoir` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `date_limite` datetime DEFAULT NULL,
  `est_payant` int(11) DEFAULT NULL,
  `idfrais` int(11) DEFAULT NULL,
  `idECUE` int(11) NOT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ecard_access_keys` (
  `id` int(11) NOT NULL,
  `access_key` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecard_verification_log`
--

CREATE TABLE IF NOT EXISTS `ecard_verification_log` (
  `id` int(11) NOT NULL,
  `card_id` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `verification_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `echanges_taches`
--

CREATE TABLE IF NOT EXISTS `echanges_taches` (
  `idechange` int(11) NOT NULL,
  `dateEchange` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `fichierJoint` varchar(145) DEFAULT NULL,
  `taches_idtaches` int(11) NOT NULL,
  `type_auteur` enum('Directeur','Encadreur','Etudiant') NOT NULL,
  `idAuteur` int(11) NOT NULL
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

-- --------------------------------------------------------

--
-- Structure de la table `enseignant_section`
--

CREATE TABLE IF NOT EXISTS `enseignant_section` (
  `idenseignant_section` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `idsection` int(11) NOT NULL,
  `dateAffectation` datetime DEFAULT current_timestamp(),
  `estPrincipal` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `enseignant_specialisation`
--

CREATE TABLE IF NOT EXISTS `enseignant_specialisation` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idSpecialisation` int(11) NOT NULL,
  `dateAffectation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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


-- --------------------------------------------------------

--
-- Structure de la table `etudiants_cards`
--

CREATE TABLE IF NOT EXISTS `etudiants_cards` (
  `id` int(11) NOT NULL,
  `card_id` varchar(50) NOT NULL,
  `student_id` int(11) NOT NULL,
  `issued_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `status` enum('active','revoked','expired') NOT NULL DEFAULT 'active',
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  `revocation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiant_en_ordre`
--

CREATE TABLE IF NOT EXISTS `etudiant_en_ordre` (
  `idetudiant_ordre` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `idfrais` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp(),
  `idimport` int(11) DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiant_tempon`
--

CREATE TABLE IF NOT EXISTS `etudiant_tempon` (
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
  `promotion_designation` varchar(100) DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `excel_tokens`
--

CREATE TABLE IF NOT EXISTS `excel_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `formation_agent` (
  `idformation` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `niveau` enum('Certificat primaire','Diplôme d''état','Graduat','Licence','Master','Doctorat') NOT NULL,
  `etablissement` varchar(255) NOT NULL,
  `filiere` varchar(255) DEFAULT NULL,
  `annee_obtention` int(11) DEFAULT NULL,
  `diplome_fichier` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `frais` (
  `idfrais` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `estObligatoire` tinyint(1) NOT NULL DEFAULT 1,
  `dateCreation` datetime NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `frais_soutenance`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `grade`
--

CREATE TABLE IF NOT EXISTS `grade` (
  `idgrade` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type_agent` enum('Enseignant','Administratif','Recherche') DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Structure de la table `historique_grade`
--

CREATE TABLE IF NOT EXISTS `historique_grade` (
  `idhistorique_grade` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idgrade` int(11) NOT NULL,
  `date_promotion` date NOT NULL,
  `reference_decision` varchar(255) DEFAULT NULL,
  `reference_notification` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique_notes`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `horaires_cours`
--

CREATE TABLE IF NOT EXISTS `horaires_cours` (
  `idhoraire` int(11) NOT NULL,
  `date_cours` date DEFAULT NULL,
  `jour` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(255) NOT NULL,
  `type_cours` enum('CM','TD','TP','Evaluation') NOT NULL DEFAULT 'CM',
  `idECUE` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `intervention_jury` (
  `idintervention` int(11) NOT NULL,
  `iddeliberation` int(11) NOT NULL,
  `type_element` enum('ECUE','UE','Semestre','Annuel') NOT NULL,
  `id_element` int(11) NOT NULL COMMENT 'ID de l''ECUE, UE, semestre selon type_element',
  `matricule` varchar(255) NOT NULL,
  `note_originale` decimal(5,2) DEFAULT NULL,
  `note_modifiee` decimal(5,2) DEFAULT NULL,
  `motif` text NOT NULL,
  `date_intervention` datetime DEFAULT current_timestamp(),
  `idAgent` int(11) NOT NULL COMMENT 'Membre du jury ayant décidé',
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur ayant enregistré'
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

CREATE TABLE IF NOT EXISTS `jury_soutenance` (
  `idjury` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `role` enum('Président','Secrétaire','Membre','Lecteur 1','Lecteur 2') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lecteurs_soutenance`
--

CREATE TABLE IF NOT EXISTS `lecteurs_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `est_premier_lecteur` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `membre_bureau_jury` (
  `idmembre` int(11) NOT NULL,
  `idbureau` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `fonction` varchar(100) DEFAULT NULL,
  `date_ajout` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mention_speciale`
--

CREATE TABLE IF NOT EXISTS `mention_speciale` (
  `idmention` int(11) NOT NULL,
  `type_mention` enum('Félicitations','Encouragements','Avertissement','Blâme') NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `iddeliberation` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `orientation` (
  `idorientation` int(11) NOT NULL,
  `designationOrientation` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp(),
  `section_idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement`
--

CREATE TABLE IF NOT EXISTS `paiement` (
  `idpaiement` int(11) NOT NULL,
  `etudiant_idetudiant` int(11) NOT NULL,
  `frais_idfrais` int(11) NOT NULL,
  `montantPaye` decimal(10,2) NOT NULL,
  `referencePaiement` varchar(255) NOT NULL,
  `datePaiement` datetime NOT NULL,
  `estComplet` tinyint(1) NOT NULL DEFAULT 0,
  `modePaiement` varchar(50) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `paiement_soutenance` (
  `idpaiement_soutenance` int(11) NOT NULL,
  `etudiant_id` int(11) DEFAULT NULL,
  `frais_soutenance_id` int(11) DEFAULT NULL,
  `montantPaye` decimal(10,2) NOT NULL,
  `referencePaiement` varchar(100) DEFAULT NULL,
  `datePaiement` datetime DEFAULT NULL,
  `estComplet` tinyint(1) DEFAULT 0,
  `modePaiement` varchar(50) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `annee_acad_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parties_cours`
--

CREATE TABLE IF NOT EXISTS `parties_cours` (
  `idpartie` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ordre` int(11) NOT NULL DEFAULT 1,
  `idECUE` int(11) NOT NULL,
  `estVisible` tinyint(1) DEFAULT 1,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `points` (
  `idpoints` int(11) NOT NULL,
  `coteObtenu` decimal(10,2) DEFAULT NULL,
  `typeEvaluation` int(11) DEFAULT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `annee_acad_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `preinscription` (
  `idpreinscription` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `postnom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `lieu_naissance` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `sexe` enum('M','F') NOT NULL,
  `etat_civil` varchar(50) NOT NULL,
  `nationalite` varchar(100) NOT NULL,
  `nom_pere` varchar(200) DEFAULT NULL,
  `nom_mere` varchar(200) DEFAULT NULL,
  `province` varchar(100) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `territoire` varchar(100) DEFAULT NULL,
  `secteur` varchar(100) DEFAULT NULL,
  `avenue` varchar(200) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `quartier` varchar(100) NOT NULL,
  `commune` varchar(100) NOT NULL,
  `telephone` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `personne_contact` varchar(200) DEFAULT NULL,
  `telephone_contact` varchar(50) DEFAULT NULL,
  `ecole_secondaire` varchar(255) DEFAULT NULL,
  `adresse_ecole` varchar(255) DEFAULT NULL,
  `section_humanites` varchar(100) DEFAULT NULL,
  `option_humanites` varchar(100) DEFAULT NULL,
  `centre_examen` varchar(255) DEFAULT NULL,
  `annee_diplome` int(11) DEFAULT NULL,
  `lieu_date_diplome` varchar(255) DEFAULT NULL,
  `pourcentage` decimal(5,2) DEFAULT NULL,
  `numero_diplome` varchar(100) DEFAULT NULL,
  `activites_professionnelles` text DEFAULT NULL,
  `etudes_post_secondaires` text DEFAULT NULL,
  `type_inscription` varchar(250) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `orientation_choix1` int(11) DEFAULT NULL,
  `orientation_choix2` int(11) DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `annee_academique_precedente` int(11) DEFAULT NULL,
  `promotion_precedente` int(11) DEFAULT NULL,
  `type_reinscription` varchar(50) DEFAULT NULL,
  `nouvelle_section_id` int(11) DEFAULT NULL,
  `motif_changement` text DEFAULT NULL,
  `annee_abandon` int(11) DEFAULT NULL,
  `motif_abandon` text DEFAULT NULL,
  `motif_reintegration` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `attestation_naissance` varchar(255) DEFAULT NULL,
  `diplome_etat` varchar(255) DEFAULT NULL,
  `bulletin_5eme` varchar(255) DEFAULT NULL,
  `bulletin_6eme` varchar(255) DEFAULT NULL,
  `attestation_aptitude` varchar(255) DEFAULT NULL,
  `preuve_paiement` varchar(255) DEFAULT NULL,
  `releve_notes` varchar(255) DEFAULT NULL,
  `documents_additionnels` text DEFAULT NULL,
  `signature_electronique` varchar(255) NOT NULL,
  `date_signature` date NOT NULL,
  `statut` enum('En attente','Validée','Rejetée') NOT NULL DEFAULT 'En attente',
  `commentaire_admin` text DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `annee_acad_id` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `universite` varchar(255) DEFAULT NULL,
  `faculte` varchar(255) DEFAULT NULL,
  `diplome_licence` varchar(255) DEFAULT NULL,
  `releve_notes_licence` varchar(255) DEFAULT NULL,
  `memoire_licence` varchar(255) DEFAULT NULL,
  `attestation_aptitude_master` varchar(255) DEFAULT NULL,
  `attestation_reussite` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `presence_agent`
--

CREATE TABLE IF NOT EXISTS `presence_agent` (
  `idPresence_agent` int(11) NOT NULL,
  `annee` varchar(45) DEFAULT NULL,
  `mois` varchar(45) DEFAULT NULL,
  `joursPresence` int(11) DEFAULT NULL,
  `joursAbsence` int(11) DEFAULT NULL,
  `joursRetard` int(11) NOT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Agent_idAgent` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `processus_deliberation` (
  `idprocessus` int(11) NOT NULL,
  `iddeliberation` int(11) NOT NULL,
  `etape` enum('Initialisation','Calcul ECUE','Calcul UE','Compensation intra-UE','Compensation inter-UE','Compensation inter-semestre','Décisions jury','Finalisation','Validation','Publication') NOT NULL,
  `statut` enum('En attente','En cours','Terminé','Erreur') NOT NULL DEFAULT 'En attente',
  `message` text DEFAULT NULL,
  `progression` int(11) DEFAULT 0 COMMENT 'Pourcentage de progression',
  `date_debut` datetime DEFAULT NULL,
  `date_fin` datetime DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Structure de la table `regle_passage`
--

CREATE TABLE IF NOT EXISTS `regle_passage` (
  `idregle` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `credits_min_passage` int(11) DEFAULT 30 COMMENT 'Nombre minimum de crédits pour passer à l''année suivante',
  `nombre_ue_echec_max` int(11) DEFAULT 2 COMMENT 'Nombre max d''UE en échec autorisées pour passer',
  `autoriser_dette` tinyint(1) DEFAULT 1 COMMENT 'Autoriser le passage avec dette',
  `max_dette_credits` int(11) DEFAULT 16 COMMENT 'Nombre max de crédits en dette autorisés',
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reponses_devoir`
--

CREATE TABLE IF NOT EXISTS `reponses_devoir` (
  `idreponse` int(11) NOT NULL,
  `fichier` varchar(255) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `note` float DEFAULT NULL,
  `feedback_enseignant` text DEFAULT NULL,
  `iddevoir` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `date_soumission` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `research_info`
--

CREATE TABLE IF NOT EXISTS `research_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `unite_recherche` varchar(255) DEFAULT NULL,
  `projet_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


-- --------------------------------------------------------

--
-- Structure de la table `responsable_orientation`
--

CREATE TABLE IF NOT EXISTS `responsable_orientation` (
  `idresponsable_orientation` int(11) NOT NULL,
  `noms` varchar(145) DEFAULT NULL,
  `fonction` varchar(145) DEFAULT NULL,
  `signature` varchar(145) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `orientation_idorientation` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `responsable_section`
--

CREATE TABLE IF NOT EXISTS `responsable_section` (
  `idresponsable_section` int(11) NOT NULL,
  `noms` varchar(245) DEFAULT NULL,
  `fonction` varchar(145) DEFAULT NULL,
  `signature` varchar(145) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `section_idsection` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ressources_cours` (
  `idressource` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type_ressource` enum('PDF','Vidéo','Audio','Présentation','Lien','Autre') NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `lien_externe` varchar(255) DEFAULT NULL,
  `est_payant` tinyint(1) DEFAULT 0,
  `idfrais` int(11) DEFAULT NULL,
  `idpartie` int(11) NOT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `resultat_deliberation`
--

CREATE TABLE IF NOT EXISTS `resultat_deliberation` (
  `idresultat` int(11) NOT NULL,
  `iddeliberation` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `idsemestre` int(11) NOT NULL,
  `moyenne_generale` decimal(5,2) DEFAULT NULL,
  `credits_acquis` int(11) DEFAULT 0,
  `credits_total` int(11) DEFAULT 0,
  `decision` enum('Admis','Ajourné','Admis par compensation','Admis sous condition') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_calcul` datetime DEFAULT current_timestamp(),
  `est_final` tinyint(1) DEFAULT 0 COMMENT 'Indique si ce résultat est la version définitive',
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `salle`
--

CREATE TABLE IF NOT EXISTS `salle` (
  `idSalle` int(11) NOT NULL,
  `designationSalle` varchar(255) NOT NULL,
  `dateCreation` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `section`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `service`
--

CREATE TABLE IF NOT EXISTS `service` (
  `idService` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `Responsable` varchar(145) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `session`
--

CREATE TABLE IF NOT EXISTS `session` (
  `idsession` int(11) NOT NULL,
  `designSession` varchar(45) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `solde_conge`
--

CREATE TABLE IF NOT EXISTS `solde_conge` (
  `idsolde_conge` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idtype_conge` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `jours_acquis` int(11) NOT NULL DEFAULT 0,
  `jours_pris` int(11) NOT NULL DEFAULT 0,
  `jours_reportes` int(11) NOT NULL DEFAULT 0,
  `date_mise_a_jour` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Structure de la table `specialisation`
--

CREATE TABLE IF NOT EXISTS `specialisation` (
  `idSpecialisation` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `dateCreation` datetime NOT NULL,
  `idUnite_recherche` int(11) NOT NULL,
  `idorientation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `statistique_recherche`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `statut_devoir_etudiant`
--

CREATE TABLE IF NOT EXISTS `statut_devoir_etudiant` (
  `id` int(11) NOT NULL,
  `iddevoir` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `statut` varchar(50) NOT NULL COMMENT 'Statut: Non commencé, Vu, Soumis, Noté, etc.',
  `date_modification` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Structure de la table `sujet_validation_history`
--

CREATE TABLE IF NOT EXISTS `sujet_validation_history` (
  `id` int(11) NOT NULL,
  `idsujets` int(11) NOT NULL,
  `status` enum('En attente','Validé','Rejeté','Modifié') NOT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `support_cours`
--

CREATE TABLE IF NOT EXISTS `support_cours` (
  `idsupport` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fichier` varchar(255) NOT NULL,
  `est_payant` tinyint(1) DEFAULT 0,
  `idfrais` int(11) DEFAULT NULL,
  `idECUE` int(11) NOT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `taches`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `teacher_info`
--

CREATE TABLE IF NOT EXISTS `teacher_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `specialisation` varchar(255) DEFAULT NULL,
  `domaine_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Structure de la table `typeevaluation`
--

CREATE TABLE IF NOT EXISTS `typeevaluation` (
  `idType` int(11) NOT NULL,
  `designationT` varchar(155) NOT NULL,
  `categorie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `type_conge`
--

CREATE TABLE IF NOT EXISTS `type_conge` (
  `idtype_conge` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duree_standard` int(11) DEFAULT NULL COMMENT 'Durée standard en jours ouvrables',
  `est_cumulable` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp()
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


CREATE TABLE IF NOT EXISTS `unite_recherche` (
  `idunite_recherche` int(11) NOT NULL,
  `designation_UR` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `unite_recherche_section`
--

CREATE TABLE IF NOT EXISTS `unite_recherche_section` (
  `idur_section` int(11) NOT NULL,
  `idunite_recherche` int(11) NOT NULL,
  `idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Créer une table d'association entre unités de recherche et orientations
CREATE TABLE IF NOT EXISTS `unite_recherche_orientation` (
  `idur_orientation` int(11) NOT NULL AUTO_INCREMENT,
  `idunite_recherche` int(11) NOT NULL,
  `idorientation` int(11) NOT NULL,
  PRIMARY KEY (`idur_orientation`),
  KEY `fk_uro_unite` (`idunite_recherche`),
  KEY `fk_uro_orientation` (`idorientation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE IF NOT EXISTS `validation_notes_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `est_valide` tinyint(1) DEFAULT 0,
  `date_validation` datetime DEFAULT NULL,
  `id_validateur` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `est_visible` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les séances de cours
CREATE TABLE IF NOT EXISTS `seance_cours` (
  `idseance` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `date_seance` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(255) DEFAULT NULL,
  `qrcode` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `idECUE` int(11) NOT NULL,
  `idhoraire` int(11) DEFAULT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idseance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les laboratoires
CREATE TABLE IF NOT EXISTS `laboratoire` (
  `idlabo` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `responsable_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `annee_acad_id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`idlabo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les autorisations de laboratoire
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

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `travail_id` int(11) DEFAULT NULL,
  `date_consultation` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
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

-- Table pour définir les documents obligatoires par cycle
CREATE TABLE IF NOT EXISTS `documents_obligatoires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cycle` enum('Premier','Deuxieme','Troisieme','Tous') NOT NULL DEFAULT 'Tous',
  `est_obligatoire` tinyint(1) DEFAULT 1,
  `delai_jours` int(11) DEFAULT NULL COMMENT 'Délai en jours pour fournir le document après inscription',
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Modifier la table existante pour l'adapter au nouveau système
ALTER TABLE `etudiant_documents` 
  ADD COLUMN `matricule` varchar(50) NOT NULL AFTER `idetudiant`,
  ADD COLUMN `document_obligatoire_id` int(11) DEFAULT NULL AFTER `type_document`,
  ADD COLUMN `annee_acad_id` int(11) NOT NULL AFTER `date_ajout`,
  ADD COLUMN `idUser` int(11) NOT NULL AFTER `annee_acad_id`,
  ADD COLUMN `statut` enum('Valide','En attente de validation','Rejeté') DEFAULT 'En attente de validation' AFTER `idUser`,
  ADD COLUMN `commentaire_validation` text DEFAULT NULL AFTER `statut`,
  ADD COLUMN `date_validation` datetime DEFAULT NULL AFTER `commentaire_validation`,
  ADD COLUMN `idValidateur` int(11) DEFAULT NULL AFTER `date_validation`,
  ADD INDEX `idx_etudiant_documents_matricule` (`matricule`),
  ADD INDEX `idx_etudiant_documents_obligatoire` (`document_obligatoire_id`),
  ADD CONSTRAINT `fk_etudiant_documents_obligatoire` FOREIGN KEY (`document_obligatoire_id`) REFERENCES `documents_obligatoires` (`id`) ON DELETE SET NULL;

-- Table pour suivre l'historique des validations de documents
CREATE TABLE IF NOT EXISTS `etudiant_documents_historique` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `statut_precedent` enum('Valide','En attente de validation','Rejeté') NOT NULL,
  `nouveau_statut` enum('Valide','En attente de validation','Rejeté') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_historique_document` (`document_id`),
  CONSTRAINT `fk_historique_document` FOREIGN KEY (`document_id`) REFERENCES `etudiant_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;






-- Table pour les séances de laboratoire
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
  PRIMARY KEY (`idseance_labo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Table pour les présences des étudiants aux cours
CREATE TABLE IF NOT EXISTS `presence_cours` (
  `idpresence` int(11) NOT NULL AUTO_INCREMENT,
  `idseance` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `heure_arrivee` datetime NOT NULL,
  `statut` enum('Présent','Retard','Absent','Excusé') NOT NULL DEFAULT 'Présent',
  `commentaire` text DEFAULT NULL,
  `methode_enregistrement` enum('QR Code','Manuel') NOT NULL DEFAULT 'QR Code',
  `idUser` int(11) DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idpresence`),
  UNIQUE KEY `idx_unique_presence` (`idseance`, `idetudiant`) 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les présences des étudiants aux laboratoires
CREATE TABLE IF NOT EXISTS `presence_labo` (
  `idpresence_labo` int(11) NOT NULL AUTO_INCREMENT,
  `idseance_labo` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `heure_arrivee` datetime NOT NULL,
  `statut` enum('Présent','Retard','Absent','Excusé') NOT NULL DEFAULT 'Présent',
  `commentaire` text DEFAULT NULL,
  `methode_enregistrement` enum('QR Code','Manuel') NOT NULL DEFAULT 'QR Code',
  `idUser` int(11) DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idpresence_labo`),
  UNIQUE KEY `idx_unique_presence_labo` (`idseance_labo`, `idetudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- Ajouter à la table presence_cours
ALTER TABLE presence_cours ADD COLUMN ip_address VARCHAR(45) AFTER methode_enregistrement;

-- Ajouter à la table presence_labo
ALTER TABLE presence_labo ADD COLUMN ip_address VARCHAR(45) AFTER methode_enregistrement;

-- Ajouter à la table journal_activites
ALTER TABLE journal_activites ADD COLUMN ip_address VARCHAR(45) AFTER date_activite;
-- Créer une table pour suivre les tentatives de fraude
CREATE TABLE IF NOT EXISTS IF NOT EXISTS tentatives_fraude_presence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    idseance INT NOT NULL,
    type_seance ENUM('cours', 'labo') NOT NULL,
    matricule_tente VARCHAR(50),
    date_tentative DATETIME NOT NULL,
    details TEXT,
    INDEX (ip_address),
    INDEX (idseance, type_seance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter des colonnes pour les coordonnées géographiques dans la table presence_cours
ALTER TABLE presence_cours 
ADD COLUMN latitude DECIMAL(10, 8) AFTER ip_address,
ADD COLUMN longitude DECIMAL(11, 8) AFTER latitude;

-- Ajouter des colonnes pour les coordonnées géographiques dans la table presence_labo
ALTER TABLE presence_labo 
ADD COLUMN latitude DECIMAL(10, 8) AFTER ip_address,
ADD COLUMN longitude DECIMAL(11, 8) AFTER latitude;

-- Ajouter des colonnes pour les coordonnées de référence dans la table seance_cours
ALTER TABLE seance_cours 
ADD COLUMN ref_latitude DECIMAL(10, 8),
ADD COLUMN ref_longitude DECIMAL(11, 8),
ADD COLUMN geo_verification_active BOOLEAN DEFAULT TRUE;

-- Ajouter des colonnes pour les coordonnées de référence dans la table seance_labo
ALTER TABLE seance_labo 
ADD COLUMN ref_latitude DECIMAL(10, 8),
ADD COLUMN ref_longitude DECIMAL(11, 8),
ADD COLUMN geo_verification_active BOOLEAN DEFAULT TRUE;
ALTER TABLE laboratoire 
ADD COLUMN ref_latitude DECIMAL(10, 8) AFTER idUser,
ADD COLUMN ref_longitude DECIMAL(11, 8) AFTER ref_latitude,
ADD COLUMN geo_verification_active BOOLEAN DEFAULT TRUE AFTER ref_longitude;











-- Table pour gérer les grilles salariales des agents permanents
CREATE TABLE IF NOT EXISTS `grille_salaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_id` int(11) NOT NULL,
  `echelon` int(11) NOT NULL DEFAULT 1,
  `anciennete_min` int(11) NOT NULL DEFAULT 0 COMMENT 'Ancienneté minimale en mois',
  `salaire_base` decimal(15,2) NOT NULL,
  `prime_fonction` decimal(15,2) DEFAULT 0.00,
  `prime_risque` decimal(15,2) DEFAULT 0.00,
  `indemnite_transport` decimal(15,2) DEFAULT 0.00,
  `indemnite_logement` decimal(15,2) DEFAULT 0.00,
  `autres_avantages` decimal(15,2) DEFAULT 0.00,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_grade_echelon` (`grade_id`, `echelon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les taux horaires des enseignants visiteurs
CREATE TABLE IF NOT EXISTS `taux_horaires_visiteurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_id` int(11) NOT NULL,
  `niveau_cours` enum('Premier cycle','Deuxième cycle','Troisième cycle') NOT NULL,
  `type_cours` enum('CM','TD','TP','Évaluation') NOT NULL,
  `taux_horaire` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `annee_acad_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_taux_unique` (`grade_id`, `niveau_cours`, `type_cours`, `annee_acad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les charges d'enseignement des visiteurs
CREATE TABLE IF NOT EXISTS `charges_enseignement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idAgent` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `volume_cm` decimal(10,2) DEFAULT 0.00 COMMENT 'Volume horaire cours magistraux',
  `volume_td` decimal(10,2) DEFAULT 0.00 COMMENT 'Volume horaire travaux dirigés',
  `volume_tp` decimal(10,2) DEFAULT 0.00 COMMENT 'Volume horaire travaux pratiques',
  `volume_evaluation` decimal(10,2) DEFAULT 0.00 COMMENT 'Volume horaire évaluations',
  `est_validee` tinyint(1) DEFAULT 0,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_charge_unique` (`idAgent`, `idECUE`, `annee_acad_id`),
  KEY `fk_charge_agent` (`idAgent`),
  KEY `fk_charge_ecue` (`idECUE`),
  KEY `fk_charge_annee` (`annee_acad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  est_active tinyint(1) DEFAULT 0,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour le suivi des heures effectuées
CREATE TABLE IF NOT EXISTS `suivi_heures_enseignement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idAgent` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `date_seance` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `duree` decimal(5,2) NOT NULL COMMENT 'Durée en heures',
  `type_cours` enum('CM','TD','TP','Évaluation') NOT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `validation_orientation` tinyint(1) DEFAULT 0,
  `date_validation_dept` datetime DEFAULT NULL,
  `idValidateur_dept` int(11) DEFAULT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
    PRIMARY KEY (`id`),
  KEY `fk_suivi_agent` (`idAgent`),
  KEY `fk_suivi_ecue` (`idECUE`),
  KEY `fk_suivi_annee` (`annee_acad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les ordres de paiement des enseignants visiteurs
CREATE TABLE IF NOT EXISTS `ordres_paiement_visiteurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `periode_debut` date NOT NULL,
  `periode_fin` date NOT NULL,
  `nb_heures_cm` decimal(10,2) DEFAULT 0.00,
  `nb_heures_td` decimal(10,2) DEFAULT 0.00,
  `nb_heures_tp` decimal(10,2) DEFAULT 0.00,
  `nb_heures_evaluation` decimal(10,2) DEFAULT 0.00,
  `montant_brut` decimal(15,2) NOT NULL,
  `retenues` decimal(15,2) DEFAULT 0.00,
  `montant_net` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `statut` enum('En préparation','Validé','Payé','Annulé') NOT NULL DEFAULT 'En préparation',
  `motif` text DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference_ordre` (`reference`),
  KEY `fk_ordre_agent` (`idAgent`),
  KEY `fk_ordre_transaction` (`transaction_id`),
  KEY `fk_ordre_annee` (`annee_acad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des détails des paiements visiteurs (cours concernés)
CREATE TABLE IF NOT EXISTS `details_paiement_visiteurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordre_paiement_id` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `nb_heures_cm` decimal(10,2) DEFAULT 0.00,
  `nb_heures_td` decimal(10,2) DEFAULT 0.00,
  `nb_heures_tp` decimal(10,2) DEFAULT 0.00,
  `nb_heures_evaluation` decimal(10,2) DEFAULT 0.00,
  `montant` decimal(15,2) NOT NULL,
  `commentaire` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detail_ordre` (`ordre_paiement_id`),
  KEY `fk_detail_ecue` (`idECUE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les paies mensuelles des agents permanents
CREATE TABLE IF NOT EXISTS `paies_mensuelles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `mois` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `salaire_base` decimal(15,2) NOT NULL,
  `prime_fonction` decimal(15,2) DEFAULT 0.00,
  `prime_risque` decimal(15,2) DEFAULT 0.00,
  `indemnite_transport` decimal(15,2) DEFAULT 0.00,
  `indemnite_logement` decimal(15,2) DEFAULT 0.00,
  `autres_primes` decimal(15,2) DEFAULT 0.00,
  `heures_supplementaires` decimal(15,2) DEFAULT 0.00,
  `retenues_impots` decimal(15,2) DEFAULT 0.00,
  `retenues_sociales` decimal(15,2) DEFAULT 0.00,
  `avances` decimal(15,2) DEFAULT 0.00,
  `autres_retenues` decimal(15,2) DEFAULT 0.00,
  `montant_net` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `statut` enum('En préparation','Validé','Payé','Annulé') NOT NULL DEFAULT 'En préparation',
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference_paie` (`reference`),
  UNIQUE KEY `idx_paie_mensuelle_unique` (`idAgent`, `mois`, `annee`),
  KEY `fk_paie_agent` (`idAgent`),
  KEY `fk_paie_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour le suivi des états de paiement (récapitulatifs mensuels)
CREATE TABLE IF NOT EXISTS `etats_paiement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `mois` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `type_agent` enum('Permanent','Visiteur','Tous') NOT NULL DEFAULT 'Tous',
  `orientation_id` int(11) DEFAULT NULL,
  `date_generation` datetime NOT NULL,
  `total_brut` decimal(15,2) NOT NULL,
  `total_retenues` decimal(15,2) NOT NULL,
  `total_net` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `nb_agents` int(11) NOT NULL,
  `statut` enum('Brouillon','Validé','Payé','Clôturé') NOT NULL DEFAULT 'Brouillon',
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL,
  `date_cloture` datetime DEFAULT NULL,
  `fichier_pdf` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference_etat` (`reference`),
  UNIQUE KEY `idx_etat_mensuel` (`mois`, `annee`, `type_agent`, `orientation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les détails des états de paiement
CREATE TABLE IF NOT EXISTS `details_etats_paiement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `etat_paiement_id` int(11) NOT NULL,
  `paie_id` int(11) DEFAULT NULL COMMENT 'ID de la paie mensuelle pour permanents',
  `ordre_paiement_id` int(11) DEFAULT NULL COMMENT 'ID de l''ordre de paiement pour visiteurs',
  `idAgent` int(11) NOT NULL,
  `montant_brut` decimal(15,2) NOT NULL,
  `montant_retenues` decimal(15,2) NOT NULL,
  `montant_net` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detail_etat` (`etat_paiement_id`),
  KEY `fk_detail_paie` (`paie_id`),
  KEY `fk_detail_ordre_paiement` (`ordre_paiement_id`),
  KEY `fk_detail_etat_agent` (`idAgent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour le statut des cours par rapport au paiement
CREATE TABLE IF NOT EXISTS `statut_paiement_cours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idAgent` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `heures_programmees` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_realisees` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_validees` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_payees` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_restantes` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_paye` decimal(15,2) NOT NULL DEFAULT 0.00,
  `statut` enum('paye','non paye') NOT NULL DEFAULT 'non paye',
  PRIMARY KEY (`id`),
  KEY `fk_statut_agent` (`idAgent`),
  KEY `fk_statut_ecue` (`idECUE`),
  KEY `fk_statut_annee_acad` (`annee_acad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `type_agent`
--

CREATE TABLE IF NOT EXISTS `type_agent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `libelle` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `unite_enseignement`
--

CREATE TABLE IF NOT EXISTS `unite_enseignement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `agent` (
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

ALTER TABLE `etudiant` 
ADD COLUMN `dossier_complete` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indique si l''étudiant a complété son dossier';

-- Table principale des rendez-vous
CREATE TABLE `rendez_vous` (
  `idRendez_vous` int(11) NOT NULL AUTO_INCREMENT,
  `Agent_idAgent` int(11) NOT NULL,
  `Service_idService` int(11) NOT NULL,
  `Patient_idPatient` int(11) DEFAULT NULL,
  `contact_externe` varchar(255) DEFAULT NULL,
  `email_externe` varchar(255) DEFAULT NULL,
  `telephone_externe` varchar(20) DEFAULT NULL,
  `date_rendez_vous` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `objet` varchar(500) NOT NULL,
  `description` text,
  `lieu` varchar(255) DEFAULT NULL,
  `statut_rendez_vous` enum('planifie','confirme','reporte','annule','termine') DEFAULT 'planifie',
  `type_rendez_vous` varchar(100) DEFAULT NULL,
  `priorite` enum('basse','normale','haute','urgente') DEFAULT 'normale',
  `rappel_active` tinyint(1) DEFAULT 1,
  `delai_rappel` int(11) DEFAULT 30,
  `commentaires` text,
  `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cree_par` int(11) NOT NULL,
  `modifie_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`idRendez_vous`),
  KEY `fk_rendez_vous_Agent1_idx` (`Agent_idAgent`),
  KEY `fk_rendez_vous_Service1_idx` (`Service_idService`),
  KEY `fk_rendez_vous_Patient1_idx` (`Patient_idPatient`),
  KEY `fk_rendez_vous_User_creation_idx` (`cree_par`),
  KEY `fk_rendez_vous_User_modification_idx` (`modifie_par`),
  KEY `idx_date_rendez_vous` (`date_rendez_vous`),
  KEY `idx_statut` (`statut_rendez_vous`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des types de rendez-vous
CREATE TABLE `type_rendez_vous` (
  `idType_rendez_vous` int(11) NOT NULL AUTO_INCREMENT,
  `designation` varchar(100) NOT NULL,
  `description` text,
  `duree_defaut` int(11) DEFAULT 60,
  `couleur` varchar(7) DEFAULT '#007bff',
  `actif` tinyint(1) DEFAULT 1,
  `Service_idService` int(11) DEFAULT NULL,
  PRIMARY KEY (`idType_rendez_vous`),
  KEY `fk_type_rendez_vous_Service1_idx` (`Service_idService`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table pour les visites
CREATE TABLE `visites` (
  `idVisite` int(11) NOT NULL AUTO_INCREMENT,
  `nom_visiteur` varchar(255) NOT NULL,
  `prenom_visiteur` varchar(255) DEFAULT NULL,
  `entreprise_visiteur` varchar(255) DEFAULT NULL,
  `telephone_visiteur` varchar(20) DEFAULT NULL,
  `email_visiteur` varchar(255) DEFAULT NULL,
  `Agent_idAgent` int(11) NOT NULL COMMENT 'Agent à visiter',
  `Service_idService` int(11) NOT NULL COMMENT 'Service concerné',
  `date_visite` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `objet_visite` varchar(500) NOT NULL,
  `description` text,
  `lieu_rencontre` varchar(255) DEFAULT NULL,
  `statut_visite` enum('programmee','en_cours','terminee','annulee','reportee') DEFAULT 'programmee',
  `type_visite` enum('professionnelle','personnelle','officielle','urgente') DEFAULT 'professionnelle',
  `nombre_accompagnants` int(11) DEFAULT 0,
  `carte_identite` varchar(255) DEFAULT NULL COMMENT 'Numéro de carte d\'identité',
  `observations` text,
  `badge_visiteur` varchar(50) DEFAULT NULL,
  `heure_arrivee_reelle` time DEFAULT NULL,
  `heure_depart_reelle` time DEFAULT NULL,
  `validation_securite` tinyint(1) DEFAULT 0,
  `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cree_par` int(11) NOT NULL,
  `modifie_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`idVisite`),
  KEY `fk_visite_Agent1_idx` (`Agent_idAgent`),
  KEY `fk_visite_Service1_idx` (`Service_idService`),
  KEY `fk_visite_User_creation_idx` (`cree_par`),
  KEY `fk_visite_User_modification_idx` (`modifie_par`),
  KEY `idx_date_visite` (`date_visite`),
  KEY `idx_statut_visite` (`statut_visite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table pour l'historique des visites
CREATE TABLE `historique_visites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idVisite` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `ancien_statut` varchar(50) DEFAULT NULL,
  `nouveau_statut` varchar(50) DEFAULT NULL,
  `commentaire` text,
  `date_action` timestamp DEFAULT CURRENT_TIMESTAMP,
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_historique_visite` (`idVisite`),
  CONSTRAINT `fk_historique_visite` FOREIGN KEY (`idVisite`) REFERENCES `visites` (`idVisite`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE `demandes_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idetudiant` int(11) NOT NULL,
  `matricule` varchar(50) NOT NULL,
  `document_obligatoire_id` int(11) NOT NULL,
  `objet` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime NOT NULL,
  `email_envoye` tinyint(1) NOT NULL DEFAULT 0,
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demandes_documents_etudiant` (`idetudiant`),
  KEY `idx_demandes_documents_matricule` (`matricule`),
  KEY `idx_demandes_documents_document` (`document_obligatoire_id`),
  CONSTRAINT `fk_demandes_documents_etudiant` FOREIGN KEY (`idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE,
  CONSTRAINT `fk_demandes_documents_document` FOREIGN KEY (`document_obligatoire_id`) REFERENCES `documents_obligatoires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table principale pour les palmarès d'archives
CREATE TABLE IF NOT EXISTS `palmares_archives` (
  `idpalmares` int(11) NOT NULL AUTO_INCREMENT,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fichier_scanne` varchar(255) DEFAULT NULL,
  `annee_academique` varchar(50) NOT NULL,
  `section` varchar(100) NOT NULL,
  `promotion` varchar(100) NOT NULL,
  `session` varchar(50) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`idpalmares`),
  KEY `fk_palmares_archives_user` (`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour stocker les étudiants des palmarès d'archives
CREATE TABLE IF NOT EXISTS `etudiants_palmares_archives` (
  `idetudiant_palmares` int(11) NOT NULL AUTO_INCREMENT,
  `idpalmares` int(11) NOT NULL,
  `matricule` varchar(50) NOT NULL,
  `nom_complet` varchar(255) NOT NULL,
  `pourcentage` decimal(5,2) DEFAULT NULL,
  `decision` varchar(50) NOT NULL,
  `session` varchar(50) NOT NULL DEFAULT 'Première session',
  PRIMARY KEY (`idetudiant_palmares`),
  KEY `fk_etudiants_palmares_archives` (`idpalmares`),
  CONSTRAINT `fk_etudiants_palmares_archives` FOREIGN KEY (`idpalmares`) REFERENCES `palmares_archives` (`idpalmares`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table simplifiée pour le suivi des enseignements ECUE
CREATE TABLE IF NOT EXISTS `suivi_enseignement_ecue` (
  `id_suivi` int(11) NOT NULL AUTO_INCREMENT,
  `idECUE` int(11) NOT NULL,
  `date_seance` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `matiere_vue` text NOT NULL,
  `nombre_heures_reelles` decimal(4,2) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `chef_promotion_id` int(11) NOT NULL COMMENT 'ID de l\'étudiant chef de promotion',
  `date_encodage` datetime NOT NULL DEFAULT current_timestamp(),
  `statut_validation` enum('En attente','Validé','Rejeté') NOT NULL DEFAULT 'En attente',
  `date_validation` datetime DEFAULT NULL,
  `appariteur_id` int(11) DEFAULT NULL COMMENT 'ID de l\'appariteur qui valide',
  `commentaire_validation` text DEFAULT NULL,
  `idUser_creation` int(11) NOT NULL,
  PRIMARY KEY (`id_suivi`),
  KEY `fk_suivi_ecue` (`idECUE`),
  KEY `fk_suivi_promotion` (`promotion_idpromotion`),
  KEY `fk_suivi_annee_acad` (`annee_acad_idannee_acad`),
  KEY `fk_suivi_chef_promotion` (`chef_promotion_id`),
  KEY `fk_suivi_appariteur` (`appariteur_id`),
  UNIQUE KEY `idx_unique_seance` (`idECUE`, `date_seance`, `heure_debut`, `promotion_idpromotion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour désigner les chefs de promotion
CREATE TABLE IF NOT EXISTS `chef_promotion` (
  `id_chef` int(11) NOT NULL AUTO_INCREMENT,
  `idetudiant` int(11) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_nomination` date NOT NULL,
  `est_actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id_chef`),
  UNIQUE KEY `idx_chef_unique_actif` (`promotion_idpromotion`, `annee_acad_idannee_acad`, `est_actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE IF NOT EXISTS periodes_essai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_nom VARCHAR(255) NOT NULL,
    client_email VARCHAR(255),
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut ENUM('Actif', 'Expiré', 'Suspendu') DEFAULT 'Actif',
    nombre_connexions INT DEFAULT 0,
    derniere_connexion DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insérer un exemple de période d'essai (30 jours)
INSERT INTO periodes_essai (client_nom, client_email, date_debut, date_fin, statut) 
VALUES ('Client Test', 'client@test.com', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'Actif');






