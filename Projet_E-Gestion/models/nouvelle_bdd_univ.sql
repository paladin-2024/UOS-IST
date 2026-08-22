-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 04 juil. 2025 à 17:13
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `egestion`
--

-- --------------------------------------------------------

--
-- Structure de la table `affectation_agent`
--

CREATE TABLE `affectation_agent` (
  `idaffectation` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idStructure` int(11) NOT NULL,
  `idService` int(11) NOT NULL,
  `date_affectation` date NOT NULL,
  `reference_decision` varchar(255) DEFAULT NULL,
  `est_actuelle` tinyint(1) DEFAULT 1,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `affectation_frais`
--

CREATE TABLE `affectation_frais` (
  `id` int(11) NOT NULL,
  `frais_id` int(11) NOT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `etudiant_id` int(11) DEFAULT NULL,
  `matricule_etudiant` varchar(245) DEFAULT NULL,
  `montant_specifique` decimal(15,2) DEFAULT NULL COMMENT 'Si différent du montant standard',
  `devise` varchar(10) DEFAULT NULL,
  `date_affectation` datetime DEFAULT current_timestamp(),
  `date_echeance` date DEFAULT NULL,
  `motif_specifique` text DEFAULT NULL,
  `est_exempte` tinyint(1) DEFAULT 0 COMMENT 'Exemption exceptionnelle du frais',
  `motif_exemption` text DEFAULT NULL,
  `reference_decision` varchar(100) DEFAULT NULL,
  `document_justificatif` varchar(255) DEFAULT NULL,
  `statut_paiement` enum('Non payé','Partiel','Complet') DEFAULT 'Non payé',
  `montant_paye` decimal(15,2) DEFAULT 0.00,
  `montant_restant` decimal(15,2) DEFAULT NULL,
  `date_dernier_paiement` datetime DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `agent`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `agent_section`
--

CREATE TABLE `agent_section` (
  `idagent_section` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idsection` int(11) NOT NULL,
  `dateAffectation` datetime DEFAULT current_timestamp(),
  `estPrincipal` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `alertes_financieres`
--

CREATE TABLE `alertes_financieres` (
  `id` int(11) NOT NULL,
  `type` enum('Budget','Caisse','Échéance','Rapprochement','Validation','Autre') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `severite` enum('Info','Warning','Danger','Success') NOT NULL DEFAULT 'Info',
  `lien` varchar(255) DEFAULT NULL,
  `est_lu` tinyint(1) DEFAULT 0,
  `date_lecture` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `destinataire_id` int(11) DEFAULT NULL,
  `role_destinataire` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `annee_acad`
--

CREATE TABLE `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `est_active` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `archive_numerique`
--

CREATE TABLE `archive_numerique` (
  `idarchive` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type_document` varchar(100) NOT NULL,
  `annee` int(11) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `mots_cles` text DEFAULT NULL,
  `fichier` varchar(255) NOT NULL,
  `niveau_confidentialite` enum('Public','Restreint','Confidentiel') NOT NULL DEFAULT 'Public',
  `idUser` int(11) NOT NULL,
  `date_archivage` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `autorisations_paiement`
--

CREATE TABLE `autorisations_paiement` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `beneficiaire_id` int(11) NOT NULL,
  `motif` text NOT NULL,
  `etat_besoin_id` int(11) DEFAULT NULL,
  `engagement_id` int(11) DEFAULT NULL,
  `date_autorisation` date NOT NULL,
  `date_validite` date DEFAULT NULL,
  `mode_paiement` enum('Espèces','Chèque','Virement','Mobile Money','Carte bancaire','Autre') NOT NULL,
  `compte_source_id` int(11) DEFAULT NULL,
  `caisse_source_id` int(11) DEFAULT NULL,
  `statut` enum('En attente','Approuvée','Rejetée','Exécutée','Annulée') NOT NULL DEFAULT 'En attente',
  `commentaire` text DEFAULT NULL,
  `pieces_jointes` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `idApprobateur` int(11) DEFAULT NULL,
  `date_execution` datetime DEFAULT NULL,
  `idExecuteur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `autorisation_depot`
--

CREATE TABLE `autorisation_depot` (
  `id_autorisation` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_depot` int(11) NOT NULL,
  `peut_consulter` tinyint(1) NOT NULL DEFAULT 1,
  `peut_modifier` tinyint(1) NOT NULL DEFAULT 0,
  `peut_valider` tinyint(1) NOT NULL DEFAULT 0,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `autorisation_labo`
--

CREATE TABLE `autorisation_labo` (
  `idautorisation` int(11) NOT NULL,
  `idlabo` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `niveau_autorisation` enum('Admin','Utilisateur') NOT NULL DEFAULT 'Utilisateur',
  `est_active` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `avances_salaires`
--

CREATE TABLE `avances_salaires` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `date_demande` date NOT NULL,
  `motif` text DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL,
  `date_remboursement_prevue` date NOT NULL,
  `mode_remboursement` enum('Prélèvement salaire','Échelonnement','Paiement direct') NOT NULL,
  `nombre_echeances` int(11) DEFAULT NULL,
  `montant_echeance` decimal(15,2) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `statut` enum('En attente','Validée','Payée','En remboursement','Remboursée','Rejetée','Annulée') NOT NULL DEFAULT 'En attente',
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  `idValidateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `banque`
--

CREATE TABLE `banque` (
  `id_banque` int(11) NOT NULL,
  `code_banque` varchar(10) NOT NULL,
  `nom_banque` varchar(100) NOT NULL,
  `adresse` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `site_web` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `beneficiaires`
--

CREATE TABLE `beneficiaires` (
  `id` int(11) NOT NULL,
  `type` enum('Étudiant','Agent','Fournisseur','Autre') NOT NULL,
  `ref_id` int(11) DEFAULT NULL COMMENT 'ID de l''étudiant, agent ou fournisseur',
  `nom` varchar(255) DEFAULT NULL COMMENT 'Pour les bénéficiaires de type Autre',
  `adresse` text DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `numero_compte` varchar(100) DEFAULT NULL,
  `banque` varchar(255) DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `budget`
--

CREATE TABLE `budget` (
  `id` int(11) NOT NULL,
  `exercice_id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `montant_prevu` decimal(15,2) NOT NULL,
  `montant_revise` decimal(15,2) DEFAULT NULL,
  `montant_engage` decimal(15,2) DEFAULT 0.00,
  `montant_realise` decimal(15,2) DEFAULT 0.00,
  `disponible` decimal(15,2) DEFAULT 0.00,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `bureau_jury_deliberation`
--

CREATE TABLE `bureau_jury_deliberation` (
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

-- --------------------------------------------------------

--
-- Structure de la table `bureau_jury_promotion`
--

CREATE TABLE `bureau_jury_promotion` (
  `id` int(11) NOT NULL,
  `idbureau` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `date_association` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `caisses`
--

CREATE TABLE `caisses` (
  `id` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `solde_initial` decimal(15,2) DEFAULT 0.00,
  `solde_actuel` decimal(15,2) DEFAULT 0.00,
  `plafond_caisse` decimal(15,2) DEFAULT NULL,
  `idAgent_responsable` int(11) NOT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories_budget`
--

CREATE TABLE `categories_budget` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('Recette','Dépense') NOT NULL,
  `compte_comptable_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `niveau` int(11) NOT NULL DEFAULT 1,
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories_doc`
--

CREATE TABLE `categories_doc` (
  `id_categorie` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `idStructure` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories_frais`
--

CREATE TABLE `categories_frais` (
  `id` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `est_obligatoire` tinyint(1) DEFAULT 1,
  `est_echelonnable` tinyint(1) DEFAULT 0,
  `est_remboursable` tinyint(1) DEFAULT 0,
  `compte_comptable` varchar(50) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categorie_indicateur`
--

CREATE TABLE `categorie_indicateur` (
  `idCategorie` int(11) NOT NULL,
  `nomCategorie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chapitre_plan`
--

CREATE TABLE `chapitre_plan` (
  `idchapitre_plan` int(11) NOT NULL,
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
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `charges_enseignement`
--

CREATE TABLE `charges_enseignement` (
  `id` int(11) NOT NULL,
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
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chef_promotion`
--

CREATE TABLE `chef_promotion` (
  `id_chef` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_nomination` date NOT NULL,
  `est_actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `comptes_bancaires`
--

CREATE TABLE `comptes_bancaires` (
  `id` int(11) NOT NULL,
  `nom_banque` varchar(255) NOT NULL,
  `numero_compte` varchar(50) NOT NULL,
  `intitule_compte` varchar(255) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `solde_initial` decimal(15,2) DEFAULT 0.00,
  `solde_actuel` decimal(15,2) DEFAULT 0.00,
  `date_ouverture` date DEFAULT NULL,
  `contact_banque` varchar(100) DEFAULT NULL,
  `telephone_banque` varchar(50) DEFAULT NULL,
  `email_banque` varchar(100) DEFAULT NULL,
  `adresse_banque` text DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `compte_comptable`
--

CREATE TABLE `compte_comptable` (
  `id_compte` int(11) NOT NULL,
  `numero_compte` varchar(20) NOT NULL,
  `intitule_compte` varchar(255) NOT NULL,
  `classe_compte` int(11) NOT NULL,
  `compte_parent` int(11) DEFAULT NULL,
  `type_compte` enum('Actif','Passif','Charge','Produit') NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `configuration_deliberation`
--

CREATE TABLE `configuration_deliberation` (
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
  `calculer_moyenne_avec_notes_vides` tinyint(1) DEFAULT 0 COMMENT 'Calculer la moyenne même si certaines notes sont vides',
  `heures_par_credit` int(11) DEFAULT 25 COMMENT 'Nombre d''heures équivalent à 1 crédit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `configuration_moyenne`
--

CREATE TABLE `configuration_moyenne` (
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

CREATE TABLE `configuration_universite` (
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
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `credit_heure` int(11) NOT NULL DEFAULT 25
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `config_finance`
--

CREATE TABLE `config_finance` (
  `id` int(11) NOT NULL,
  `devise_principale` varchar(10) NOT NULL DEFAULT 'USD',
  `devise_secondaire` varchar(10) DEFAULT 'CDF',
  `taux_change` decimal(15,6) DEFAULT 2000.000000,
  `date_mise_a_jour_taux` datetime DEFAULT current_timestamp(),
  `annee_fiscale_debut` date DEFAULT NULL,
  `annee_fiscale_fin` date DEFAULT NULL,
  `format_facture` varchar(100) DEFAULT 'INV-{YEAR}-{NUM}',
  `numero_facture_suivant` int(11) DEFAULT 1,
  `logo_facture` varchar(255) DEFAULT NULL,
  `signature_comptable` varchar(255) DEFAULT NULL,
  `signature_finance` varchar(255) DEFAULT NULL,
  `termes_paiement` text DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contrat_agent`
--

CREATE TABLE `contrat_agent` (
  `idContrat_agent` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `typeContrat` varchar(45) DEFAULT NULL,
  `dateDebut` date DEFAULT NULL,
  `dateFin` date DEFAULT NULL,
  `fonction` varchar(145) DEFAULT NULL,
  `salaireDeBase` decimal(14,2) DEFAULT NULL,
  `transport` decimal(14,2) DEFAULT NULL,
  `logement` decimal(14,2) DEFAULT NULL,
  `anciennete` decimal(14,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Agent_idAgent` int(11) NOT NULL,
  `Service_idService` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `conversation`
--

CREATE TABLE `conversation` (
  `idconversation` int(11) NOT NULL,
  `sujets_idsujets` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cotes_grille`
--

CREATE TABLE `cotes_grille` (
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

-- --------------------------------------------------------

--
-- Structure de la table `couriels_recu`
--

CREATE TABLE `couriels_recu` (
  `idcouriels_recu` int(11) NOT NULL,
  `dateArrive` date DEFAULT NULL,
  `provenance` varchar(145) DEFAULT NULL,
  `depositaire` varchar(145) DEFAULT NULL,
  `objet` text DEFAULT NULL,
  `resumeCouriel` text DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `userConcerne` int(11) DEFAULT NULL,
  `Service_idService` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `courrier_academique`
--

CREATE TABLE `courrier_academique` (
  `idcourrier` int(11) NOT NULL,
  `reference` varchar(100) NOT NULL,
  `type` enum('Entrant','Sortant') NOT NULL,
  `objet` varchar(255) NOT NULL,
  `expediteur` varchar(255) DEFAULT NULL,
  `destinataire` varchar(255) DEFAULT NULL,
  `date_courrier` date NOT NULL,
  `date_reception` date DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `deadline_assignment`
--

CREATE TABLE `deadline_assignment` (
  `iddeadline` int(11) NOT NULL,
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
  `jours_rappel` int(11) DEFAULT 7 COMMENT 'Rappel X jours avant'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `deliberation`
--

CREATE TABLE `deliberation` (
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

CREATE TABLE `demande_conge` (
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

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

CREATE TABLE `depenses` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `engagement_id` int(11) DEFAULT NULL,
  `categorie_budget_id` int(11) NOT NULL,
  `exercice_id` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `taux_change` decimal(15,6) DEFAULT NULL,
  `beneficiaire` varchar(255) NOT NULL,
  `motif` text NOT NULL,
  `date_depense` datetime NOT NULL,
  `facture_reference` varchar(100) DEFAULT NULL,
  `facture_fichier` varchar(255) DEFAULT NULL,
  `pieces_justificatives` text DEFAULT NULL,
  `statut` enum('En attente','Validée','Payée','Rejetée','Annulée') NOT NULL DEFAULT 'En attente',
  `date_validation` datetime DEFAULT NULL,
  `validateur_id` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depot_memoire`
--

CREATE TABLE `depot_memoire` (
  `iddepot_memoire` int(11) NOT NULL,
  `dateDepot` date DEFAULT NULL,
  `fichier` varchar(145) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `sujets_idsujets` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depot_rapport`
--

CREATE TABLE `depot_rapport` (
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

-- --------------------------------------------------------

--
-- Structure de la table `details_etats_besoin`
--

CREATE TABLE `details_etats_besoin` (
  `id` int(11) NOT NULL,
  `etat_besoin_id` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantite` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unite_mesure` varchar(50) DEFAULT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `details_etats_paiement`
--

CREATE TABLE `details_etats_paiement` (
  `id` int(11) NOT NULL,
  `etat_paiement_id` int(11) NOT NULL,
  `paie_id` int(11) DEFAULT NULL COMMENT 'ID de la paie mensuelle pour permanents',
  `ordre_paiement_id` int(11) DEFAULT NULL COMMENT 'ID de l''ordre de paiement pour visiteurs',
  `idAgent` int(11) NOT NULL,
  `montant_brut` decimal(15,2) NOT NULL,
  `montant_retenues` decimal(15,2) NOT NULL,
  `montant_net` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `details_paiement_visiteurs`
--

CREATE TABLE `details_paiement_visiteurs` (
  `id` int(11) NOT NULL,
  `ordre_paiement_id` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `nb_heures_cm` decimal(10,2) DEFAULT 0.00,
  `nb_heures_td` decimal(10,2) DEFAULT 0.00,
  `nb_heures_tp` decimal(10,2) DEFAULT 0.00,
  `nb_heures_evaluation` decimal(10,2) DEFAULT 0.00,
  `montant` decimal(15,2) NOT NULL,
  `commentaire` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_rapport_financier`
--

CREATE TABLE `detail_rapport_financier` (
  `id_detail_rapport` int(11) NOT NULL,
  `id_rapport` int(11) NOT NULL,
  `type` enum('Recette','Dépense') NOT NULL,
  `id_compte_comptable` int(11) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `montant` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(50) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dette_bulletin`
--

CREATE TABLE `dette_bulletin` (
  `id_bulletin` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_generation` datetime DEFAULT current_timestamp(),
  `chemin_fichier` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dette_etudiant`
--

CREATE TABLE `dette_etudiant` (
  `id_dette` int(11) NOT NULL,
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
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur qui a créé l''enregistrement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dette_evaluation`
--

CREATE TABLE `dette_evaluation` (
  `id_evaluation` int(11) NOT NULL,
  `id_dette` int(11) NOT NULL,
  `type_evaluation` enum('CC','EX','TP','TD') NOT NULL,
  `note` decimal(5,2) NOT NULL,
  `date_evaluation` date NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_encodage` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dette_historique`
--

CREATE TABLE `dette_historique` (
  `id_historique` int(11) NOT NULL,
  `id_dette` int(11) NOT NULL,
  `action` enum('Creation','Modification','Validation','Annulation') NOT NULL,
  `details` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `devoirs`
--

CREATE TABLE `devoirs` (
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

-- --------------------------------------------------------

--
-- Structure de la table `documents_obligatoires`
--

CREATE TABLE `documents_obligatoires` (
  `id` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cycle` enum('Premier','Deuxieme','Troisieme','Tous') NOT NULL DEFAULT 'Tous',
  `est_obligatoire` tinyint(1) DEFAULT 1,
  `delai_jours` int(11) DEFAULT NULL COMMENT 'Délai en jours pour fournir le document après inscription',
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `documents_prive`
--

CREATE TABLE `documents_prive` (
  `id_document` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `id_categorie` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `date_ajout` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `documents_public`
--

CREATE TABLE `documents_public` (
  `id_document` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `id_categorie` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `date_ajout` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `document_agent`
--

CREATE TABLE `document_agent` (
  `idDocument_agent` int(11) NOT NULL,
  `titre` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fichier` varchar(145) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Agent_idAgent` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dossier_famille`
--

CREATE TABLE `dossier_famille` (
  `idDossier_famille` int(11) NOT NULL,
  `noms` varchar(245) DEFAULT NULL,
  `sexe` varchar(45) DEFAULT NULL,
  `dateNaissance` date DEFAULT NULL,
  `lieuNaissance` varchar(45) DEFAULT NULL,
  `typeLiaison` varchar(145) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Agent_idAgent` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dossier_scientifique`
--

CREATE TABLE `dossier_scientifique` (
  `iddossier` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `type_document` enum('CV','Publication','Diplôme','Certificat','Autre') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fichier` varchar(255) NOT NULL,
  `date_document` date DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `droits_acces_finances`
--

CREATE TABLE `droits_acces_finances` (
  `id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `type` enum('Caisse','Banque','Budget','Validation','Rapports') NOT NULL,
  `niveau` enum('Lecture','Écriture','Validation','Administration') NOT NULL DEFAULT 'Lecture',
  `entite_id` int(11) DEFAULT NULL COMMENT 'ID de la caisse, banque, etc. concernée',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idCreateur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecard_access_keys`
--

CREATE TABLE `ecard_access_keys` (
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

CREATE TABLE `ecard_verification_log` (
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

CREATE TABLE `echanges_taches` (
  `idechange` int(11) NOT NULL,
  `dateEchange` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `fichierJoint` varchar(145) DEFAULT NULL,
  `taches_idtaches` int(11) NOT NULL,
  `type_auteur` enum('Directeur','Encadreur','Etudiant') NOT NULL,
  `idAuteur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `echange_chapitre`
--

CREATE TABLE `echange_chapitre` (
  `idechange_chapitre` int(11) NOT NULL,
  `idchapitre_plan` int(11) NOT NULL,
  `type_auteur` enum('Directeur','Encadreur','Etudiant') NOT NULL,
  `idAuteur` int(11) NOT NULL,
  `commentaire` text NOT NULL,
  `fichier_joint` varchar(255) DEFAULT NULL,
  `type_fichier` varchar(50) DEFAULT NULL,
  `statut_lecture` enum('Non lu','Lu','Traité') NOT NULL DEFAULT 'Non lu',
  `date_echange` datetime NOT NULL DEFAULT current_timestamp(),
  `reponse_a` int(11) DEFAULT NULL COMMENT 'ID de l''échange parent si c''est une réponse'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `echelonnement_paiement`
--

CREATE TABLE `echelonnement_paiement` (
  `id` int(11) NOT NULL,
  `affectation_id` int(11) NOT NULL,
  `numero_tranche` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `pourcentage` decimal(5,2) DEFAULT NULL,
  `date_echeance` date DEFAULT NULL,
  `est_requis_inscription` tinyint(1) DEFAULT 0,
  `est_requis_examens` tinyint(1) DEFAULT 0,
  `est_requis_deliberation` tinyint(1) DEFAULT 0,
  `statut_paiement` enum('Non payé','Partiel','Complet') DEFAULT 'Non payé',
  `montant_paye` decimal(15,2) DEFAULT 0.00,
  `montant_restant` decimal(15,2) DEFAULT NULL,
  `date_dernier_paiement` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `echelonnement_paiement_etudiant`
--

CREATE TABLE `echelonnement_paiement_etudiant` (
  `id` int(11) NOT NULL,
  `echelonnement_id` int(11) NOT NULL COMMENT 'Référence à l''échelonnement principal',
  `affectation_id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `matricule_etudiant` varchar(245) NOT NULL,
  `numero_tranche` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `date_echeance` date DEFAULT NULL,
  `statut_paiement` enum('Non payé','Partiel','Complet') DEFAULT 'Non payé',
  `montant_paye` decimal(15,2) DEFAULT 0.00,
  `montant_restant` decimal(15,2) DEFAULT NULL,
  `date_dernier_paiement` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecriture_comptable`
--

CREATE TABLE `ecriture_comptable` (
  `id_ecriture` int(11) NOT NULL,
  `numero_ecriture` varchar(20) NOT NULL,
  `date_ecriture` date NOT NULL,
  `id_journal` int(11) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `piece_reference` varchar(50) DEFAULT NULL,
  `id_facture_client` int(11) DEFAULT NULL,
  `id_facture_fournisseur` int(11) DEFAULT NULL,
  `id_paiement_client` int(11) DEFAULT NULL,
  `id_paiement_fournisseur` int(11) DEFAULT NULL,
  `id_operation_caisse` int(11) DEFAULT NULL,
  `id_operation_bancaire` int(11) DEFAULT NULL,
  `est_validee` tinyint(1) NOT NULL DEFAULT 0,
  `id_exercice` int(11) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecue`
--

CREATE TABLE `ecue` (
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

CREATE TABLE `ecue_notes_verrouillage` (
  `id` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `idsession` int(11) NOT NULL,
  `idannee_acad` int(11) NOT NULL,
  `date_verrouillage` datetime NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `enseignant_ecue`
--

CREATE TABLE `enseignant_ecue` (
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

CREATE TABLE `enseignant_section` (
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

CREATE TABLE `enseignant_specialisation` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idSpecialisation` int(11) NOT NULL,
  `dateAffectation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `enseignant_uniterecherche`
--

CREATE TABLE `enseignant_uniterecherche` (
  `idAffectation` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `idSpecialisation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etats_besoin`
--

CREATE TABLE `etats_besoin` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `montant_estime` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `priorite` enum('Basse','Normale','Haute','Urgente') NOT NULL DEFAULT 'Normale',
  `date_souhaitee` date DEFAULT NULL,
  `justification` text DEFAULT NULL,
  `piece_jointe` varchar(255) DEFAULT NULL,
  `statut` enum('Brouillon','Soumis','En révision','Approuvé','Rejeté','Annulé','En cours d''exécution','Terminé') NOT NULL DEFAULT 'Brouillon',
  `date_soumission` datetime DEFAULT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `date_rejet` datetime DEFAULT NULL,
  `motif_rejet` text DEFAULT NULL,
  `categorie_budget_id` int(11) DEFAULT NULL,
  `exercice_id` int(11) NOT NULL,
  `demandeur_id` int(11) NOT NULL,
  `approbateur_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiant`
--

CREATE TABLE `etudiant` (
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
  `idUser` int(11) NOT NULL,
  `est_actif` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indique si l''inscription est active pour l''année en cours',
  `dossier_complete` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indique si l''étudiant a complété son dossier'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiants_cards`
--

CREATE TABLE `etudiants_cards` (
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
-- Structure de la table `etudiants_palmares_archives`
--

CREATE TABLE `etudiants_palmares_archives` (
  `idetudiant_palmares` int(11) NOT NULL,
  `idpalmares` int(11) NOT NULL,
  `matricule` varchar(50) NOT NULL,
  `nom_complet` varchar(255) NOT NULL,
  `pourcentage` decimal(5,2) DEFAULT NULL,
  `decision` varchar(50) NOT NULL,
  `session` varchar(50) NOT NULL DEFAULT 'Première session'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiant_documents`
--

CREATE TABLE `etudiant_documents` (
  `id` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `matricule` varchar(50) NOT NULL,
  `type_document` varchar(50) NOT NULL,
  `document_obligatoire_id` int(11) DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `date_ajout` datetime NOT NULL DEFAULT current_timestamp(),
  `annee_acad_id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `statut` enum('Valide','En attente de validation','Rejeté') DEFAULT 'En attente de validation',
  `commentaire_validation` text DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiant_documents_historique`
--

CREATE TABLE `etudiant_documents_historique` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `statut_precedent` enum('Valide','En attente de validation','Rejeté') NOT NULL,
  `nouveau_statut` enum('Valide','En attente de validation','Rejeté') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiant_en_ordre`
--

CREATE TABLE `etudiant_en_ordre` (
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
-- Structure de la table `etudiant_historique`
--

CREATE TABLE `etudiant_historique` (
  `id` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `idannee_acad` int(11) NOT NULL,
  `date_inscription` date NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

CREATE TABLE `evaluations` (
  `idevaluation` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_evaluation` date NOT NULL,
  `idECUE` int(11) NOT NULL,
  `idType` int(11) NOT NULL,
  `ponderation` decimal(5,2) NOT NULL DEFAULT 1.00,
  `note_max` decimal(5,2) NOT NULL DEFAULT 20.00,
  `session_idsession` int(11) NOT NULL,
  `est_visible` tinyint(1) DEFAULT 0,
  `annee_acad_id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exercices_budgetaires`
--

CREATE TABLE `exercices_budgetaires` (
  `id` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `est_actif` tinyint(1) DEFAULT 0,
  `est_cloture` tinyint(1) DEFAULT 0,
  `date_cloture` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exercice_comptable`
--

CREATE TABLE `exercice_comptable` (
  `id_exercice` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `est_cloture` tinyint(1) NOT NULL DEFAULT 0,
  `date_cloture` datetime DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fiche_paie`
--

CREATE TABLE `fiche_paie` (
  `idFiche_paie` int(11) NOT NULL,
  `mois_paie` varchar(45) DEFAULT NULL,
  `annee_paie` varchar(45) DEFAULT NULL,
  `salaireBase` decimal(14,2) DEFAULT NULL,
  `transport` decimal(14,2) DEFAULT NULL,
  `logement` decimal(14,2) DEFAULT NULL,
  `anciennete` decimal(14,2) DEFAULT NULL,
  `IPR` decimal(14,2) DEFAULT NULL,
  `INSS` decimal(14,2) DEFAULT NULL,
  `Autres_retenu` decimal(14,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Contrat_agent_idContrat_agent` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `formation_agent`
--

CREATE TABLE `formation_agent` (
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

-- --------------------------------------------------------

--
-- Structure de la table `frais`
--

CREATE TABLE `frais` (
  `id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `annee_acad_id` int(11) NOT NULL,
  `cycle` enum('Licence','Master','Doctorat','Tous') NOT NULL DEFAULT 'Tous',
  `niveau` varchar(20) DEFAULT NULL COMMENT 'L1, L2, L3, M1, M2, etc.',
  `est_obligatoire` tinyint(1) NOT NULL DEFAULT 1,
  `est_echelonnable` tinyint(1) DEFAULT 0,
  `nb_tranches_max` int(11) DEFAULT 1,
  `date_echeance_globale` date DEFAULT NULL,
  `est_requis_inscription` tinyint(1) DEFAULT 1,
  `est_requis_examens` tinyint(1) DEFAULT 0,
  `est_requis_deliberation` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grade`
--

CREATE TABLE `grade` (
  `idgrade` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type_agent` enum('Enseignant','Administratif','Recherche') DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grille_salaires`
--

CREATE TABLE `grille_salaires` (
  `id` int(11) NOT NULL,
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
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique_changement_decision`
--

CREATE TABLE `historique_changement_decision` (
  `id_historique` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `ancienne_decision` varchar(50) DEFAULT NULL,
  `nouvelle_decision` varchar(50) DEFAULT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `annee_acad_id` int(11) DEFAULT NULL,
  `nb_dettes_supprimees` int(11) DEFAULT 0,
  `date_changement` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique_cotes`
--

CREATE TABLE `historique_cotes` (
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

CREATE TABLE `historique_grade` (
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

CREATE TABLE `historique_notes` (
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
-- Structure de la table `historique_soldes`
--

CREATE TABLE `historique_soldes` (
  `id` int(11) NOT NULL,
  `type` enum('Caisse','Banque') NOT NULL,
  `source_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `solde_ouverture` decimal(15,2) NOT NULL,
  `entrees` decimal(15,2) DEFAULT 0.00,
  `sorties` decimal(15,2) DEFAULT 0.00,
  `solde_fermeture` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `est_ajuste` tinyint(1) DEFAULT 0,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique_visites`
--

CREATE TABLE `historique_visites` (
  `id` int(11) NOT NULL,
  `idVisite` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `ancien_statut` varchar(50) DEFAULT NULL,
  `nouveau_statut` varchar(50) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_action` timestamp NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `horaires_cours`
--

CREATE TABLE `horaires_cours` (
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

-- --------------------------------------------------------

--
-- Structure de la table `intervention_jury`
--

CREATE TABLE `intervention_jury` (
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

-- --------------------------------------------------------

--
-- Structure de la table `journal_activites`
--

CREATE TABLE `journal_activites` (
  `id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL COMMENT 'Type d''utilisateur: etudiant, enseignant, admin',
  `user_id` int(11) NOT NULL COMMENT 'ID de l''utilisateur',
  `type_activite` varchar(50) NOT NULL COMMENT 'Type d''activité: devoir, cours, etc.',
  `id_element` int(11) NOT NULL COMMENT 'ID de l''élément concerné',
  `description` text DEFAULT NULL COMMENT 'Description de l''activité',
  `date_activite` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `journal_comptable`
--

CREATE TABLE `journal_comptable` (
  `id_journal` int(11) NOT NULL,
  `code_journal` varchar(10) NOT NULL,
  `libelle_journal` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jury`
--

CREATE TABLE `jury` (
  `idjury` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `id_president` int(11) NOT NULL,
  `id_secretaire` int(11) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jury_membre_autorisations`
--

CREATE TABLE `jury_membre_autorisations` (
  `id_autorisation` int(11) NOT NULL,
  `idbureau` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_autorisation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jury_soutenance`
--

CREATE TABLE `jury_soutenance` (
  `idjury` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `role` enum('Président','Secrétaire','Membre','Lecteur 1','Lecteur 2') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `laboratoire`
--

CREATE TABLE `laboratoire` (
  `idlabo` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `responsable_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `annee_acad_id` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `ref_latitude` decimal(10,8) DEFAULT NULL,
  `ref_longitude` decimal(11,8) DEFAULT NULL,
  `geo_verification_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lecteurs_soutenance`
--

CREATE TABLE `lecteurs_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `est_premier_lecteur` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_budget`
--

CREATE TABLE `ligne_budget` (
  `id_ligne_budget` int(11) NOT NULL,
  `id_budget` int(11) NOT NULL,
  `id_compte_comptable` int(11) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `montant_prevu` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_realise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `type` enum('Recette','Dépense') NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_ecriture_comptable`
--

CREATE TABLE `ligne_ecriture_comptable` (
  `id_ligne_ecriture` int(11) NOT NULL,
  `id_ecriture` int(11) NOT NULL,
  `id_compte` int(11) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `log_operation`
--

CREATE TABLE `log_operation` (
  `id_log` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `date_operation` datetime NOT NULL DEFAULT current_timestamp(),
  `type_operation` varchar(50) NOT NULL,
  `table_concernee` varchar(50) NOT NULL,
  `id_enregistrement` int(11) NOT NULL,
  `description` text NOT NULL,
  `adresse_ip` varchar(50) DEFAULT NULL,
  `navigateur` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `membre_bureau_jury`
--

CREATE TABLE `membre_bureau_jury` (
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

CREATE TABLE `mention_speciale` (
  `idmention` int(11) NOT NULL,
  `type_mention` enum('Félicitations','Encouragements','Avertissement','Blâme') NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `iddeliberation` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `message`
--

CREATE TABLE `message` (
  `idmessage` int(11) NOT NULL,
  `idconversation` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `type_expediteur` enum('Etudiant','Directeur','Encadreur') NOT NULL,
  `id_expediteur` int(11) NOT NULL,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `lu` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mode_paiement`
--

CREATE TABLE `mode_paiement` (
  `id_mode_paiement` int(11) NOT NULL,
  `code_mode` varchar(10) NOT NULL,
  `libelle_mode` varchar(50) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `moyenne_annuelle`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `moyenne_semestre`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `moyenne_ue`
--

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

-- --------------------------------------------------------

--
-- Structure de la table `notes_soutenance`
--

CREATE TABLE `notes_soutenance` (
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

-- --------------------------------------------------------

--
-- Structure de la table `notifications_documents`
--

CREATE TABLE `notifications_documents` (
  `id` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `objet` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notification_plan`
--

CREATE TABLE `notification_plan` (
  `idnotification` int(11) NOT NULL,
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
  `date_lecture` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `objectifs_financiers`
--

CREATE TABLE `objectifs_financiers` (
  `id` int(11) NOT NULL,
  `planification_id` int(11) NOT NULL,
  `categorie_budget_id` int(11) DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('Recette','Dépense','Investissement','Économie') NOT NULL,
  `annee` int(11) NOT NULL,
  `montant_objectif` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `priorite` enum('Basse','Moyenne','Haute','Critique') NOT NULL DEFAULT 'Moyenne',
  `indicateurs_performance` text DEFAULT NULL,
  `mesures_atteinte` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `operation_bancaire`
--

CREATE TABLE `operation_bancaire` (
  `id_operation` int(11) NOT NULL,
  `numero_operation` varchar(20) NOT NULL,
  `date_operation` date NOT NULL,
  `id_compte_bancaire` int(11) NOT NULL,
  `type_operation` enum('Entrée','Sortie','Transfert') NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `id_compte_comptable` int(11) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `id_budget` int(11) DEFAULT NULL,
  `id_ligne_budget` int(11) DEFAULT NULL,
  `id_caisse` int(11) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `operation_caisse`
--

CREATE TABLE `operation_caisse` (
  `id_operation` int(11) NOT NULL,
  `numero_operation` varchar(20) NOT NULL,
  `date_operation` date NOT NULL,
  `id_caisse` int(11) NOT NULL,
  `type_operation` enum('Entrée','Sortie','Transfert') NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `id_compte_comptable` int(11) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `id_budget` int(11) DEFAULT NULL,
  `id_ligne_budget` int(11) DEFAULT NULL,
  `id_compte_bancaire` int(11) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ordres_paiement_visiteurs`
--

CREATE TABLE `ordres_paiement_visiteurs` (
  `id` int(11) NOT NULL,
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
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `orientation`
--

CREATE TABLE `orientation` (
  `idorientation` int(11) NOT NULL,
  `designationOrientation` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp(),
  `section_idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement`
--

CREATE TABLE `paiement` (
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

-- --------------------------------------------------------

--
-- Structure de la table `paiements_frais`
--

CREATE TABLE `paiements_frais` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `etudiant_id` int(11) DEFAULT NULL,
  `matricule_etudiant` varchar(245) DEFAULT NULL,
  `affectation_id` int(11) NOT NULL,
  `echelonnement_id` int(11) DEFAULT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `mode_paiement` enum('Espèces','Chèque','Virement','Mobile Money','Carte bancaire','Autre') NOT NULL,
  `reference_externe` varchar(100) DEFAULT NULL,
  `date_valeur` datetime DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL,
  `recu_numero` varchar(50) DEFAULT NULL,
  `recu_fichier` varchar(255) DEFAULT NULL,
  `est_confirme` tinyint(1) DEFAULT 0,
  `date_confirmation` datetime DEFAULT NULL,
  `idConfirmateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiements_tranches`
--

CREATE TABLE `paiements_tranches` (
  `id` int(11) NOT NULL,
  `echelonnement_id` int(11) NOT NULL,
  `paiement_id` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paies_mensuelles`
--

CREATE TABLE `paies_mensuelles` (
  `id` int(11) NOT NULL,
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
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `palmares_archive`
--

CREATE TABLE `palmares_archive` (
  `id_palmares` int(11) NOT NULL,
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
  `session_idsession` int(11) DEFAULT NULL COMMENT 'Référence optionnelle à une session existante'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `palmares_archives`
--

CREATE TABLE `palmares_archives` (
  `idpalmares` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fichier_scanne` varchar(255) DEFAULT NULL,
  `annee_academique` varchar(50) NOT NULL,
  `section` varchar(100) NOT NULL,
  `promotion` varchar(100) NOT NULL,
  `session` varchar(50) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `palmares_etudiant`
--

CREATE TABLE `palmares_etudiant` (
  `id_palmares_etudiant` int(11) NOT NULL,
  `id_palmares` int(11) NOT NULL,
  `nom_complet` varchar(255) NOT NULL COMMENT 'Nom complet de l étudiant',
  `pourcentage` decimal(5,2) NOT NULL COMMENT 'Pourcentage obtenu',
  `mention` enum('Passable','Assez Bien','Bien','Très Bien','Excellent','Distinction','Grande Distinction','La Plus Grande Distinction') DEFAULT NULL,
  `rang` int(11) DEFAULT NULL COMMENT 'Position dans le classement',
  `matricule` varchar(100) DEFAULT NULL COMMENT 'Matricule si disponible',
  `idetudiant` int(11) DEFAULT NULL COMMENT 'Référence optionnelle à un étudiant existant',
  `commentaire` text DEFAULT NULL,
  `credit_obtenu` int(11) DEFAULT NULL,
  `credit_total` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `palmares_historique`
--

CREATE TABLE `palmares_historique` (
  `id_historique` int(11) NOT NULL,
  `id_palmares` int(11) NOT NULL,
  `action` enum('Creation','Modification','Suppression') NOT NULL,
  `details` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parties_cours`
--

CREATE TABLE `parties_cours` (
  `idpartie` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ordre` int(11) NOT NULL DEFAULT 1,
  `idECUE` int(11) NOT NULL,
  `estVisible` tinyint(1) DEFAULT 1,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `periodes_essai`
--

CREATE TABLE `periodes_essai` (
  `id` int(11) NOT NULL,
  `client_nom` varchar(255) NOT NULL,
  `client_email` varchar(255) DEFAULT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `statut` enum('Actif','Expiré','Suspendu') DEFAULT 'Actif',
  `nombre_connexions` int(11) DEFAULT 0,
  `derniere_connexion` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `plan_comptable`
--

CREATE TABLE `plan_comptable` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `type` enum('Actif','Passif','Charge','Produit') NOT NULL,
  `niveau` int(11) NOT NULL DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `est_analytique` tinyint(1) DEFAULT 0,
  `est_budgetaire` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `plan_travail`
--

CREATE TABLE `plan_travail` (
  `idplan_travail` int(11) NOT NULL,
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
  `idUser` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a créé/modifié'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `plan_validation_history`
--

CREATE TABLE `plan_validation_history` (
  `id` int(11) NOT NULL,
  `idplan_travail` int(11) NOT NULL,
  `statut` enum('En attente','Validé','Rejeté','Modifié') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL COMMENT 'Directeur qui effectue l''action',
  `version_plan` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `points`
--

CREATE TABLE `points` (
  `idpoints` int(11) NOT NULL,
  `coteObtenu` decimal(10,2) DEFAULT NULL,
  `typeEvaluation` int(11) DEFAULT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `annee_acad_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ponderation_ecue`
--

CREATE TABLE `ponderation_ecue` (
  `idponderation` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `coefficient` decimal(5,2) NOT NULL DEFAULT 1.00,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `preinscription`
--

CREATE TABLE `preinscription` (
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

CREATE TABLE `presence_agent` (
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

-- --------------------------------------------------------

--
-- Structure de la table `presence_cours`
--

CREATE TABLE `presence_cours` (
  `idpresence` int(11) NOT NULL,
  `idseance` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `heure_arrivee` datetime NOT NULL,
  `statut` enum('Présent','Retard','Absent','Excusé') NOT NULL DEFAULT 'Présent',
  `commentaire` text DEFAULT NULL,
  `methode_enregistrement` enum('QR Code','Manuel') NOT NULL DEFAULT 'QR Code',
  `ip_address` varchar(45) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `presence_labo`
--

CREATE TABLE `presence_labo` (
  `idpresence_labo` int(11) NOT NULL,
  `idseance_labo` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `heure_arrivee` datetime NOT NULL,
  `statut` enum('Présent','Retard','Absent','Excusé') NOT NULL DEFAULT 'Présent',
  `commentaire` text DEFAULT NULL,
  `methode_enregistrement` enum('QR Code','Manuel') NOT NULL DEFAULT 'QR Code',
  `ip_address` varchar(45) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `processus_deliberation`
--

CREATE TABLE `processus_deliberation` (
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

-- --------------------------------------------------------

--
-- Structure de la table `projet`
--

CREATE TABLE `projet` (
  `idProjet` int(11) NOT NULL,
  `nomProjet` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dateDebut` date DEFAULT NULL,
  `dateFin` date DEFAULT NULL,
  `statut` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `promotion`
--

CREATE TABLE `promotion` (
  `idpromotion` int(11) NOT NULL,
  `designationPromotion` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `cycle` enum('Premier','Deuxieme','Troisieme','') NOT NULL,
  `orientation_idorientation` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `est_terminale` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rapport_financier`
--

CREATE TABLE `rapport_financier` (
  `id_rapport` int(11) NOT NULL,
  `code_rapport` varchar(20) NOT NULL,
  `date_rapport` date NOT NULL,
  `id_succursale` int(11) NOT NULL,
  `periode_debut` date NOT NULL,
  `periode_fin` date NOT NULL,
  `total_recettes` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_depenses` decimal(15,2) NOT NULL DEFAULT 0.00,
  `solde` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observation` text DEFAULT NULL,
  `est_consolide` tinyint(1) NOT NULL DEFAULT 0,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recours`
--

CREATE TABLE `recours` (
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

CREATE TABLE `recours_historique` (
  `id_historique` int(11) NOT NULL,
  `id_recours` int(11) NOT NULL COMMENT 'ID du recours concerné',
  `action` varchar(50) NOT NULL COMMENT 'Type d''action effectuée',
  `details` text DEFAULT NULL COMMENT 'Détails supplémentaires sur l''action',
  `date_action` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date et heure de l''action',
  `id_utilisateur` int(11) NOT NULL COMMENT 'ID de l''utilisateur ayant effectué l''action'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historique des actions effectuées sur les recours';

-- --------------------------------------------------------

--
-- Structure de la table `recours_reponse`
--

CREATE TABLE `recours_reponse` (
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

CREATE TABLE `regle_passage` (
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
-- Structure de la table `rendez_vous`
--

CREATE TABLE `rendez_vous` (
  `idRendez_vous` int(11) NOT NULL,
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
  `description` text DEFAULT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `statut_rendez_vous` enum('planifie','confirme','reporte','annule','termine') DEFAULT 'planifie',
  `type_rendez_vous` varchar(100) DEFAULT NULL,
  `priorite` enum('basse','normale','haute','urgente') DEFAULT 'normale',
  `rappel_active` tinyint(1) DEFAULT 1,
  `delai_rappel` int(11) DEFAULT 30,
  `commentaires` text DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cree_par` int(11) NOT NULL,
  `modifie_par` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reponses_devoir`
--

CREATE TABLE `reponses_devoir` (
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

CREATE TABLE `research_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `unite_recherche` varchar(255) DEFAULT NULL,
  `projet_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `responsable_departement`
--

CREATE TABLE `responsable_departement` (
  `idresponsable_departement` int(11) NOT NULL,
  `noms` varchar(145) DEFAULT NULL,
  `fonction` varchar(145) DEFAULT NULL,
  `signature` varchar(145) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `departement_iddepartement` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `responsable_orientation`
--

CREATE TABLE `responsable_orientation` (
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

CREATE TABLE `responsable_section` (
  `idresponsable_section` int(11) NOT NULL,
  `noms` varchar(245) DEFAULT NULL,
  `fonction` varchar(145) DEFAULT NULL,
  `signature` varchar(145) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `section_idsection` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ressources_cours`
--

CREATE TABLE `ressources_cours` (
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

CREATE TABLE `resultat_deliberation` (
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

CREATE TABLE `salle` (
  `idSalle` int(11) NOT NULL,
  `designationSalle` varchar(255) NOT NULL,
  `dateCreation` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `seance_cours`
--

CREATE TABLE `seance_cours` (
  `idseance` int(11) NOT NULL,
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
  `ref_latitude` decimal(10,8) DEFAULT NULL,
  `ref_longitude` decimal(11,8) DEFAULT NULL,
  `geo_verification_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `seance_labo`
--

CREATE TABLE `seance_labo` (
  `idseance_labo` int(11) NOT NULL,
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
  `ref_latitude` decimal(10,8) DEFAULT NULL,
  `ref_longitude` decimal(11,8) DEFAULT NULL,
  `geo_verification_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `section`
--

CREATE TABLE `section` (
  `idsection` int(11) NOT NULL,
  `designationSection` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `idAnnee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `section_chapitre`
--

CREATE TABLE `section_chapitre` (
  `idsection_chapitre` int(11) NOT NULL,
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
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `semestre`
--

CREATE TABLE `semestre` (
  `idsemestre` int(11) NOT NULL,
  `numeroSemestre` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `promotion_idpromotion` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `service`
--

CREATE TABLE `service` (
  `idService` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `Responsable` varchar(145) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `session`
--

CREATE TABLE `session` (
  `idsession` int(11) NOT NULL,
  `designSession` varchar(45) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions_caisse`
--

CREATE TABLE `sessions_caisse` (
  `id` int(11) NOT NULL,
  `caisse_id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `date_ouverture` datetime NOT NULL,
  `montant_ouverture` decimal(15,2) NOT NULL,
  `date_fermeture` datetime DEFAULT NULL,
  `montant_fermeture` decimal(15,2) DEFAULT NULL,
  `montant_calcule` decimal(15,2) DEFAULT NULL,
  `difference` decimal(15,2) DEFAULT NULL,
  `explication_difference` text DEFAULT NULL,
  `statut` enum('Ouverte','Fermée','Annulée') NOT NULL DEFAULT 'Ouverte',
  `commentaire` text DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `situation_financiere_etudiant`
--

CREATE TABLE `situation_financiere_etudiant` (
  `id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `matricule_etudiant` varchar(245) DEFAULT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `total_du` decimal(15,2) DEFAULT 0.00,
  `total_paye` decimal(15,2) DEFAULT 0.00,
  `solde` decimal(15,2) DEFAULT 0.00,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `date_derniere_maj` datetime DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `solde_conge`
--

CREATE TABLE `solde_conge` (
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

-- --------------------------------------------------------

--
-- Structure de la table `soutenance`
--

CREATE TABLE `soutenance` (
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

CREATE TABLE `specialisation` (
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

CREATE TABLE `statistique_recherche` (
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

CREATE TABLE `statut_devoir_etudiant` (
  `id` int(11) NOT NULL,
  `iddevoir` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `statut` varchar(50) NOT NULL COMMENT 'Statut: Non commencé, Vu, Soumis, Noté, etc.',
  `date_modification` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `statut_paiement_cours`
--

CREATE TABLE `statut_paiement_cours` (
  `id` int(11) NOT NULL,
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
  `statut` enum('paye','non paye') NOT NULL DEFAULT 'non paye'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `structure`
--

CREATE TABLE `structure` (
  `idStructure` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `adresse` varchar(145) DEFAULT NULL,
  `siteweb` varchar(45) DEFAULT NULL,
  `phone1` varchar(45) DEFAULT NULL,
  `phone2` varchar(45) DEFAULT NULL,
  `logo` varchar(145) DEFAULT NULL,
  `joursOuvrables` int(11) DEFAULT NULL,
  `IPR` float DEFAULT NULL,
  `taux_retenu_absence` float DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `nJoursRecouvrement` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `suivi_enseignements`
--

CREATE TABLE `suivi_enseignements` (
  `id_suivi` int(11) NOT NULL,
  `chef_promotion_id` int(11) NOT NULL COMMENT 'ID de l''étudiant chef de promotion',
  `idECUE` int(11) NOT NULL COMMENT 'ID de la matière/ECUE',
  `date_cours` date NOT NULL COMMENT 'Date de la séance de cours',
  `heure_debut` time NOT NULL COMMENT 'Heure de début du cours',
  `heure_fin` time NOT NULL COMMENT 'Heure de fin du cours',
  `type_cours` enum('CM','TD','TP','Evaluation') NOT NULL DEFAULT 'CM' COMMENT 'Type de cours',
  `enseignant_id` int(11) DEFAULT NULL COMMENT 'ID de l''enseignant (optionnel)',
  `salle` varchar(100) DEFAULT NULL COMMENT 'Salle de cours',
  `commentaire` text DEFAULT NULL COMMENT 'Commentaires ou observations',
  `annee_acad_idannee_acad` int(11) NOT NULL COMMENT 'Année académique',
  `date_encodage` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur ayant créé l''enregistrement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Suivi des enseignements par les chefs de promotion';

-- --------------------------------------------------------

--
-- Structure de la table `suivi_heures_enseignement`
--

CREATE TABLE `suivi_heures_enseignement` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `date_seance` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `duree` decimal(5,2) NOT NULL COMMENT 'Durée en heures',
  `type_cours` enum('CM','TD','TP','Évaluation') NOT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `validation_departement` tinyint(1) DEFAULT 0,
  `date_validation_dept` datetime DEFAULT NULL,
  `idValidateur_dept` int(11) DEFAULT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `suivi_paiements_promotion`
--

CREATE TABLE `suivi_paiements_promotion` (
  `id` int(11) NOT NULL,
  `affectation_id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `matricule_etudiant` varchar(245) NOT NULL,
  `montant_specifique` decimal(15,2) DEFAULT NULL COMMENT 'Montant individuel si différent',
  `montant_paye` decimal(15,2) DEFAULT 0.00,
  `montant_restant` decimal(15,2) DEFAULT NULL,
  `statut_paiement` enum('Non payé','Partiel','Complet') DEFAULT 'Non payé',
  `date_dernier_paiement` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sujets`
--

CREATE TABLE `sujets` (
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
  `statut_validation` enum('En attente','Validé','A reformulé','Modifié') NOT NULL DEFAULT 'En attente',
  `commentaire_commission` text DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sujet_validation_history`
--

CREATE TABLE `sujet_validation_history` (
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

CREATE TABLE `support_cours` (
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

CREATE TABLE `taches` (
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
-- Structure de la table `taux_horaires_visiteurs`
--

CREATE TABLE `taux_horaires_visiteurs` (
  `id` int(11) NOT NULL,
  `grade_id` int(11) NOT NULL,
  `niveau_cours` enum('Premier cycle','Deuxième cycle','Troisième cycle') NOT NULL,
  `type_cours` enum('CM','TD','TP','Évaluation') NOT NULL,
  `taux_horaire` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `annee_acad_id` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `teacher_info`
--

CREATE TABLE `teacher_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `specialisation` varchar(255) DEFAULT NULL,
  `domaine_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tentatives_fraude_presence`
--

CREATE TABLE `tentatives_fraude_presence` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `idseance` int(11) NOT NULL,
  `type_seance` enum('cours','labo') NOT NULL,
  `matricule_tente` varchar(50) DEFAULT NULL,
  `date_tentative` datetime NOT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tranches_paiement_config`
--

CREATE TABLE `tranches_paiement_config` (
  `id` int(11) NOT NULL,
  `frais_id` int(11) NOT NULL,
  `numero_tranche` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `pourcentage` decimal(5,2) NOT NULL,
  `montant_fixe` decimal(15,2) DEFAULT NULL,
  `date_echeance_relative` int(11) DEFAULT NULL COMMENT 'Jours après l''affectation',
  `date_echeance_fixe` date DEFAULT NULL,
  `est_requis_inscription` tinyint(1) DEFAULT 0,
  `est_requis_examens` tinyint(1) DEFAULT 0,
  `est_requis_deliberation` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `type` enum('Recette','Dépense','Transfert','Ajustement') NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `taux_change` decimal(15,6) DEFAULT NULL,
  `montant_devise_principale` decimal(15,2) DEFAULT NULL,
  `date_transaction` date NOT NULL,
  `source` enum('Caisse','Banque') NOT NULL,
  `source_id` int(11) NOT NULL COMMENT 'ID de la caisse ou du compte bancaire',
  `destination_id` int(11) DEFAULT NULL COMMENT 'Pour les transferts',
  `categorie_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `pieces_jointes` varchar(255) DEFAULT NULL,
  `statut` enum('Provisoire','Confirmée','Annulée') NOT NULL DEFAULT 'Provisoire',
  `motif_annulation` text DEFAULT NULL,
  `idAgent` int(11) NOT NULL,
  `session_caisse_id` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL,
  `beneficiaire` varchar(255) DEFAULT NULL,
  `depositaire` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `travaux_scientifiques`
--

CREATE TABLE `travaux_scientifiques` (
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

CREATE TABLE `typeevaluation` (
  `idType` int(11) NOT NULL,
  `designationT` varchar(155) NOT NULL,
  `categorie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `types_remuneration`
--

CREATE TABLE `types_remuneration` (
  `id` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `type_agent` enum('Enseignant','Administratif','Recherche') NOT NULL,
  `mode_calcul` enum('Forfaitaire','Horaire','Journalier','Mensuel','Par crédit') NOT NULL,
  `montant_base` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `type_agent`
--

CREATE TABLE `type_agent` (
  `id` int(11) NOT NULL,
  `libelle` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `type_conge`
--

CREATE TABLE `type_conge` (
  `idtype_conge` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duree_standard` int(11) DEFAULT NULL COMMENT 'Durée standard en jours ouvrables',
  `est_cumulable` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `type_rendez_vous`
--

CREATE TABLE `type_rendez_vous` (
  `idType_rendez_vous` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duree_defaut` int(11) DEFAULT 60,
  `couleur` varchar(7) DEFAULT '#007bff',
  `actif` tinyint(1) DEFAULT 1,
  `Service_idService` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `t_modules`
--

CREATE TABLE `t_modules` (
  `idMod` int(255) NOT NULL,
  `nomMod` varchar(255) NOT NULL,
  `package` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `t_permissions`
--

CREATE TABLE `t_permissions` (
  `idPerm` int(255) NOT NULL,
  `idMod` int(255) NOT NULL,
  `codePerm` varchar(255) NOT NULL,
  `nomPerm` varchar(255) NOT NULL,
  `descPerm` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `t_roles`
--

CREATE TABLE `t_roles` (
  `idRole` int(255) NOT NULL,
  `nomRole` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `t_users`
--

CREATE TABLE `t_users` (
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

-- --------------------------------------------------------

--
-- Structure de la table `t_user_permissions`
--

CREATE TABLE `t_user_permissions` (
  `idUP` int(255) NOT NULL,
  `idRole` int(255) NOT NULL,
  `idPerm` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ue`
--

CREATE TABLE `ue` (
  `idUE` int(11) NOT NULL,
  `codeUE` varchar(45) DEFAULT NULL,
  `designationUE` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `semestre_idsemestre` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `unite_recherche`
--

CREATE TABLE `unite_recherche` (
  `idunite_recherche` int(11) NOT NULL,
  `designation_UR` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `unite_recherche_orientation`
--

CREATE TABLE `unite_recherche_orientation` (
  `idur_orientation` int(11) NOT NULL,
  `idunite_recherche` int(11) NOT NULL,
  `idorientation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `unite_recherche_section`
--

CREATE TABLE `unite_recherche_section` (
  `idur_section` int(11) NOT NULL,
  `idunite_recherche` int(11) NOT NULL,
  `idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `validations_etats_besoin`
--

CREATE TABLE `validations_etats_besoin` (
  `id` int(11) NOT NULL,
  `etat_besoin_id` int(11) NOT NULL,
  `etape` varchar(100) NOT NULL,
  `decision` enum('Approuvé','Rejeté','En attente information','Modification demandée') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_validation` datetime DEFAULT current_timestamp(),
  `validateur_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `validation_notes_soutenance`
--

CREATE TABLE `validation_notes_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `est_valide` tinyint(1) DEFAULT 0,
  `date_validation` datetime DEFAULT NULL,
  `id_validateur` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `est_visible` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `visites`
--

CREATE TABLE `visites` (
  `idVisite` int(11) NOT NULL,
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
  `description` text DEFAULT NULL,
  `lieu_rencontre` varchar(255) DEFAULT NULL,
  `statut_visite` enum('programmee','en_cours','terminee','annulee','reportee') DEFAULT 'programmee',
  `type_visite` enum('professionnelle','personnelle','officielle','urgente') DEFAULT 'professionnelle',
  `nombre_accompagnants` int(11) DEFAULT 0,
  `carte_identite` varchar(255) DEFAULT NULL COMMENT 'Numéro de carte d''identité',
  `observations` text DEFAULT NULL,
  `badge_visiteur` varchar(50) DEFAULT NULL,
  `heure_arrivee_reelle` time DEFAULT NULL,
  `heure_depart_reelle` time DEFAULT NULL,
  `validation_securite` tinyint(1) DEFAULT 0,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cree_par` int(11) NOT NULL,
  `modifie_par` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_dettes_etudiants_avec_moyenne_ue`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_dettes_etudiants_avec_moyenne_ue` (
`id_dette` int(11)
,`matricule` varchar(255)
,`nom_etudiant` varchar(245)
,`designationECUE` varchar(245)
,`designationUE` varchar(245)
,`numeroSemestre` varchar(45)
,`designationPromotion` varchar(245)
,`cycle` enum('Premier','Deuxieme','Troisieme','')
,`est_terminale` tinyint(4)
,`designSession` varchar(45)
,`annee_academique` varchar(145)
,`note_obtenue` decimal(5,2)
,`credits_ecue` int(11)
,`statut` enum('En cours','Validée','Annulée')
,`date_creation` datetime
,`note_rachat` decimal(5,2)
,`date_validation` datetime
,`moyenne_ue` double(23,6)
,`etat_rachat` varchar(11)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_historique_changement_decision`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_historique_changement_decision` (
`id_historique` int(11)
,`matricule` varchar(255)
,`nom_etudiant` varchar(245)
,`ancienne_decision` varchar(50)
,`nouvelle_decision` varchar(50)
,`nb_dettes_supprimees` int(11)
,`designationPromotion` varchar(245)
,`designSession` varchar(45)
,`annee_academique` varchar(145)
,`date_changement` datetime
,`modifie_par` varchar(255)
);

-- --------------------------------------------------------

--
-- Structure de la vue `v_dettes_etudiants_avec_moyenne_ue`
--
DROP TABLE IF EXISTS `v_dettes_etudiants_avec_moyenne_ue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dettes_etudiants_avec_moyenne_ue`  AS SELECT `d`.`id_dette` AS `id_dette`, `d`.`matricule` AS `matricule`, `e`.`noms` AS `nom_etudiant`, `ecue`.`designationECUE` AS `designationECUE`, `ue`.`designationUE` AS `designationUE`, `s`.`numeroSemestre` AS `numeroSemestre`, `p`.`designationPromotion` AS `designationPromotion`, `p`.`cycle` AS `cycle`, `p`.`est_terminale` AS `est_terminale`, `sess`.`designSession` AS `designSession`, `aa`.`designation` AS `annee_academique`, `d`.`note_obtenue` AS `note_obtenue`, `d`.`credits_ecue` AS `credits_ecue`, `d`.`statut` AS `statut`, `d`.`date_creation` AS `date_creation`, `d`.`note_rachat` AS `note_rachat`, `d`.`date_validation` AS `date_validation`, (select sum(`cg2`.`MF` * round((`e2`.`CMI` + `e2`.`TD` + `e2`.`TP`) / 25,2)) / sum(round((`e2`.`CMI` + `e2`.`TD` + `e2`.`TP`) / 25,2)) from (`cotes_grille` `cg2` join `ecue` `e2` on(`cg2`.`ECUE_idECUE` = `e2`.`idECUE`)) where `cg2`.`matricule` = `d`.`matricule` and `e2`.`UE_idUE` = `d`.`UE_idUE` and `cg2`.`session_idsession` = `d`.`session_idsession` and `cg2`.`annee_acad_id` = `d`.`annee_acad_idannee_acad`) AS `moyenne_ue`, CASE WHEN `d`.`note_rachat` is not null AND `d`.`note_rachat` >= 10 THEN 'Validée' WHEN `d`.`note_rachat` is not null AND `d`.`note_rachat` < 10 THEN 'Non validée' ELSE 'En attente' END AS `etat_rachat` FROM (((((((`dette_etudiant` `d` join `etudiant` `e` on(`d`.`matricule` = `e`.`matricule`)) join `ecue` on(`d`.`ECUE_idECUE` = `ecue`.`idECUE`)) join `ue` on(`d`.`UE_idUE` = `ue`.`idUE`)) join `semestre` `s` on(`d`.`semestre_idsemestre` = `s`.`idsemestre`)) join `promotion` `p` on(`d`.`promotion_idpromotion` = `p`.`idpromotion`)) join `session` `sess` on(`d`.`session_idsession` = `sess`.`idsession`)) join `annee_acad` `aa` on(`d`.`annee_acad_idannee_acad` = `aa`.`idannee_acad`)) ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_historique_changement_decision`
--
DROP TABLE IF EXISTS `v_historique_changement_decision`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_historique_changement_decision`  AS SELECT `h`.`id_historique` AS `id_historique`, `h`.`matricule` AS `matricule`, `e`.`noms` AS `nom_etudiant`, `h`.`ancienne_decision` AS `ancienne_decision`, `h`.`nouvelle_decision` AS `nouvelle_decision`, `h`.`nb_dettes_supprimees` AS `nb_dettes_supprimees`, `p`.`designationPromotion` AS `designationPromotion`, `s`.`designSession` AS `designSession`, `a`.`designation` AS `annee_academique`, `h`.`date_changement` AS `date_changement`, `u`.`nomUser` AS `modifie_par` FROM (((((`historique_changement_decision` `h` left join `etudiant` `e` on(`h`.`matricule` = `e`.`matricule`)) left join `promotion` `p` on(`h`.`promotion_id` = `p`.`idpromotion`)) left join `session` `s` on(`h`.`session_id` = `s`.`idsession`)) left join `annee_acad` `a` on(`h`.`annee_acad_id` = `a`.`idannee_acad`)) left join `t_users` `u` on(`h`.`created_by` = `u`.`idUser`)) ORDER BY `h`.`date_changement` DESC ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `affectation_agent`
--
ALTER TABLE `affectation_agent`
  ADD PRIMARY KEY (`idaffectation`),
  ADD KEY `fk_affectation_agent` (`idAgent`),
  ADD KEY `fk_affectation_structure` (`idStructure`),
  ADD KEY `fk_affectation_service` (`idService`);

--
-- Index pour la table `affectation_frais`
--
ALTER TABLE `affectation_frais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_affectation_frais_2` (`frais_id`),
  ADD KEY `fk_affectation_promotion` (`promotion_id`),
  ADD KEY `fk_affectation_etudiant` (`etudiant_id`),
  ADD KEY `idx_matricule_affectation` (`matricule_etudiant`);

--
-- Index pour la table `agent`
--
ALTER TABLE `agent`
  ADD PRIMARY KEY (`idAgent`),
  ADD KEY `fk_agent_grade` (`grade_id`),
  ADD KEY `fk_agent_service` (`idService`);

--
-- Index pour la table `agent_section`
--
ALTER TABLE `agent_section`
  ADD PRIMARY KEY (`idagent_section`),
  ADD KEY `idAgent` (`idAgent`),
  ADD KEY `idsection` (`idsection`);

--
-- Index pour la table `alertes_financieres`
--
ALTER TABLE `alertes_financieres`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alertes_destinataire` (`destinataire_id`),
  ADD KEY `idx_alertes_role` (`role_destinataire`);

--
-- Index pour la table `annee_acad`
--
ALTER TABLE `annee_acad`
  ADD PRIMARY KEY (`idannee_acad`);

--
-- Index pour la table `archive_numerique`
--
ALTER TABLE `archive_numerique`
  ADD PRIMARY KEY (`idarchive`),
  ADD KEY `fk_archive_user` (`idUser`);

--
-- Index pour la table `autorisations_paiement`
--
ALTER TABLE `autorisations_paiement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_reference_autorisation` (`reference`),
  ADD KEY `fk_autorisation_beneficiaire` (`beneficiaire_id`),
  ADD KEY `fk_autorisation_besoin` (`etat_besoin_id`),
  ADD KEY `fk_autorisation_engagement` (`engagement_id`);

--
-- Index pour la table `autorisation_depot`
--
ALTER TABLE `autorisation_depot`
  ADD PRIMARY KEY (`id_autorisation`),
  ADD UNIQUE KEY `uk_user_depot` (`id_user`,`id_depot`);

--
-- Index pour la table `autorisation_labo`
--
ALTER TABLE `autorisation_labo`
  ADD PRIMARY KEY (`idautorisation`);

--
-- Index pour la table `avances_salaires`
--
ALTER TABLE `avances_salaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_reference_avance` (`reference`),
  ADD KEY `fk_avance_agent` (`agent_id`),
  ADD KEY `fk_avance_transaction` (`transaction_id`);

--
-- Index pour la table `banque`
--
ALTER TABLE `banque`
  ADD PRIMARY KEY (`id_banque`),
  ADD UNIQUE KEY `code_banque_UNIQUE` (`code_banque`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `beneficiaires`
--
ALTER TABLE `beneficiaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_beneficiaire_type_ref` (`type`,`ref_id`);

--
-- Index pour la table `budget`
--
ALTER TABLE `budget`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_budget_categorie_exercice` (`exercice_id`,`categorie_id`),
  ADD KEY `fk_budget_exercice` (`exercice_id`),
  ADD KEY `fk_budget_categorie` (`categorie_id`);

--
-- Index pour la table `bureau_jury_deliberation`
--
ALTER TABLE `bureau_jury_deliberation`
  ADD PRIMARY KEY (`idbureau`),
  ADD KEY `fk_bureau_jury_annee` (`annee_acad_idannee_acad`),
  ADD KEY `fk_bureau_jury_president` (`president_id`),
  ADD KEY `fk_bureau_jury_secretaire` (`secretaire_id`);

--
-- Index pour la table `bureau_jury_promotion`
--
ALTER TABLE `bureau_jury_promotion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bureau_promotion` (`idbureau`,`idpromotion`),
  ADD KEY `fk_bureau_jury` (`idbureau`),
  ADD KEY `fk_promotion` (`idpromotion`);

--
-- Index pour la table `caisses`
--
ALTER TABLE `caisses`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `categories_budget`
--
ALTER TABLE `categories_budget`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_code_categorie` (`code`),
  ADD KEY `fk_categories_compte` (`compte_comptable_id`);

--
-- Index pour la table `categories_doc`
--
ALTER TABLE `categories_doc`
  ADD PRIMARY KEY (`id_categorie`),
  ADD KEY `idStructure` (`idStructure`);

--
-- Index pour la table `categories_frais`
--
ALTER TABLE `categories_frais`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `categorie_indicateur`
--
ALTER TABLE `categorie_indicateur`
  ADD PRIMARY KEY (`idCategorie`);

--
-- Index pour la table `chapitre_plan`
--
ALTER TABLE `chapitre_plan`
  ADD PRIMARY KEY (`idchapitre_plan`),
  ADD KEY `idx_chapitre_plan` (`idplan_travail`),
  ADD KEY `idx_chapitre_numero` (`numero_chapitre`),
  ADD KEY `idx_chapitre_deadline` (`deadline`),
  ADD KEY `idx_chapitre_statut` (`statut`);

--
-- Index pour la table `charges_enseignement`
--
ALTER TABLE `charges_enseignement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_charge_unique` (`idAgent`,`idECUE`,`annee_acad_id`),
  ADD KEY `fk_charge_agent` (`idAgent`),
  ADD KEY `fk_charge_ecue` (`idECUE`),
  ADD KEY `fk_charge_annee` (`annee_acad_id`);

--
-- Index pour la table `chef_promotion`
--
ALTER TABLE `chef_promotion`
  ADD PRIMARY KEY (`id_chef`),
  ADD UNIQUE KEY `idx_chef_unique_actif` (`promotion_idpromotion`,`annee_acad_idannee_acad`,`est_actif`);

--
-- Index pour la table `comptes_bancaires`
--
ALTER TABLE `comptes_bancaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_numero_compte` (`numero_compte`);

--
-- Index pour la table `compte_comptable`
--
ALTER TABLE `compte_comptable`
  ADD PRIMARY KEY (`id_compte`),
  ADD UNIQUE KEY `numero_compte_UNIQUE` (`numero_compte`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `configuration_deliberation`
--
ALTER TABLE `configuration_deliberation`
  ADD PRIMARY KEY (`idconfig`),
  ADD KEY `fk_config_bureau` (`idbureau`),
  ADD KEY `fk_config_session` (`session_idsession`),
  ADD KEY `fk_config_annee` (`annee_acad_idannee_acad`);

--
-- Index pour la table `configuration_moyenne`
--
ALTER TABLE `configuration_moyenne`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `configuration_universite`
--
ALTER TABLE `configuration_universite`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `config_finance`
--
ALTER TABLE `config_finance`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `contrat_agent`
--
ALTER TABLE `contrat_agent`
  ADD PRIMARY KEY (`idContrat_agent`),
  ADD KEY `fk_Contrat_agent_Agent_idx` (`Agent_idAgent`),
  ADD KEY `fk_Contrat_agent_Service1_idx` (`Service_idService`);

--
-- Index pour la table `conversation`
--
ALTER TABLE `conversation`
  ADD PRIMARY KEY (`idconversation`),
  ADD KEY `fk_conversation_sujet` (`sujets_idsujets`);

--
-- Index pour la table `cotes_grille`
--
ALTER TABLE `cotes_grille`
  ADD PRIMARY KEY (`idpoints`),
  ADD UNIQUE KEY `unique_cote` (`ECUE_idECUE`,`session_idsession`,`matricule`,`annee_acad_id`),
  ADD KEY `idx_ecue` (`ECUE_idECUE`),
  ADD KEY `idx_session` (`session_idsession`),
  ADD KEY `idx_matricule` (`matricule`),
  ADD KEY `idx_annee` (`annee_acad_id`);

--
-- Index pour la table `couriels_recu`
--
ALTER TABLE `couriels_recu`
  ADD PRIMARY KEY (`idcouriels_recu`),
  ADD KEY `fk_couriels_recu_Service1_idx` (`Service_idService`);

--
-- Index pour la table `courrier_academique`
--
ALTER TABLE `courrier_academique`
  ADD PRIMARY KEY (`idcourrier`),
  ADD KEY `fk_courrier_user` (`idUser`);

--
-- Index pour la table `deadline_assignment`
--
ALTER TABLE `deadline_assignment`
  ADD PRIMARY KEY (`iddeadline`),
  ADD KEY `idx_deadline_chapitre` (`idchapitre_plan`),
  ADD KEY `idx_deadline_section` (`idsection_chapitre`),
  ADD KEY `idx_deadline_date` (`deadline`),
  ADD KEY `idx_deadline_directeur` (`idDirecteur`),
  ADD KEY `idx_deadline_statut` (`statut_deadline`);

--
-- Index pour la table `deliberation`
--
ALTER TABLE `deliberation`
  ADD PRIMARY KEY (`iddeliberation`),
  ADD KEY `fk_deliberation_bureau` (`idbureau`),
  ADD KEY `fk_deliberation_promotion` (`idpromotion`),
  ADD KEY `fk_deliberation_session` (`session_idsession`);

--
-- Index pour la table `demande_conge`
--
ALTER TABLE `demande_conge`
  ADD PRIMARY KEY (`iddemande_conge`),
  ADD KEY `fk_demande_agent` (`idAgent`),
  ADD KEY `fk_demande_type` (`idtype_conge`),
  ADD KEY `fk_demande_decideur` (`idDecideur`);

--
-- Index pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `depot_memoire`
--
ALTER TABLE `depot_memoire`
  ADD PRIMARY KEY (`iddepot_memoire`),
  ADD KEY `fk_depot_memoire_sujets_idx` (`sujets_idsujets`);

--
-- Index pour la table `depot_rapport`
--
ALTER TABLE `depot_rapport`
  ADD PRIMARY KEY (`iddepot_rapport`),
  ADD KEY `fk_depot_rapport_encadreur_idx` (`encadreur`),
  ADD KEY `fk_depot_rapport_etudiant_idx` (`etudiant_idetudiant`);

--
-- Index pour la table `details_etats_besoin`
--
ALTER TABLE `details_etats_besoin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_details_etat_besoin` (`etat_besoin_id`);

--
-- Index pour la table `details_etats_paiement`
--
ALTER TABLE `details_etats_paiement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detail_etat` (`etat_paiement_id`),
  ADD KEY `fk_detail_paie` (`paie_id`),
  ADD KEY `fk_detail_ordre_paiement` (`ordre_paiement_id`),
  ADD KEY `fk_detail_etat_agent` (`idAgent`);

--
-- Index pour la table `details_paiement_visiteurs`
--
ALTER TABLE `details_paiement_visiteurs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detail_ordre` (`ordre_paiement_id`),
  ADD KEY `fk_detail_ecue` (`idECUE`);

--
-- Index pour la table `detail_rapport_financier`
--
ALTER TABLE `detail_rapport_financier`
  ADD PRIMARY KEY (`id_detail_rapport`),
  ADD KEY `id_rapport` (`id_rapport`),
  ADD KEY `id_compte_comptable` (`id_compte_comptable`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `dette_bulletin`
--
ALTER TABLE `dette_bulletin`
  ADD PRIMARY KEY (`id_bulletin`),
  ADD KEY `fk_bulletin_promotion` (`promotion_idpromotion`),
  ADD KEY `fk_bulletin_session` (`session_idsession`),
  ADD KEY `fk_bulletin_annee` (`annee_acad_idannee_acad`),
  ADD KEY `fk_bulletin_user` (`idUser`);

--
-- Index pour la table `dette_etudiant`
--
ALTER TABLE `dette_etudiant`
  ADD PRIMARY KEY (`id_dette`),
  ADD KEY `fk_dette_etudiant` (`matricule`),
  ADD KEY `fk_dette_ecue` (`ECUE_idECUE`),
  ADD KEY `fk_dette_ue` (`UE_idUE`),
  ADD KEY `fk_dette_semestre` (`semestre_idsemestre`),
  ADD KEY `fk_dette_promotion` (`promotion_idpromotion`),
  ADD KEY `fk_dette_session` (`session_idsession`),
  ADD KEY `fk_dette_annee` (`annee_acad_idannee_acad`),
  ADD KEY `fk_dette_user` (`idUser`);

--
-- Index pour la table `dette_evaluation`
--
ALTER TABLE `dette_evaluation`
  ADD PRIMARY KEY (`id_evaluation`),
  ADD KEY `fk_evaluation_dette` (`id_dette`),
  ADD KEY `fk_evaluation_session` (`session_idsession`),
  ADD KEY `fk_evaluation_annee` (`annee_acad_idannee_acad`),
  ADD KEY `fk_evaluation_user` (`idUser`);

--
-- Index pour la table `dette_historique`
--
ALTER TABLE `dette_historique`
  ADD PRIMARY KEY (`id_historique`),
  ADD KEY `fk_historique_dette` (`id_dette`),
  ADD KEY `fk_historique_dette_user` (`idUser`);

--
-- Index pour la table `devoirs`
--
ALTER TABLE `devoirs`
  ADD PRIMARY KEY (`iddevoir`);

--
-- Index pour la table `documents_obligatoires`
--
ALTER TABLE `documents_obligatoires`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `documents_prive`
--
ALTER TABLE `documents_prive`
  ADD PRIMARY KEY (`id_document`);

--
-- Index pour la table `documents_public`
--
ALTER TABLE `documents_public`
  ADD PRIMARY KEY (`id_document`),
  ADD KEY `id_categorie` (`id_categorie`),
  ADD KEY `idUser` (`idUser`);

--
-- Index pour la table `document_agent`
--
ALTER TABLE `document_agent`
  ADD PRIMARY KEY (`idDocument_agent`),
  ADD KEY `fk_Document_agent_Agent1_idx` (`Agent_idAgent`);

--
-- Index pour la table `dossier_famille`
--
ALTER TABLE `dossier_famille`
  ADD PRIMARY KEY (`idDossier_famille`),
  ADD KEY `fk_Dossier_famille_Agent1_idx` (`Agent_idAgent`);

--
-- Index pour la table `dossier_scientifique`
--
ALTER TABLE `dossier_scientifique`
  ADD PRIMARY KEY (`iddossier`),
  ADD KEY `fk_dossier_enseignant` (`idenseignant`),
  ADD KEY `fk_dossier_user` (`idUser`);

--
-- Index pour la table `droits_acces_finances`
--
ALTER TABLE `droits_acces_finances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_droits_user_type` (`idUser`,`type`);

--
-- Index pour la table `ecard_access_keys`
--
ALTER TABLE `ecard_access_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_key` (`access_key`),
  ADD KEY `access_key_2` (`access_key`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Index pour la table `ecard_verification_log`
--
ALTER TABLE `ecard_verification_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_id` (`card_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `verification_date` (`verification_date`);

--
-- Index pour la table `echanges_taches`
--
ALTER TABLE `echanges_taches`
  ADD PRIMARY KEY (`idechange`),
  ADD KEY `fk_echanges_taches` (`taches_idtaches`);

--
-- Index pour la table `echange_chapitre`
--
ALTER TABLE `echange_chapitre`
  ADD PRIMARY KEY (`idechange_chapitre`),
  ADD KEY `idx_echange_chapitre` (`idchapitre_plan`),
  ADD KEY `idx_echange_auteur` (`idAuteur`,`type_auteur`),
  ADD KEY `idx_echange_date` (`date_echange`),
  ADD KEY `idx_echange_reponse` (`reponse_a`);

--
-- Index pour la table `echelonnement_paiement`
--
ALTER TABLE `echelonnement_paiement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_echelonnement_affectation` (`affectation_id`);

--
-- Index pour la table `echelonnement_paiement_etudiant`
--
ALTER TABLE `echelonnement_paiement_etudiant`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_echelonnement_etudiant` (`echelonnement_id`,`etudiant_id`),
  ADD KEY `fk_echelonnement_etudiant` (`etudiant_id`),
  ADD KEY `fk_echelonnement_affectation` (`affectation_id`);

--
-- Index pour la table `ecriture_comptable`
--
ALTER TABLE `ecriture_comptable`
  ADD PRIMARY KEY (`id_ecriture`),
  ADD UNIQUE KEY `numero_ecriture_UNIQUE` (`numero_ecriture`);

--
-- Index pour la table `ecue`
--
ALTER TABLE `ecue`
  ADD PRIMARY KEY (`idECUE`),
  ADD KEY `fk_ECUE_UE1_idx` (`UE_idUE`),
  ADD KEY `idCreateur` (`idCreateur`);

--
-- Index pour la table `ecue_notes_verrouillage`
--
ALTER TABLE `ecue_notes_verrouillage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_verrouillage` (`idECUE`,`idsession`,`idannee_acad`);

--
-- Index pour la table `enseignant_ecue`
--
ALTER TABLE `enseignant_ecue`
  ADD PRIMARY KEY (`idenseignant_ecue`);

--
-- Index pour la table `enseignant_section`
--
ALTER TABLE `enseignant_section`
  ADD PRIMARY KEY (`idenseignant_section`),
  ADD KEY `fk_enseignant_section_enseignant` (`idenseignant`),
  ADD KEY `fk_enseignant_section_section` (`idsection`);

--
-- Index pour la table `enseignant_specialisation`
--
ALTER TABLE `enseignant_specialisation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idAgent` (`idAgent`),
  ADD KEY `idSpecialisation` (`idSpecialisation`);

--
-- Index pour la table `enseignant_uniterecherche`
--
ALTER TABLE `enseignant_uniterecherche`
  ADD PRIMARY KEY (`idAffectation`);

--
-- Index pour la table `etats_besoin`
--
ALTER TABLE `etats_besoin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_reference_besoin` (`reference`),
  ADD KEY `fk_besoin_categorie` (`categorie_budget_id`),
  ADD KEY `fk_besoin_exercice` (`exercice_id`),
  ADD KEY `fk_besoin_demandeur` (`demandeur_id`),
  ADD KEY `fk_besoin_service` (`service_id`);

--
-- Index pour la table `etudiant`
--
ALTER TABLE `etudiant`
  ADD PRIMARY KEY (`idetudiant`),
  ADD KEY `fk_etudiant_annee_acad_idx` (`annee_acad_idannee_acad`),
  ADD KEY `fk_etudiant_promotion_idx` (`promotion_idpromotion`);

--
-- Index pour la table `etudiants_cards`
--
ALTER TABLE `etudiants_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `card_id` (`card_id`),
  ADD KEY `card_id_2` (`card_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`);

--
-- Index pour la table `etudiants_palmares_archives`
--
ALTER TABLE `etudiants_palmares_archives`
  ADD PRIMARY KEY (`idetudiant_palmares`),
  ADD KEY `fk_etudiants_palmares_archives` (`idpalmares`);

--
-- Index pour la table `etudiant_documents`
--
ALTER TABLE `etudiant_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_etudiant_documents_etudiant` (`idetudiant`),
  ADD KEY `idx_etudiant_documents_matricule` (`matricule`),
  ADD KEY `idx_etudiant_documents_obligatoire` (`document_obligatoire_id`);

--
-- Index pour la table `etudiant_documents_historique`
--
ALTER TABLE `etudiant_documents_historique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historique_document` (`document_id`);

--
-- Index pour la table `etudiant_en_ordre`
--
ALTER TABLE `etudiant_en_ordre`
  ADD PRIMARY KEY (`idetudiant_ordre`),
  ADD UNIQUE KEY `uk_etudiant_frais_annee` (`idetudiant`,`idfrais`,`annee_acad_idannee_acad`),
  ADD KEY `fk_etudiant_ordre_frais` (`idfrais`),
  ADD KEY `fk_etudiant_ordre_annee` (`annee_acad_idannee_acad`),
  ADD KEY `fk_etudiant_ordre_import` (`idimport`),
  ADD KEY `fk_etudiant_ordre_user` (`idUser`);

--
-- Index pour la table `etudiant_historique`
--
ALTER TABLE `etudiant_historique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historique_etudiant` (`idetudiant`),
  ADD KEY `fk_historique_promotion` (`idpromotion`),
  ADD KEY `fk_historique_annee_acad` (`idannee_acad`);

--
-- Index pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`idevaluation`);

--
-- Index pour la table `exercices_budgetaires`
--
ALTER TABLE `exercices_budgetaires`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `exercice_comptable`
--
ALTER TABLE `exercice_comptable`
  ADD PRIMARY KEY (`id_exercice`);

--
-- Index pour la table `frais`
--
ALTER TABLE `frais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_frais_categorie` (`categorie_id`),
  ADD KEY `fk_frais_annee_acad` (`annee_acad_id`);

--
-- Index pour la table `grade`
--
ALTER TABLE `grade`
  ADD PRIMARY KEY (`idgrade`);

--
-- Index pour la table `grille_salaires`
--
ALTER TABLE `grille_salaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_grade_echelon` (`grade_id`,`echelon`);

--
-- Index pour la table `historique_changement_decision`
--
ALTER TABLE `historique_changement_decision`
  ADD PRIMARY KEY (`id_historique`),
  ADD KEY `idx_matricule` (`matricule`),
  ADD KEY `idx_annee` (`annee_acad_id`),
  ADD KEY `idx_date` (`date_changement`);

--
-- Index pour la table `historique_cotes`
--
ALTER TABLE `historique_cotes`
  ADD PRIMARY KEY (`idhistorique`);

--
-- Index pour la table `historique_grade`
--
ALTER TABLE `historique_grade`
  ADD PRIMARY KEY (`idhistorique_grade`);

--
-- Index pour la table `historique_notes`
--
ALTER TABLE `historique_notes`
  ADD PRIMARY KEY (`idhistorique`);

--
-- Index pour la table `historique_soldes`
--
ALTER TABLE `historique_soldes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_solde_source_date` (`type`,`source_id`,`date`);

--
-- Index pour la table `historique_visites`
--
ALTER TABLE `historique_visites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historique_visite` (`idVisite`);

--
-- Index pour la table `horaires_cours`
--
ALTER TABLE `horaires_cours`
  ADD PRIMARY KEY (`idhoraire`);

--
-- Index pour la table `intervention_jury`
--
ALTER TABLE `intervention_jury`
  ADD PRIMARY KEY (`idintervention`);

--
-- Index pour la table `journal_comptable`
--
ALTER TABLE `journal_comptable`
  ADD PRIMARY KEY (`id_journal`),
  ADD UNIQUE KEY `code_journal_UNIQUE` (`code_journal`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `jury`
--
ALTER TABLE `jury`
  ADD PRIMARY KEY (`idjury`);

--
-- Index pour la table `jury_membre_autorisations`
--
ALTER TABLE `jury_membre_autorisations`
  ADD PRIMARY KEY (`id_autorisation`),
  ADD UNIQUE KEY `unique_autorisation` (`idbureau`,`idAgent`,`idECUE`,`session_idsession`,`annee_acad_idannee_acad`),
  ADD KEY `fk_autorisations_bureau` (`idbureau`),
  ADD KEY `fk_autorisations_agent` (`idAgent`),
  ADD KEY `fk_autorisations_ecue` (`idECUE`),
  ADD KEY `fk_autorisations_session` (`session_idsession`),
  ADD KEY `fk_autorisations_annee` (`annee_acad_idannee_acad`),
  ADD KEY `fk_autorisations_user` (`idUser`);

--
-- Index pour la table `jury_soutenance`
--
ALTER TABLE `jury_soutenance`
  ADD PRIMARY KEY (`idjury`);

--
-- Index pour la table `laboratoire`
--
ALTER TABLE `laboratoire`
  ADD PRIMARY KEY (`idlabo`);

--
-- Index pour la table `lecteurs_soutenance`
--
ALTER TABLE `lecteurs_soutenance`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `ligne_budget`
--
ALTER TABLE `ligne_budget`
  ADD PRIMARY KEY (`id_ligne_budget`),
  ADD KEY `id_budget` (`id_budget`),
  ADD KEY `id_compte_comptable` (`id_compte_comptable`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `ligne_ecriture_comptable`
--
ALTER TABLE `ligne_ecriture_comptable`
  ADD PRIMARY KEY (`id_ligne_ecriture`),
  ADD KEY `id_ecriture` (`id_ecriture`),
  ADD KEY `id_compte` (`id_compte`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `log_operation`
--
ALTER TABLE `log_operation`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_user` (`id_user`);

--
-- Index pour la table `membre_bureau_jury`
--
ALTER TABLE `membre_bureau_jury`
  ADD PRIMARY KEY (`idmembre`);

--
-- Index pour la table `mode_paiement`
--
ALTER TABLE `mode_paiement`
  ADD PRIMARY KEY (`id_mode_paiement`),
  ADD UNIQUE KEY `code_mode_UNIQUE` (`code_mode`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `moyenne_annuelle`
--
ALTER TABLE `moyenne_annuelle`
  ADD PRIMARY KEY (`idmoyenne_annuelle`);

--
-- Index pour la table `moyenne_semestre`
--
ALTER TABLE `moyenne_semestre`
  ADD PRIMARY KEY (`idmoyenne_semestre`);

--
-- Index pour la table `moyenne_ue`
--
ALTER TABLE `moyenne_ue`
  ADD PRIMARY KEY (`idmoyenne_ue`);

--
-- Index pour la table `notifications_documents`
--
ALTER TABLE `notifications_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_docs_etudiant` (`idetudiant`),
  ADD KEY `fk_notif_docs_user` (`idUser`);

--
-- Index pour la table `notification_plan`
--
ALTER TABLE `notification_plan`
  ADD PRIMARY KEY (`idnotification`),
  ADD KEY `idx_notif_destinataire` (`destinataire_id`,`type_destinataire`),
  ADD KEY `idx_notif_type` (`type_notification`),
  ADD KEY `idx_notif_plan` (`idplan_travail`),
  ADD KEY `idx_notif_chapitre` (`idchapitre_plan`),
  ADD KEY `idx_notif_statut` (`statut_lecture`);

--
-- Index pour la table `objectifs_financiers`
--
ALTER TABLE `objectifs_financiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_objectif_planification` (`planification_id`),
  ADD KEY `fk_objectif_categorie` (`categorie_budget_id`);

--
-- Index pour la table `operation_bancaire`
--
ALTER TABLE `operation_bancaire`
  ADD PRIMARY KEY (`id_operation`),
  ADD UNIQUE KEY `numero_operation_UNIQUE` (`numero_operation`),
  ADD KEY `id_compte_bancaire` (`id_compte_bancaire`),
  ADD KEY `id_compte_comptable` (`id_compte_comptable`),
  ADD KEY `id_budget` (`id_budget`),
  ADD KEY `id_ligne_budget` (`id_ligne_budget`),
  ADD KEY `id_caisse` (`id_caisse`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `operation_caisse`
--
ALTER TABLE `operation_caisse`
  ADD PRIMARY KEY (`id_operation`),
  ADD UNIQUE KEY `numero_operation_UNIQUE` (`numero_operation`),
  ADD KEY `id_caisse` (`id_caisse`),
  ADD KEY `id_compte_comptable` (`id_compte_comptable`),
  ADD KEY `id_budget` (`id_budget`),
  ADD KEY `id_ligne_budget` (`id_ligne_budget`),
  ADD KEY `id_compte_bancaire` (`id_compte_bancaire`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `ordres_paiement_visiteurs`
--
ALTER TABLE `ordres_paiement_visiteurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_reference_ordre` (`reference`),
  ADD KEY `fk_ordre_agent` (`idAgent`),
  ADD KEY `fk_ordre_transaction` (`transaction_id`),
  ADD KEY `fk_ordre_annee` (`annee_acad_id`);

--
-- Index pour la table `orientation`
--
ALTER TABLE `orientation`
  ADD PRIMARY KEY (`idorientation`);

--
-- Index pour la table `paiements_frais`
--
ALTER TABLE `paiements_frais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_paiements_transaction` (`transaction_id`),
  ADD KEY `fk_paiements_etudiant` (`etudiant_id`),
  ADD KEY `fk_paiements_affectation` (`affectation_id`),
  ADD KEY `idx_matricule_paiement` (`matricule_etudiant`);

--
-- Index pour la table `paiements_tranches`
--
ALTER TABLE `paiements_tranches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_paiement_tranche_echelonnement` (`echelonnement_id`),
  ADD KEY `fk_paiement_tranche_paiement` (`paiement_id`);

--
-- Index pour la table `paies_mensuelles`
--
ALTER TABLE `paies_mensuelles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_reference_paie` (`reference`),
  ADD UNIQUE KEY `idx_paie_mensuelle_unique` (`idAgent`,`mois`,`annee`),
  ADD KEY `fk_paie_agent` (`idAgent`),
  ADD KEY `fk_paie_transaction` (`transaction_id`);

--
-- Index pour la table `palmares_archive`
--
ALTER TABLE `palmares_archive`
  ADD PRIMARY KEY (`id_palmares`),
  ADD KEY `fk_palmares_user` (`idUser`),
  ADD KEY `fk_palmares_annee` (`annee_acad_idannee_acad`),
  ADD KEY `fk_palmares_promotion` (`promotion_idpromotion`),
  ADD KEY `fk_palmares_session` (`session_idsession`);

--
-- Index pour la table `palmares_archives`
--
ALTER TABLE `palmares_archives`
  ADD PRIMARY KEY (`idpalmares`),
  ADD KEY `fk_palmares_archives_user` (`idUser`);

--
-- Index pour la table `palmares_etudiant`
--
ALTER TABLE `palmares_etudiant`
  ADD PRIMARY KEY (`id_palmares_etudiant`),
  ADD KEY `fk_palmares_etudiant_palmares` (`id_palmares`),
  ADD KEY `fk_palmares_etudiant_etudiant` (`idetudiant`);

--
-- Index pour la table `palmares_historique`
--
ALTER TABLE `palmares_historique`
  ADD PRIMARY KEY (`id_historique`),
  ADD KEY `fk_historique_palmares` (`id_palmares`),
  ADD KEY `fk_historique_user` (`idUser`);

--
-- Index pour la table `periodes_essai`
--
ALTER TABLE `periodes_essai`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `plan_comptable`
--
ALTER TABLE `plan_comptable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_code_compte` (`code`);

--
-- Index pour la table `plan_travail`
--
ALTER TABLE `plan_travail`
  ADD PRIMARY KEY (`idplan_travail`),
  ADD KEY `idx_plan_sujet` (`idsujets`),
  ADD KEY `idx_plan_statut` (`statut_validation`),
  ADD KEY `idx_plan_validateur` (`idValidateur`);

--
-- Index pour la table `plan_validation_history`
--
ALTER TABLE `plan_validation_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_plan` (`idplan_travail`),
  ADD KEY `idx_history_date` (`date_action`);

--
-- Index pour la table `points`
--
ALTER TABLE `points`
  ADD PRIMARY KEY (`idpoints`);

--
-- Index pour la table `presence_cours`
--
ALTER TABLE `presence_cours`
  ADD PRIMARY KEY (`idpresence`),
  ADD UNIQUE KEY `idx_unique_presence` (`idseance`,`idetudiant`);

--
-- Index pour la table `presence_labo`
--
ALTER TABLE `presence_labo`
  ADD PRIMARY KEY (`idpresence_labo`),
  ADD UNIQUE KEY `idx_unique_presence_labo` (`idseance_labo`,`idetudiant`);

--
-- Index pour la table `promotion`
--
ALTER TABLE `promotion`
  ADD PRIMARY KEY (`idpromotion`);

--
-- Index pour la table `rapport_financier`
--
ALTER TABLE `rapport_financier`
  ADD PRIMARY KEY (`id_rapport`),
  ADD UNIQUE KEY `code_rapport_UNIQUE` (`code_rapport`),
  ADD KEY `id_succursale` (`id_succursale`),
  ADD KEY `id_user_creation` (`id_user_creation`);

--
-- Index pour la table `recours`
--
ALTER TABLE `recours`
  ADD PRIMARY KEY (`id_recours`);

--
-- Index pour la table `recours_reponse`
--
ALTER TABLE `recours_reponse`
  ADD PRIMARY KEY (`id_reponse`);

--
-- Index pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  ADD PRIMARY KEY (`idRendez_vous`),
  ADD KEY `fk_rendez_vous_Agent1_idx` (`Agent_idAgent`),
  ADD KEY `fk_rendez_vous_Service1_idx` (`Service_idService`),
  ADD KEY `fk_rendez_vous_Patient1_idx` (`Patient_idPatient`),
  ADD KEY `fk_rendez_vous_User_creation_idx` (`cree_par`),
  ADD KEY `fk_rendez_vous_User_modification_idx` (`modifie_par`),
  ADD KEY `idx_date_rendez_vous` (`date_rendez_vous`),
  ADD KEY `idx_statut` (`statut_rendez_vous`);

--
-- Index pour la table `responsable_orientation`
--
ALTER TABLE `responsable_orientation`
  ADD PRIMARY KEY (`idresponsable_orientation`);

--
-- Index pour la table `responsable_section`
--
ALTER TABLE `responsable_section`
  ADD PRIMARY KEY (`idresponsable_section`);

--
-- Index pour la table `ressources_cours`
--
ALTER TABLE `ressources_cours`
  ADD PRIMARY KEY (`idressource`);

--
-- Index pour la table `resultat_deliberation`
--
ALTER TABLE `resultat_deliberation`
  ADD PRIMARY KEY (`idresultat`);

--
-- Index pour la table `salle`
--
ALTER TABLE `salle`
  ADD PRIMARY KEY (`idSalle`);

--
-- Index pour la table `seance_cours`
--
ALTER TABLE `seance_cours`
  ADD PRIMARY KEY (`idseance`);

--
-- Index pour la table `seance_labo`
--
ALTER TABLE `seance_labo`
  ADD PRIMARY KEY (`idseance_labo`);

--
-- Index pour la table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`idsection`);

--
-- Index pour la table `section_chapitre`
--
ALTER TABLE `section_chapitre`
  ADD PRIMARY KEY (`idsection_chapitre`),
  ADD KEY `idx_section_chapitre` (`idchapitre_plan`),
  ADD KEY `idx_section_numero` (`numero_section`),
  ADD KEY `idx_section_deadline` (`deadline`);

--
-- Index pour la table `semestre`
--
ALTER TABLE `semestre`
  ADD PRIMARY KEY (`idsemestre`);

--
-- Index pour la table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`idService`);

--
-- Index pour la table `session`
--
ALTER TABLE `session`
  ADD PRIMARY KEY (`idsession`);

--
-- Index pour la table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sessions_caisse` (`caisse_id`),
  ADD KEY `fk_sessions_agent` (`idAgent`);

--
-- Index pour la table `situation_financiere_etudiant`
--
ALTER TABLE `situation_financiere_etudiant`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_situation_etudiant_annee` (`etudiant_id`,`annee_acad_id`),
  ADD KEY `fk_situation_etudiant` (`etudiant_id`),
  ADD KEY `fk_situation_annee` (`annee_acad_id`),
  ADD KEY `idx_matricule_situation` (`matricule_etudiant`);

--
-- Index pour la table `specialisation`
--
ALTER TABLE `specialisation`
  ADD PRIMARY KEY (`idSpecialisation`);

--
-- Index pour la table `statut_devoir_etudiant`
--
ALTER TABLE `statut_devoir_etudiant`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `statut_paiement_cours`
--
ALTER TABLE `statut_paiement_cours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_statut_agent` (`idAgent`),
  ADD KEY `fk_statut_ecue` (`idECUE`),
  ADD KEY `fk_statut_annee_acad` (`annee_acad_id`);

--
-- Index pour la table `structure`
--
ALTER TABLE `structure`
  ADD PRIMARY KEY (`idStructure`);

--
-- Index pour la table `suivi_enseignements`
--
ALTER TABLE `suivi_enseignements`
  ADD PRIMARY KEY (`id_suivi`),
  ADD KEY `idx_chef_promotion` (`chef_promotion_id`),
  ADD KEY `idx_ecue` (`idECUE`),
  ADD KEY `idx_enseignant` (`enseignant_id`),
  ADD KEY `idx_annee_acad` (`annee_acad_idannee_acad`),
  ADD KEY `idx_date_cours` (`date_cours`),
  ADD KEY `idx_chef_date` (`chef_promotion_id`,`date_cours`);

--
-- Index pour la table `suivi_heures_enseignement`
--
ALTER TABLE `suivi_heures_enseignement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_suivi_agent` (`idAgent`),
  ADD KEY `fk_suivi_ecue` (`idECUE`),
  ADD KEY `fk_suivi_annee` (`annee_acad_id`);

--
-- Index pour la table `suivi_paiements_promotion`
--
ALTER TABLE `suivi_paiements_promotion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_affectation_etudiant` (`affectation_id`,`etudiant_id`),
  ADD KEY `fk_suivi_affectation` (`affectation_id`),
  ADD KEY `fk_suivi_etudiant` (`etudiant_id`),
  ADD KEY `idx_suivi_matricule` (`matricule_etudiant`);

--
-- Index pour la table `sujets`
--
ALTER TABLE `sujets`
  ADD PRIMARY KEY (`idsujets`);

--
-- Index pour la table `sujet_validation_history`
--
ALTER TABLE `sujet_validation_history`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `support_cours`
--
ALTER TABLE `support_cours`
  ADD PRIMARY KEY (`idsupport`);

--
-- Index pour la table `taches`
--
ALTER TABLE `taches`
  ADD PRIMARY KEY (`idtaches`);

--
-- Index pour la table `taux_horaires_visiteurs`
--
ALTER TABLE `taux_horaires_visiteurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_taux_unique` (`grade_id`,`niveau_cours`,`type_cours`,`annee_acad_id`);

--
-- Index pour la table `tentatives_fraude_presence`
--
ALTER TABLE `tentatives_fraude_presence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `idseance` (`idseance`,`type_seance`);

--
-- Index pour la table `tranches_paiement_config`
--
ALTER TABLE `tranches_paiement_config`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_reference` (`reference`),
  ADD KEY `idx_transactions_date` (`date_transaction`),
  ADD KEY `idx_transactions_source` (`source`,`source_id`),
  ADD KEY `idx_transactions_session` (`session_caisse_id`);

--
-- Index pour la table `travaux_scientifiques`
--
ALTER TABLE `travaux_scientifiques`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `typeevaluation`
--
ALTER TABLE `typeevaluation`
  ADD PRIMARY KEY (`idType`);

--
-- Index pour la table `types_remuneration`
--
ALTER TABLE `types_remuneration`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `type_agent`
--
ALTER TABLE `type_agent`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `type_conge`
--
ALTER TABLE `type_conge`
  ADD PRIMARY KEY (`idtype_conge`);

--
-- Index pour la table `type_rendez_vous`
--
ALTER TABLE `type_rendez_vous`
  ADD PRIMARY KEY (`idType_rendez_vous`),
  ADD KEY `fk_type_rendez_vous_Service1_idx` (`Service_idService`);

--
-- Index pour la table `t_modules`
--
ALTER TABLE `t_modules`
  ADD PRIMARY KEY (`idMod`);

--
-- Index pour la table `t_permissions`
--
ALTER TABLE `t_permissions`
  ADD PRIMARY KEY (`idPerm`);

--
-- Index pour la table `t_roles`
--
ALTER TABLE `t_roles`
  ADD PRIMARY KEY (`idRole`);

--
-- Index pour la table `t_users`
--
ALTER TABLE `t_users`
  ADD PRIMARY KEY (`idUser`);

--
-- Index pour la table `t_user_permissions`
--
ALTER TABLE `t_user_permissions`
  ADD PRIMARY KEY (`idUP`);

--
-- Index pour la table `ue`
--
ALTER TABLE `ue`
  ADD PRIMARY KEY (`idUE`);

--
-- Index pour la table `unite_recherche`
--
ALTER TABLE `unite_recherche`
  ADD PRIMARY KEY (`idunite_recherche`);

--
-- Index pour la table `unite_recherche_orientation`
--
ALTER TABLE `unite_recherche_orientation`
  ADD PRIMARY KEY (`idur_orientation`);

--
-- Index pour la table `unite_recherche_section`
--
ALTER TABLE `unite_recherche_section`
  ADD PRIMARY KEY (`idur_section`);

--
-- Index pour la table `validations_etats_besoin`
--
ALTER TABLE `validations_etats_besoin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_validations_etat_besoin` (`etat_besoin_id`),
  ADD KEY `fk_validations_validateur` (`validateur_id`);

--
-- Index pour la table `visites`
--
ALTER TABLE `visites`
  ADD PRIMARY KEY (`idVisite`),
  ADD KEY `fk_visite_Agent1_idx` (`Agent_idAgent`),
  ADD KEY `fk_visite_Service1_idx` (`Service_idService`),
  ADD KEY `fk_visite_User_creation_idx` (`cree_par`),
  ADD KEY `fk_visite_User_modification_idx` (`modifie_par`),
  ADD KEY `idx_date_visite` (`date_visite`),
  ADD KEY `idx_statut_visite` (`statut_visite`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `affectation_agent`
--
ALTER TABLE `affectation_agent`
  MODIFY `idaffectation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `affectation_frais`
--
ALTER TABLE `affectation_frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `agent`
--
ALTER TABLE `agent`
  MODIFY `idAgent` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `agent_section`
--
ALTER TABLE `agent_section`
  MODIFY `idagent_section` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `alertes_financieres`
--
ALTER TABLE `alertes_financieres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `annee_acad`
--
ALTER TABLE `annee_acad`
  MODIFY `idannee_acad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `archive_numerique`
--
ALTER TABLE `archive_numerique`
  MODIFY `idarchive` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `autorisations_paiement`
--
ALTER TABLE `autorisations_paiement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `autorisation_depot`
--
ALTER TABLE `autorisation_depot`
  MODIFY `id_autorisation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `autorisation_labo`
--
ALTER TABLE `autorisation_labo`
  MODIFY `idautorisation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `avances_salaires`
--
ALTER TABLE `avances_salaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `banque`
--
ALTER TABLE `banque`
  MODIFY `id_banque` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `beneficiaires`
--
ALTER TABLE `beneficiaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `budget`
--
ALTER TABLE `budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `bureau_jury_deliberation`
--
ALTER TABLE `bureau_jury_deliberation`
  MODIFY `idbureau` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `bureau_jury_promotion`
--
ALTER TABLE `bureau_jury_promotion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `caisses`
--
ALTER TABLE `caisses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories_budget`
--
ALTER TABLE `categories_budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories_doc`
--
ALTER TABLE `categories_doc`
  MODIFY `id_categorie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories_frais`
--
ALTER TABLE `categories_frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categorie_indicateur`
--
ALTER TABLE `categorie_indicateur`
  MODIFY `idCategorie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chapitre_plan`
--
ALTER TABLE `chapitre_plan`
  MODIFY `idchapitre_plan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `charges_enseignement`
--
ALTER TABLE `charges_enseignement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chef_promotion`
--
ALTER TABLE `chef_promotion`
  MODIFY `id_chef` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comptes_bancaires`
--
ALTER TABLE `comptes_bancaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `compte_comptable`
--
ALTER TABLE `compte_comptable`
  MODIFY `id_compte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `configuration_deliberation`
--
ALTER TABLE `configuration_deliberation`
  MODIFY `idconfig` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `configuration_moyenne`
--
ALTER TABLE `configuration_moyenne`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `configuration_universite`
--
ALTER TABLE `configuration_universite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `config_finance`
--
ALTER TABLE `config_finance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contrat_agent`
--
ALTER TABLE `contrat_agent`
  MODIFY `idContrat_agent` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cotes_grille`
--
ALTER TABLE `cotes_grille`
  MODIFY `idpoints` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `deadline_assignment`
--
ALTER TABLE `deadline_assignment`
  MODIFY `iddeadline` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `deliberation`
--
ALTER TABLE `deliberation`
  MODIFY `iddeliberation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depenses`
--
ALTER TABLE `depenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depot_memoire`
--
ALTER TABLE `depot_memoire`
  MODIFY `iddepot_memoire` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `details_etats_besoin`
--
ALTER TABLE `details_etats_besoin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `details_etats_paiement`
--
ALTER TABLE `details_etats_paiement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `details_paiement_visiteurs`
--
ALTER TABLE `details_paiement_visiteurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `detail_rapport_financier`
--
ALTER TABLE `detail_rapport_financier`
  MODIFY `id_detail_rapport` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dette_bulletin`
--
ALTER TABLE `dette_bulletin`
  MODIFY `id_bulletin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dette_etudiant`
--
ALTER TABLE `dette_etudiant`
  MODIFY `id_dette` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dette_evaluation`
--
ALTER TABLE `dette_evaluation`
  MODIFY `id_evaluation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dette_historique`
--
ALTER TABLE `dette_historique`
  MODIFY `id_historique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `devoirs`
--
ALTER TABLE `devoirs`
  MODIFY `iddevoir` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents_obligatoires`
--
ALTER TABLE `documents_obligatoires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents_prive`
--
ALTER TABLE `documents_prive`
  MODIFY `id_document` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents_public`
--
ALTER TABLE `documents_public`
  MODIFY `id_document` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_agent`
--
ALTER TABLE `document_agent`
  MODIFY `idDocument_agent` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dossier_famille`
--
ALTER TABLE `dossier_famille`
  MODIFY `idDossier_famille` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `droits_acces_finances`
--
ALTER TABLE `droits_acces_finances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `echanges_taches`
--
ALTER TABLE `echanges_taches`
  MODIFY `idechange` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `echange_chapitre`
--
ALTER TABLE `echange_chapitre`
  MODIFY `idechange_chapitre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `echelonnement_paiement`
--
ALTER TABLE `echelonnement_paiement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `echelonnement_paiement_etudiant`
--
ALTER TABLE `echelonnement_paiement_etudiant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ecriture_comptable`
--
ALTER TABLE `ecriture_comptable`
  MODIFY `id_ecriture` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ecue`
--
ALTER TABLE `ecue`
  MODIFY `idECUE` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ecue_notes_verrouillage`
--
ALTER TABLE `ecue_notes_verrouillage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `enseignant_ecue`
--
ALTER TABLE `enseignant_ecue`
  MODIFY `idenseignant_ecue` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `enseignant_section`
--
ALTER TABLE `enseignant_section`
  MODIFY `idenseignant_section` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `enseignant_specialisation`
--
ALTER TABLE `enseignant_specialisation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `enseignant_uniterecherche`
--
ALTER TABLE `enseignant_uniterecherche`
  MODIFY `idAffectation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etats_besoin`
--
ALTER TABLE `etats_besoin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiant`
--
ALTER TABLE `etudiant`
  MODIFY `idetudiant` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiants_cards`
--
ALTER TABLE `etudiants_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiants_palmares_archives`
--
ALTER TABLE `etudiants_palmares_archives`
  MODIFY `idetudiant_palmares` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiant_documents`
--
ALTER TABLE `etudiant_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiant_documents_historique`
--
ALTER TABLE `etudiant_documents_historique`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiant_en_ordre`
--
ALTER TABLE `etudiant_en_ordre`
  MODIFY `idetudiant_ordre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiant_historique`
--
ALTER TABLE `etudiant_historique`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `idevaluation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `exercices_budgetaires`
--
ALTER TABLE `exercices_budgetaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `exercice_comptable`
--
ALTER TABLE `exercice_comptable`
  MODIFY `id_exercice` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `frais`
--
ALTER TABLE `frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grade`
--
ALTER TABLE `grade`
  MODIFY `idgrade` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grille_salaires`
--
ALTER TABLE `grille_salaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_changement_decision`
--
ALTER TABLE `historique_changement_decision`
  MODIFY `id_historique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_cotes`
--
ALTER TABLE `historique_cotes`
  MODIFY `idhistorique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_grade`
--
ALTER TABLE `historique_grade`
  MODIFY `idhistorique_grade` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_notes`
--
ALTER TABLE `historique_notes`
  MODIFY `idhistorique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_soldes`
--
ALTER TABLE `historique_soldes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_visites`
--
ALTER TABLE `historique_visites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `horaires_cours`
--
ALTER TABLE `horaires_cours`
  MODIFY `idhoraire` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intervention_jury`
--
ALTER TABLE `intervention_jury`
  MODIFY `idintervention` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `journal_comptable`
--
ALTER TABLE `journal_comptable`
  MODIFY `id_journal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `jury`
--
ALTER TABLE `jury`
  MODIFY `idjury` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `jury_membre_autorisations`
--
ALTER TABLE `jury_membre_autorisations`
  MODIFY `id_autorisation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `jury_soutenance`
--
ALTER TABLE `jury_soutenance`
  MODIFY `idjury` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `laboratoire`
--
ALTER TABLE `laboratoire`
  MODIFY `idlabo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lecteurs_soutenance`
--
ALTER TABLE `lecteurs_soutenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ligne_budget`
--
ALTER TABLE `ligne_budget`
  MODIFY `id_ligne_budget` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ligne_ecriture_comptable`
--
ALTER TABLE `ligne_ecriture_comptable`
  MODIFY `id_ligne_ecriture` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `log_operation`
--
ALTER TABLE `log_operation`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `membre_bureau_jury`
--
ALTER TABLE `membre_bureau_jury`
  MODIFY `idmembre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mode_paiement`
--
ALTER TABLE `mode_paiement`
  MODIFY `id_mode_paiement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `moyenne_annuelle`
--
ALTER TABLE `moyenne_annuelle`
  MODIFY `idmoyenne_annuelle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `moyenne_semestre`
--
ALTER TABLE `moyenne_semestre`
  MODIFY `idmoyenne_semestre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `moyenne_ue`
--
ALTER TABLE `moyenne_ue`
  MODIFY `idmoyenne_ue` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_documents`
--
ALTER TABLE `notifications_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notification_plan`
--
ALTER TABLE `notification_plan`
  MODIFY `idnotification` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `objectifs_financiers`
--
ALTER TABLE `objectifs_financiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `operation_bancaire`
--
ALTER TABLE `operation_bancaire`
  MODIFY `id_operation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `operation_caisse`
--
ALTER TABLE `operation_caisse`
  MODIFY `id_operation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ordres_paiement_visiteurs`
--
ALTER TABLE `ordres_paiement_visiteurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `orientation`
--
ALTER TABLE `orientation`
  MODIFY `idorientation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiements_frais`
--
ALTER TABLE `paiements_frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiements_tranches`
--
ALTER TABLE `paiements_tranches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paies_mensuelles`
--
ALTER TABLE `paies_mensuelles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `palmares_archive`
--
ALTER TABLE `palmares_archive`
  MODIFY `id_palmares` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `palmares_archives`
--
ALTER TABLE `palmares_archives`
  MODIFY `idpalmares` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `palmares_etudiant`
--
ALTER TABLE `palmares_etudiant`
  MODIFY `id_palmares_etudiant` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `palmares_historique`
--
ALTER TABLE `palmares_historique`
  MODIFY `id_historique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `periodes_essai`
--
ALTER TABLE `periodes_essai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `plan_comptable`
--
ALTER TABLE `plan_comptable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `plan_travail`
--
ALTER TABLE `plan_travail`
  MODIFY `idplan_travail` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `plan_validation_history`
--
ALTER TABLE `plan_validation_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `points`
--
ALTER TABLE `points`
  MODIFY `idpoints` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `presence_cours`
--
ALTER TABLE `presence_cours`
  MODIFY `idpresence` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `presence_labo`
--
ALTER TABLE `presence_labo`
  MODIFY `idpresence_labo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `promotion`
--
ALTER TABLE `promotion`
  MODIFY `idpromotion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rapport_financier`
--
ALTER TABLE `rapport_financier`
  MODIFY `id_rapport` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recours`
--
ALTER TABLE `recours`
  MODIFY `id_recours` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recours_reponse`
--
ALTER TABLE `recours_reponse`
  MODIFY `id_reponse` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  MODIFY `idRendez_vous` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `responsable_orientation`
--
ALTER TABLE `responsable_orientation`
  MODIFY `idresponsable_orientation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `responsable_section`
--
ALTER TABLE `responsable_section`
  MODIFY `idresponsable_section` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ressources_cours`
--
ALTER TABLE `ressources_cours`
  MODIFY `idressource` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `resultat_deliberation`
--
ALTER TABLE `resultat_deliberation`
  MODIFY `idresultat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `salle`
--
ALTER TABLE `salle`
  MODIFY `idSalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `seance_cours`
--
ALTER TABLE `seance_cours`
  MODIFY `idseance` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `seance_labo`
--
ALTER TABLE `seance_labo`
  MODIFY `idseance_labo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `section`
--
ALTER TABLE `section`
  MODIFY `idsection` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `section_chapitre`
--
ALTER TABLE `section_chapitre`
  MODIFY `idsection_chapitre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `semestre`
--
ALTER TABLE `semestre`
  MODIFY `idsemestre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `service`
--
ALTER TABLE `service`
  MODIFY `idService` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `session`
--
ALTER TABLE `session`
  MODIFY `idsession` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `situation_financiere_etudiant`
--
ALTER TABLE `situation_financiere_etudiant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `specialisation`
--
ALTER TABLE `specialisation`
  MODIFY `idSpecialisation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `statut_devoir_etudiant`
--
ALTER TABLE `statut_devoir_etudiant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `statut_paiement_cours`
--
ALTER TABLE `statut_paiement_cours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `structure`
--
ALTER TABLE `structure`
  MODIFY `idStructure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivi_enseignements`
--
ALTER TABLE `suivi_enseignements`
  MODIFY `id_suivi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivi_heures_enseignement`
--
ALTER TABLE `suivi_heures_enseignement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivi_paiements_promotion`
--
ALTER TABLE `suivi_paiements_promotion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sujets`
--
ALTER TABLE `sujets`
  MODIFY `idsujets` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sujet_validation_history`
--
ALTER TABLE `sujet_validation_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `support_cours`
--
ALTER TABLE `support_cours`
  MODIFY `idsupport` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `idtaches` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taux_horaires_visiteurs`
--
ALTER TABLE `taux_horaires_visiteurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tentatives_fraude_presence`
--
ALTER TABLE `tentatives_fraude_presence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tranches_paiement_config`
--
ALTER TABLE `tranches_paiement_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `travaux_scientifiques`
--
ALTER TABLE `travaux_scientifiques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `typeevaluation`
--
ALTER TABLE `typeevaluation`
  MODIFY `idType` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `types_remuneration`
--
ALTER TABLE `types_remuneration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `type_agent`
--
ALTER TABLE `type_agent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `type_conge`
--
ALTER TABLE `type_conge`
  MODIFY `idtype_conge` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `type_rendez_vous`
--
ALTER TABLE `type_rendez_vous`
  MODIFY `idType_rendez_vous` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `t_modules`
--
ALTER TABLE `t_modules`
  MODIFY `idMod` int(255) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `t_permissions`
--
ALTER TABLE `t_permissions`
  MODIFY `idPerm` int(255) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `t_roles`
--
ALTER TABLE `t_roles`
  MODIFY `idRole` int(255) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `t_users`
--
ALTER TABLE `t_users`
  MODIFY `idUser` int(255) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `t_user_permissions`
--
ALTER TABLE `t_user_permissions`
  MODIFY `idUP` int(255) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ue`
--
ALTER TABLE `ue`
  MODIFY `idUE` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `unite_recherche`
--
ALTER TABLE `unite_recherche`
  MODIFY `idunite_recherche` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `unite_recherche_orientation`
--
ALTER TABLE `unite_recherche_orientation`
  MODIFY `idur_orientation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `unite_recherche_section`
--
ALTER TABLE `unite_recherche_section`
  MODIFY `idur_section` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `validations_etats_besoin`
--
ALTER TABLE `validations_etats_besoin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `visites`
--
ALTER TABLE `visites`
  MODIFY `idVisite` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `banque`
--
ALTER TABLE `banque`
  ADD CONSTRAINT `banque_ibfk_1` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `categories_budget`
--
ALTER TABLE `categories_budget`
  ADD CONSTRAINT `fk_categories_compte` FOREIGN KEY (`compte_comptable_id`) REFERENCES `plan_comptable` (`id`);

--
-- Contraintes pour la table `chapitre_plan`
--
ALTER TABLE `chapitre_plan`
  ADD CONSTRAINT `fk_chapitre_plan` FOREIGN KEY (`idplan_travail`) REFERENCES `plan_travail` (`idplan_travail`) ON DELETE CASCADE;

--
-- Contraintes pour la table `details_etats_besoin`
--
ALTER TABLE `details_etats_besoin`
  ADD CONSTRAINT `fk_details_etat_besoin` FOREIGN KEY (`etat_besoin_id`) REFERENCES `etats_besoin` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `detail_rapport_financier`
--
ALTER TABLE `detail_rapport_financier`
  ADD CONSTRAINT `detail_rapport_financier_ibfk_1` FOREIGN KEY (`id_rapport`) REFERENCES `rapport_financier` (`id_rapport`),
  ADD CONSTRAINT `detail_rapport_financier_ibfk_2` FOREIGN KEY (`id_compte_comptable`) REFERENCES `compte_comptable` (`id_compte`),
  ADD CONSTRAINT `detail_rapport_financier_ibfk_3` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `dette_evaluation`
--
ALTER TABLE `dette_evaluation`
  ADD CONSTRAINT `fk_evaluation_dette` FOREIGN KEY (`id_dette`) REFERENCES `dette_etudiant` (`id_dette`) ON DELETE CASCADE;

--
-- Contraintes pour la table `dette_historique`
--
ALTER TABLE `dette_historique`
  ADD CONSTRAINT `fk_historique_dette` FOREIGN KEY (`id_dette`) REFERENCES `dette_etudiant` (`id_dette`) ON DELETE CASCADE;

--
-- Contraintes pour la table `echelonnement_paiement`
--
ALTER TABLE `echelonnement_paiement`
  ADD CONSTRAINT `fk_echelonnement_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `affectation_frais` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `etats_besoin`
--
ALTER TABLE `etats_besoin`
  ADD CONSTRAINT `fk_besoin_categorie` FOREIGN KEY (`categorie_budget_id`) REFERENCES `categories_budget` (`id`),
  ADD CONSTRAINT `fk_besoin_demandeur` FOREIGN KEY (`demandeur_id`) REFERENCES `agent` (`idAgent`),
  ADD CONSTRAINT `fk_besoin_exercice` FOREIGN KEY (`exercice_id`) REFERENCES `exercices_budgetaires` (`id`),
  ADD CONSTRAINT `fk_besoin_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`idService`);

--
-- Contraintes pour la table `etudiants_palmares_archives`
--
ALTER TABLE `etudiants_palmares_archives`
  ADD CONSTRAINT `fk_etudiants_palmares_archives` FOREIGN KEY (`idpalmares`) REFERENCES `palmares_archives` (`idpalmares`) ON DELETE CASCADE;

--
-- Contraintes pour la table `etudiant_documents`
--
ALTER TABLE `etudiant_documents`
  ADD CONSTRAINT `fk_etudiant_documents_etudiant` FOREIGN KEY (`idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_etudiant_documents_obligatoire` FOREIGN KEY (`document_obligatoire_id`) REFERENCES `documents_obligatoires` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `etudiant_documents_historique`
--
ALTER TABLE `etudiant_documents_historique`
  ADD CONSTRAINT `fk_historique_document` FOREIGN KEY (`document_id`) REFERENCES `etudiant_documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `etudiant_historique`
--
ALTER TABLE `etudiant_historique`
  ADD CONSTRAINT `fk_historique_annee_acad` FOREIGN KEY (`idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_historique_etudiant` FOREIGN KEY (`idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_historique_promotion` FOREIGN KEY (`idpromotion`) REFERENCES `promotion` (`idpromotion`) ON DELETE CASCADE;

--
-- Contraintes pour la table `historique_visites`
--
ALTER TABLE `historique_visites`
  ADD CONSTRAINT `fk_historique_visite` FOREIGN KEY (`idVisite`) REFERENCES `visites` (`idVisite`) ON DELETE CASCADE;

--
-- Contraintes pour la table `journal_comptable`
--
ALTER TABLE `journal_comptable`
  ADD CONSTRAINT `journal_comptable_ibfk_1` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `ligne_budget`
--
ALTER TABLE `ligne_budget`
  ADD CONSTRAINT `ligne_budget_ibfk_1` FOREIGN KEY (`id_budget`) REFERENCES `budget_old` (`id_budget`),
  ADD CONSTRAINT `ligne_budget_ibfk_2` FOREIGN KEY (`id_compte_comptable`) REFERENCES `compte_comptable` (`id_compte`),
  ADD CONSTRAINT `ligne_budget_ibfk_3` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `ligne_ecriture_comptable`
--
ALTER TABLE `ligne_ecriture_comptable`
  ADD CONSTRAINT `ligne_ecriture_comptable_ibfk_1` FOREIGN KEY (`id_ecriture`) REFERENCES `ecriture_comptable` (`id_ecriture`),
  ADD CONSTRAINT `ligne_ecriture_comptable_ibfk_2` FOREIGN KEY (`id_compte`) REFERENCES `compte_comptable` (`id_compte`),
  ADD CONSTRAINT `ligne_ecriture_comptable_ibfk_3` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `log_operation`
--
ALTER TABLE `log_operation`
  ADD CONSTRAINT `log_operation_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `mode_paiement`
--
ALTER TABLE `mode_paiement`
  ADD CONSTRAINT `mode_paiement_ibfk_1` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `operation_bancaire`
--
ALTER TABLE `operation_bancaire`
  ADD CONSTRAINT `operation_bancaire_ibfk_1` FOREIGN KEY (`id_compte_bancaire`) REFERENCES `compte_bancaire` (`id_compte_bancaire`),
  ADD CONSTRAINT `operation_bancaire_ibfk_2` FOREIGN KEY (`id_compte_comptable`) REFERENCES `compte_comptable` (`id_compte`),
  ADD CONSTRAINT `operation_bancaire_ibfk_3` FOREIGN KEY (`id_budget`) REFERENCES `budget_old` (`id_budget`),
  ADD CONSTRAINT `operation_bancaire_ibfk_4` FOREIGN KEY (`id_ligne_budget`) REFERENCES `ligne_budget` (`id_ligne_budget`),
  ADD CONSTRAINT `operation_bancaire_ibfk_5` FOREIGN KEY (`id_caisse`) REFERENCES `caisse` (`id_caisse`),
  ADD CONSTRAINT `operation_bancaire_ibfk_6` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `operation_caisse`
--
ALTER TABLE `operation_caisse`
  ADD CONSTRAINT `operation_caisse_ibfk_1` FOREIGN KEY (`id_caisse`) REFERENCES `caisse` (`id_caisse`),
  ADD CONSTRAINT `operation_caisse_ibfk_2` FOREIGN KEY (`id_compte_comptable`) REFERENCES `compte_comptable` (`id_compte`),
  ADD CONSTRAINT `operation_caisse_ibfk_3` FOREIGN KEY (`id_budget`) REFERENCES `budget_old` (`id_budget`),
  ADD CONSTRAINT `operation_caisse_ibfk_4` FOREIGN KEY (`id_ligne_budget`) REFERENCES `ligne_budget` (`id_ligne_budget`),
  ADD CONSTRAINT `operation_caisse_ibfk_5` FOREIGN KEY (`id_compte_bancaire`) REFERENCES `compte_bancaire` (`id_compte_bancaire`),
  ADD CONSTRAINT `operation_caisse_ibfk_6` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `paiements_frais`
--
ALTER TABLE `paiements_frais`
  ADD CONSTRAINT `fk_paiements_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `affectation_frais` (`id`),
  ADD CONSTRAINT `fk_paiements_etudiant` FOREIGN KEY (`etudiant_id`) REFERENCES `etudiant` (`idetudiant`),
  ADD CONSTRAINT `fk_paiements_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`);

--
-- Contraintes pour la table `paiements_tranches`
--
ALTER TABLE `paiements_tranches`
  ADD CONSTRAINT `fk_paiement_tranche_echelonnement` FOREIGN KEY (`echelonnement_id`) REFERENCES `echelonnement_paiement` (`id`),
  ADD CONSTRAINT `fk_paiement_tranche_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `paiements_frais` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `palmares_etudiant`
--
ALTER TABLE `palmares_etudiant`
  ADD CONSTRAINT `fk_palmares_etudiant_palmares` FOREIGN KEY (`id_palmares`) REFERENCES `palmares_archive` (`id_palmares`) ON DELETE CASCADE;

--
-- Contraintes pour la table `palmares_historique`
--
ALTER TABLE `palmares_historique`
  ADD CONSTRAINT `fk_historique_palmares` FOREIGN KEY (`id_palmares`) REFERENCES `palmares_archive` (`id_palmares`) ON DELETE CASCADE;

--
-- Contraintes pour la table `plan_travail`
--
ALTER TABLE `plan_travail`
  ADD CONSTRAINT `fk_plan_sujet` FOREIGN KEY (`idsujets`) REFERENCES `sujets` (`idsujets`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rapport_financier`
--
ALTER TABLE `rapport_financier`
  ADD CONSTRAINT `rapport_financier_ibfk_1` FOREIGN KEY (`id_succursale`) REFERENCES `succursale` (`id_succursale`),
  ADD CONSTRAINT `rapport_financier_ibfk_2` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `section_chapitre`
--
ALTER TABLE `section_chapitre`
  ADD CONSTRAINT `fk_section_chapitre` FOREIGN KEY (`idchapitre_plan`) REFERENCES `chapitre_plan` (`idchapitre_plan`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  ADD CONSTRAINT `fk_sessions_agent` FOREIGN KEY (`idAgent`) REFERENCES `agent` (`idAgent`),
  ADD CONSTRAINT `fk_sessions_caisse` FOREIGN KEY (`caisse_id`) REFERENCES `caisses` (`id`);

--
-- Contraintes pour la table `validations_etats_besoin`
--
ALTER TABLE `validations_etats_besoin`
  ADD CONSTRAINT `fk_validations_etat_besoin` FOREIGN KEY (`etat_besoin_id`) REFERENCES `etats_besoin` (`id`),
  ADD CONSTRAINT `fk_validations_validateur` FOREIGN KEY (`validateur_id`) REFERENCES `agent` (`idAgent`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
