-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 11 août 2025 à 20:03
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
-- Structure de la table `acces_archive`
--

CREATE TABLE `acces_archive` (
  `idacces` int(11) NOT NULL,
  `idarchive` int(11) NOT NULL,
  `idRole` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `activite_projet`
--

CREATE TABLE `activite_projet` (
  `idActivite_projet` int(11) NOT NULL,
  `intitule` text DEFAULT NULL,
  `dateDebut` date DEFAULT NULL,
  `dateFin` date DEFAULT NULL,
  `budget` decimal(14,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `etatActivite` varchar(45) DEFAULT NULL,
  `Projet_idProjet` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `actualites`
--

CREATE TABLE `actualites` (
  `idactualite` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `date_publication` datetime DEFAULT current_timestamp(),
  `date_expiration` datetime DEFAULT NULL,
  `cible` enum('Etudiants','Enseignants','Tous') NOT NULL DEFAULT 'Tous',
  `niveau` enum('Global','Section') NOT NULL DEFAULT 'Global',
  `idsection` int(11) DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Déchargement des données de la table `configuration_universite`
--

INSERT INTO `configuration_universite` (`id`, `type_etablissement`, `nom`, `sigle`, `adresse`, `ville`, `pays`, `telephone`, `email`, `site_web`, `logo`, `ministere_tutelle`, `nom_responsable`, `titre_responsable`, `signature_responsable`, `cachet`, `date_modification`, `credit_heure`) VALUES
(1, 'Institut Supérieur', 'INSTITUT NATIONAL DE BATIMENT ET TRAVAUX PUBLICS', 'INBTP', '21, Avenue de la Montagne , Joli Parc, Ngaliema', 'Kinshasa', NULL, '+243 812147575', 'contact@inbtp.ac.cd', 'http://www.inbtp.ac.cd', 'uploads/config/logo_1754768397_logo-inbtp.PNG', 'ENSEIGNEMENT SUPERIEUR ET UNIVERSITAIRE', 'TSHIBANGU MUNYENZE CEDRICK', 'Directeur Général', 'uploads/config/signature_responsable_1741610875_signature KABI.jpg', 'uploads/config/cachet_1741610875_SignatureBDOM.png', '2025-08-09 19:39:57', 15);

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
-- Structure de la table `credit_ue`
--

CREATE TABLE `credit_ue` (
  `idcredit` int(11) NOT NULL,
  `idUE` int(11) NOT NULL,
  `nombre_credits` int(11) NOT NULL DEFAULT 0,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
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
-- Structure de la table `details_rapprochements`
--

CREATE TABLE `details_rapprochements` (
  `id` int(11) NOT NULL,
  `rapprochement_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `date_operation` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `est_debit` tinyint(1) DEFAULT 0,
  `est_rapproche` tinyint(1) DEFAULT 0,
  `est_ajustement` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
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
-- Structure de la table `documents_inscription_externe`
--

CREATE TABLE `documents_inscription_externe` (
  `id` int(11) NOT NULL,
  `inscription_externe_id` int(11) NOT NULL,
  `lien_document_id` int(11) NOT NULL,
  `nom_fichier_original` varchar(255) NOT NULL,
  `nom_fichier_stocke` varchar(255) NOT NULL,
  `chemin_fichier` varchar(500) NOT NULL,
  `taille_fichier` bigint(20) DEFAULT NULL,
  `type_mime` varchar(100) DEFAULT NULL,
  `statut_validation` enum('En attente','Validé','Rejeté') DEFAULT 'En attente',
  `commentaire_validation` text DEFAULT NULL,
  `date_upload` datetime DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `id_validateur` int(11) DEFAULT NULL
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
-- Structure de la table `doc_activite`
--

CREATE TABLE `doc_activite` (
  `idDoc_activite` int(11) NOT NULL,
  `titre` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dateDocument` date DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Activite_projet_idActivite_projet` int(11) NOT NULL
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
-- Structure de la table `etats_paiement`
--

CREATE TABLE `etats_paiement` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `mois` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `type_agent` enum('Permanent','Visiteur','Tous') NOT NULL DEFAULT 'Tous',
  `departement_id` int(11) DEFAULT NULL,
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
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etat_de_besoin`
--

CREATE TABLE `etat_de_besoin` (
  `idEtat_de_besoin` int(11) NOT NULL,
  `dateElaboration` date DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `libelle` varchar(145) DEFAULT NULL,
  `montant` decimal(14,2) DEFAULT NULL,
  `validation1` int(11) DEFAULT NULL,
  `validation2` int(11) DEFAULT NULL,
  `statut` varchar(45) DEFAULT 'Non Paye',
  `idUser` int(11) DEFAULT NULL,
  `Service_idService` int(11) NOT NULL,
  `idLigne_depense` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `etudiant_tempon`
--

CREATE TABLE `etudiant_tempon` (
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
-- Structure de la table `excel_tokens`
--

CREATE TABLE `excel_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT 1
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
-- Structure de la table `frais_soutenance`
--

CREATE TABLE `frais_soutenance` (
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

CREATE TABLE `grade` (
  `idgrade` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type_agent` enum('Enseignant','Administratif','Recherche') DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grilles_anciennes_ecue`
--

CREATE TABLE `grilles_anciennes_ecue` (
  `id` int(11) NOT NULL,
  `ue_id` int(11) NOT NULL,
  `code_ecue` varchar(50) DEFAULT NULL,
  `designation_ecue` varchar(255) NOT NULL,
  `coefficient` decimal(5,2) NOT NULL DEFAULT 1.00,
  `ordre_affichage` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grilles_anciennes_etudiants`
--

CREATE TABLE `grilles_anciennes_etudiants` (
  `id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `matricule` varchar(50) NOT NULL,
  `noms` varchar(255) NOT NULL,
  `ordre_affichage` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grilles_anciennes_imports`
--

CREATE TABLE `grilles_anciennes_imports` (
  `id` int(11) NOT NULL,
  `annee_academique` varchar(50) NOT NULL,
  `session` varchar(100) NOT NULL,
  `semestre` varchar(50) NOT NULL,
  `promotion` varchar(255) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `fichier_origine` varchar(255) NOT NULL,
  `date_import` datetime DEFAULT current_timestamp(),
  `mapping_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mapping_config`)),
  `nombre_etudiants` int(11) DEFAULT 0,
  `nombre_ues` int(11) DEFAULT 0,
  `nombre_ecues` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grilles_anciennes_notes`
--

CREATE TABLE `grilles_anciennes_notes` (
  `id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `ecue_id` int(11) NOT NULL,
  `note_cc` decimal(5,2) DEFAULT NULL,
  `note_examen` decimal(5,2) DEFAULT NULL,
  `note_finale` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grilles_anciennes_resultats`
--

CREATE TABLE `grilles_anciennes_resultats` (
  `id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `ue_id` int(11) DEFAULT NULL,
  `import_id` int(11) NOT NULL,
  `moyenne` decimal(5,2) DEFAULT NULL,
  `credits_valides` decimal(5,2) DEFAULT 0.00,
  `credits_total` decimal(5,2) DEFAULT 0.00,
  `est_valide` tinyint(1) DEFAULT 0,
  `mention` varchar(50) DEFAULT NULL,
  `type_resultat` enum('ue','semestre','annuel') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grilles_anciennes_ue`
--

CREATE TABLE `grilles_anciennes_ue` (
  `id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `code_ue` varchar(50) NOT NULL,
  `designation_ue` varchar(255) NOT NULL,
  `credits` decimal(5,2) NOT NULL DEFAULT 0.00,
  `semestre` varchar(10) DEFAULT 'S1',
  `ordre_affichage` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Structure de la table `import_etudiants_ordre`
--

CREATE TABLE `import_etudiants_ordre` (
  `idimport` int(11) NOT NULL,
  `fichier` varchar(255) NOT NULL,
  `date_import` datetime DEFAULT current_timestamp(),
  `idfrais` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `idsection` int(11) DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inscriptions_externes`
--

CREATE TABLE `inscriptions_externes` (
  `id` int(11) NOT NULL,
  `lien_inscription_id` int(11) NOT NULL,
  `reference_inscription` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `postnom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(50) NOT NULL,
  `date_naissance` date NOT NULL,
  `lieu_naissance` varchar(100) NOT NULL,
  `sexe` enum('M','F') NOT NULL,
  `nationalite` varchar(100) NOT NULL,
  `adresse_complete` text NOT NULL,
  `personne_contact` varchar(200) DEFAULT NULL,
  `telephone_contact` varchar(50) DEFAULT NULL,
  `donnees_supplementaires` text DEFAULT NULL COMMENT 'JSON pour données additionnelles',
  `statut` enum('En cours','Complète','Validée','Rejetée') DEFAULT 'En cours',
  `commentaire_admin` text DEFAULT NULL,
  `date_soumission` datetime DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `id_validateur` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
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
-- Structure de la table `inventaire`
--

CREATE TABLE `inventaire` (
  `id_inventaire` int(11) NOT NULL,
  `numero_inventaire` varchar(20) NOT NULL,
  `date_inventaire` date NOT NULL,
  `id_depot` int(11) NOT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
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
-- Structure de la table `liens_inscription_externe`
--

CREATE TABLE `liens_inscription_externe` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `promotion_id` int(11) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `token_unique` varchar(255) NOT NULL,
  `url_complete` varchar(500) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `max_inscriptions` int(11) DEFAULT NULL COMMENT 'Nombre maximum d''inscriptions autorisées',
  `nb_inscriptions_actuelles` int(11) DEFAULT 0,
  `est_actif` tinyint(1) DEFAULT 1,
  `utiliser_docs_defaut` tinyint(1) DEFAULT 1 COMMENT 'Utiliser les documents par défaut configurés',
  `documents_personnalises` text DEFAULT NULL COMMENT 'JSON des documents spécifiques si pas défaut',
  `message_accueil` text DEFAULT NULL,
  `message_succes` text DEFAULT NULL,
  `couleur_theme` varchar(7) DEFAULT '#007bff',
  `logo_personnalise` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lien_inscription_documents`
--

CREATE TABLE `lien_inscription_documents` (
  `id` int(11) NOT NULL,
  `lien_inscription_id` int(11) NOT NULL,
  `document_obligatoire_id` int(11) DEFAULT NULL COMMENT 'Référence au document standard si utilisé',
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `est_obligatoire` tinyint(1) DEFAULT 1,
  `delai_jours` int(11) DEFAULT NULL,
  `ordre_affichage` int(11) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp()
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
-- Structure de la table `paiement_soutenance`
--

CREATE TABLE `paiement_soutenance` (
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
  `idUser` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a créé/modifié',
  `pourcentage_avancement` int(11) DEFAULT 0 COMMENT 'Pourcentage d''avancement global du plan basé sur les chapitres validés'
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
-- Structure de la table `prevision_production`
--

CREATE TABLE `prevision_production` (
  `idPrevision_production` int(11) NOT NULL,
  `dateDebut` date DEFAULT NULL,
  `dateFin` date DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Laboratoire_production_idLaboratoire_production` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `rapprochements_bancaires`
--

CREATE TABLE `rapprochements_bancaires` (
  `id` int(11) NOT NULL,
  `compte_bancaire_id` int(11) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `solde_initial_releve` decimal(15,2) NOT NULL,
  `solde_final_releve` decimal(15,2) NOT NULL,
  `solde_calcule_systeme` decimal(15,2) NOT NULL,
  `difference` decimal(15,2) NOT NULL,
  `statut` enum('En cours','Terminé','Validé') NOT NULL DEFAULT 'En cours',
  `pieces_jointes` varchar(255) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL
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
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `orientation_idorientation` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `est_chef` tinyint(1) DEFAULT 0 COMMENT 'Indique si ce responsable est le chef de département (1=Oui, 0=Non)'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `responsable_section`
--

CREATE TABLE `responsable_section` (
  `idresponsable_section` int(11) NOT NULL,
  `noms` varchar(245) DEFAULT NULL,
  `fonction` varchar(145) DEFAULT NULL,
  `est_chef` tinyint(1) DEFAULT 0,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
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
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `boite_postale` varchar(50) DEFAULT NULL,
  `site_web` varchar(255) DEFAULT NULL,
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
-- Structure de la table `sujet_historique`
--

CREATE TABLE `sujet_historique` (
  `id_historique` int(11) NOT NULL,
  `idsujets` int(11) NOT NULL,
  `action` enum('Création','Modification','Validation','Reformulation demandée','Reformulation acceptée','Reformulation refusée') NOT NULL,
  `intitule_avant` text DEFAULT NULL,
  `intitule_apres` text DEFAULT NULL,
  `statut_avant` varchar(50) DEFAULT NULL,
  `statut_apres` varchar(50) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_action` timestamp NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL,
  `type_utilisateur` enum('Etudiant','Enseignant','Admin') DEFAULT NULL,
  `details_modification` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_modification`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sujet_reformulations`
--

CREATE TABLE `sujet_reformulations` (
  `id_reformulation` int(11) NOT NULL,
  `idsujets` int(11) NOT NULL,
  `etudiant_idetudiant` int(11) NOT NULL,
  `intitule_propose` text NOT NULL,
  `idSpecialisation_propose` int(11) DEFAULT NULL,
  `idDirecteur_propose` int(11) DEFAULT NULL,
  `idEncadreur_propose` int(11) DEFAULT NULL,
  `justification_etudiant` text NOT NULL,
  `commentaire_commission_original` text DEFAULT NULL,
  `statut_reformulation` enum('En attente','Acceptée','Refusée') NOT NULL DEFAULT 'En attente',
  `commentaire_reponse` text DEFAULT NULL,
  `date_proposition` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_traitement` timestamp NULL DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

--
-- Déchargement des données de la table `t_modules`
--

INSERT INTO `t_modules` (`idMod`, `nomMod`, `package`) VALUES
(1, 'Tableau de bord', 'configuration'),
(2, 'Configuration', 'configuration'),
(3, 'Gestion des étudiants', 'etudiants'),
(4, 'GRH', 'grh'),
(8, 'Réception', 'reception'),
(9, 'Projet', 'projet'),
(11, 'Finances', 'finance'),
(12, 'Archivage Numérique', 'document'),
(18, 'Indicateurs de Gestion', 'indicateur'),
(20, 'Gestion Enseignants', 'cours'),
(21, 'Unités de Recherche', 'ur'),
(23, 'Bibliothèque Numérique', 'bibliotheque'),
(24, 'Espace Enseignant-chercheur', 'recherche'),
(25, 'Section ou Faculté', 'depot'),
(26, 'Recherche', 'recherche'),
(28, 'Enseignements', 'enseignement'),
(29, 'Jury', 'deliberation'),
(55, 'Présences', 'cours'),
(56, 'Laboratoire', 'laboratoire'),
(59, 'Direction services académiques', 'academique'),
(60, 'Cellule LMD', 'lmd');

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

--
-- Déchargement des données de la table `t_permissions`
--

INSERT INTO `t_permissions` (`idPerm`, `idMod`, `codePerm`, `nomPerm`, `descPerm`) VALUES
(1, 1, 'Consulter', 'dashboard', 'Tableau de bord'),
(2, 2, 'Consulter', 'roles', 'Roles'),
(3, 2, 'Consulter', 'modules', 'Modules'),
(4, 2, 'Consulter', 'users', 'Utilisateurs'),
(5, 4, 'ajouter', 'agent.add', 'Encodage du Personnel'),
(6, 4, 'modifier', 'agent.edit', 'Capture Photo'),
(7, 4, 'consulter', 'agent.list', 'Listes du Personnel'),
(8, 4, 'ajouter', 'agent.famille.add', 'Encoder Dossier famille'),
(9, 4, 'modifier', 'agent.famille.edit', 'MAJ Dossier Famille'),
(10, 4, 'consulter', 'agent.famille.list', 'Données familiales du Personnel'),
(11, 4, 'ajouter', 'agent.contrat.add', 'Notifications et Mandats'),
(12, 4, 'modifier', 'agent.contrat.edit', 'Mise à jour des Mandats'),
(13, 4, 'consulter', 'agent.contrat.list', 'Liste des contrats'),
(14, 4, 'ajouter', 'agent.doc.add', 'Gestion des documents'),
(15, 4, 'modifier', 'agent.doc.edit', 'Modifier document'),
(16, 4, 'consulter', 'agent.doc.list', 'Liste des documents'),
(17, 4, 'ajouter', 'agent.pres.add', 'Encodage des Présences'),
(18, 4, 'consulter', 'agent.pres.list', 'Consulter présence'),
(19, 1, 'dashboard', 'tableau.finance', 'Tableau de bord Financier'),
(20, 1, 'dashboard', 'tableau.logistique', 'Tableau de bord Logistique'),
(21, 5, 'ajouter', 'compte.add', 'Ajouter un compte'),
(22, 5, 'modifier', 'compte.edit', 'Modifier un compte'),
(23, 5, 'consulter', 'compte.list', 'Afficher plan comptable'),
(24, 5, 'ajouter', 'journal.add', 'Ajouter un journal'),
(25, 5, 'modifier', 'journal.edit', 'Modifier un journal'),
(26, 5, 'ajouter', 'banque.add', 'Ajouter une Banque'),
(27, 5, 'modifier', 'banque.edit', 'Modifier une Banque'),
(28, 5, 'ajouter', 'ecriture.add', 'Passer écriture'),
(29, 5, 'modifier', 'ecriture.edit', 'Modifier Ecriture'),
(32, 5, 'ajouter', 'facture_cl.add', 'Ajouter facture client'),
(33, 5, 'modifier', 'facture_cl.edit', 'Modifier facture client'),
(34, 5, 'consulter', 'facture_cl.list', 'Liste factures clients'),
(35, 5, 'consulter', 'facture_cl.historique', 'Historique factures clients'),
(36, 5, 'ajouter', 'facture_frs.add', 'Ajouter facture fournisseur'),
(37, 5, 'modifier', 'facture_frs.edit', 'Modifier facture fournisseur'),
(38, 5, 'consulter', 'facture_frs.list', 'Liste factures fournisseurs'),
(39, 5, 'consulter', 'facture_frs.historique', 'Historique factures fournisseurs'),
(40, 5, 'ajouter', 'paiement.facture.client.add', 'Paiement Facture client'),
(41, 5, 'modifier', 'paiement.facture.client.edit', 'Annuler paiement facture client'),
(42, 5, 'ajouter', 'paiement.facture.fourni.add', 'Paiement facture Fournisseur'),
(43, 5, 'modifier', 'paiement.facture.fourni.edit', 'Annuler Paiement Facture fournisseur'),
(44, 6, 'ajouter', 'budget.recette.add', 'Ajouter un Budget Récette'),
(45, 6, 'modifier', 'budget.recette.edit', 'Budgets Récettes'),
(46, 6, 'visualiser', 'budget.recette.view', 'Visualiser budget recette'),
(47, 6, 'ajouter', 'budget.recette.grp.add', 'Ajouter groupe recette'),
(48, 6, 'modifier', 'budget.recette.grp.edit', 'Configurer budget récette'),
(49, 6, 'ajouter', 'budget.recette.ligne.add', 'Ajouter ligne récette'),
(50, 6, 'modifier', 'budget.recette.ligne.edit', 'Modifier ligne récette'),
(51, 6, 'ajouter', 'budget.depense.add', 'Ajouter budget dépense'),
(52, 6, 'modifier', 'budget.depense.edit', 'Budgets Dépenses'),
(53, 6, 'visualiser', 'budget.depense.list', 'Visualiser budget dépense'),
(54, 6, 'ajouter', 'budget.depense.groupe.add', 'Ajouter groupe de dépense'),
(55, 6, 'modifier', 'budget.depense.groupe.edit', 'Configurer budget dépense'),
(56, 6, 'ajouter', 'budget.depense.ligne.add', 'Ajouter ligne dépense'),
(57, 6, 'modifier', 'budget.depense.ligne.edit', 'Modifier ligne dépense'),
(58, 5, 'ajouter', 'recette.add', 'Ajouter récette'),
(59, 5, 'modifier', 'recette.edit', 'Modifier récette'),
(60, 5, 'visualiser', 'recette.list', 'Visualiser récettes'),
(61, 5, 'ajouter', 'depense.add', 'Ajouter dépense'),
(62, 5, 'modifier', 'depense.edit', 'Modifier dépense'),
(63, 5, 'visualiser', 'depense.list', 'Visualiser les dépenses'),
(65, 6, 'visualiser', 'budget.execution', 'Exécution budgétaire'),
(66, 7, 'ajouter', 'etat_besoin.add', 'Ajouter état de besoins'),
(67, 7, 'modifier', 'etat_besoin.edit', 'MAJ état de besoins'),
(68, 7, 'visualiser', 'etat_besoin.paye', 'Payer état de besoins'),
(69, 7, 'modifier', 'etat_besoin.valid', 'Valider état de besoins'),
(77, 8, 'ajouter', 'courriel.add', 'Enregistrer un courriel'),
(78, 8, 'modifier', 'courriel.edit', 'Modifier un couriel'),
(79, 8, 'visualiser', 'courriel.list', 'Visualiser les couriels'),
(80, 8, 'modifier', 'courriel.coment', 'Commenter un courriel'),
(81, 9, 'ajouter', 'projet.add', 'Ajouter projet'),
(83, 9, 'visualiser', 'projet.view', 'Liste des projets'),
(84, 9, 'ajouter', 'activite.add', 'Ajouter activité'),
(86, 9, 'visualiser', 'activite.list', 'Liste des activités'),
(87, 9, 'ajouter', 'document.add', 'Ajouter document activité'),
(89, 9, 'visualiser', 'document.list', 'Liste des documents activités'),
(90, 10, 'ajouter', 'labo.add', 'Ajouter laboratoire'),
(91, 2, 'ajouter', 'structure.add', 'Gérer campus'),
(92, 2, 'ajouter', 'service.add', 'Encoder Service'),
(93, 2, 'modifier', 'service.edit', 'Modifier Service'),
(94, 10, 'modifier', 'labo.edit', 'Modifier laboratoire'),
(95, 10, 'ajouter', 'prevision.add', 'Ajouter une prévision'),
(96, 10, 'modifier', 'prevision.edit', 'Modifier une prévision'),
(97, 10, 'ajouter', 'production.add', 'Ajouter une production'),
(98, 10, 'modifier', 'production.edit', 'Modifier une production'),
(99, 10, 'visualiser', 'production.list', 'Visualiser une production'),
(100, 10, 'ajouter', 'produit.add', 'Ajouter un produit'),
(101, 10, 'modifier', 'produit.edit', 'Modifier un produit'),
(102, 10, 'visualiser', 'produit.list', 'Liste des produits'),
(103, 10, 'ajouter', 'pf.add', 'Sortie produit fini labo'),
(104, 10, 'modifier', 'pf.edit', 'Modifier sortie PF'),
(105, 10, 'ajouter', 'matiere.add', 'Entrée MP'),
(106, 10, 'ajouter', 'emballage.add', 'Entrée emballage'),
(107, 2, 'visualiser', 'service.list', 'Liste des services'),
(108, 5, 'ajouter', 'client.add', 'Ajouter client'),
(109, 5, 'modifier', 'client.edit', 'Modifier client'),
(110, 5, 'ajouter', 'fournisseur.add', 'Ajouter Fournisseur'),
(111, 5, 'modifier', 'fournisseur.edit', 'Modifier Fournisseur'),
(112, 5, 'visualiser', 'journal.visualiser', 'Journal des opérations'),
(114, 12, 'ajouter', 'doc.public.add', 'Ajouter document publique'),
(115, 12, 'ajouter', 'doc.prive.add', 'Ajouter document privé'),
(116, 12, 'consulter', 'doc.public.view', 'Consulter document publique'),
(117, 12, 'consulter', 'doc.prive.view', 'Consulter document privé'),
(118, 12, 'ajouter', 'categorie.add', 'Ajouter classeur'),
(119, 12, 'visualiser', 'doc.consulter', 'Consulter par classeur'),
(120, 14, 'consulter', 'liste.dettes', 'Liste des déttes'),
(121, 14, 'consulter', 'liste.creances', 'Liste des créances'),
(122, 14, 'consulter', 'journal.operations', 'Journal de mes opérations automatiques'),
(125, 14, 'consulter', 'cloture.periodique', 'Rapport Financier'),
(126, 14, 'consulter', 'compte.situation', 'Situation automatique d\'un compte'),
(127, 14, 'consulter', 'recette.liste', 'Liste des récettes'),
(128, 14, 'consulter', 'depense.liste', 'Liste des dépenses'),
(129, 16, 'consulter', 'execution.budget', 'Exécution budgétaire'),
(130, 16, 'consulter', 'etat_besoin.list', 'Liste des états de bésoins'),
(132, 13, 'consulter', 'agent.listepdf', 'Liste des agents'),
(133, 13, 'consulter', 'presence.list', 'Liste des présences'),
(134, 13, 'consulter', 'agent.dossier', 'Dossier de l\'agent'),
(138, 19, 'consulter', 'rapport.journal', 'Journal comptable'),
(139, 19, 'consulter', 'balance', 'Balance Générale'),
(140, 19, 'consulter', 'bilan', 'Bilan'),
(141, 18, 'ajouter', 'categorie.indicateur', 'Catégories indicateurs'),
(142, 18, 'ajouter', 'indicateur.add', 'Gestion des indicateurs'),
(143, 18, 'ajouter', 'encoder', 'Encoder indicateur'),
(144, 18, 'consulter', 'rapport.indicateur', 'Rapport Indicateurs'),
(145, 19, 'consulter', 'historique.compte', 'Historique compte'),
(146, 3, 'ajouter', 'etudiant.inscrit', 'Inscrire étudiant'),
(147, 3, 'ajouter', 'reinscription_etudiants', 'Ré-inscrire étudiant'),
(148, 3, 'consulter', 'liste_etudiants', 'Parcours académique'),
(149, 3, 'ajouter', 'verify-card', 'Cartes d\'étudiants'),
(157, 21, 'ajouter', 'affecation_ur', 'Affectation des enseignants dans les UR'),
(158, 21, 'ajouter', 'unite_recherche', 'Création et mise à jour des UR'),
(159, 7, 'ajouter', 'salle.add', 'Gestion des salles'),
(160, 22, 'ajouter', 'note.add', 'Saisie et enregistrement des notes'),
(162, 22, 'ajouter', 'dette.add', 'Gestion des crédits'),
(163, 22, 'consulter', 'grille', 'Consulter les grilles de délibérations'),
(164, 22, 'consulter', 'palmares', 'Imprimer les palmares'),
(165, 22, 'consulter', 'pv_deliv', 'Procès Verbal de délibération'),
(166, 3, 'consulter', 'documents_etudiants', 'Rélevé des notes'),
(167, 4, 'ajouter', 'conges.list', 'Demandes des congés'),
(171, 23, 'ajouter', 'bilio_add', 'Alimenter la biliothèque'),
(172, 24, 'ajouter', 'projet.recherche', 'Mes supervisions'),
(173, 24, 'ajouter', 'projet.taches', 'Tâches de mes étudiants'),
(178, 2, 'ajouter', 'faculte', 'Gérer faculté/Section'),
(179, 2, 'ajouter', 'orientation', 'Gérer les orientations'),
(180, 2, 'ajouter', 'promotion', 'Gérer Promotion'),
(181, 2, 'ajouter', 'annee', 'Années académiques'),
(182, 26, 'ajouter', 'fiches', 'Fiche de suivi d\'un étudiant'),
(183, 26, 'consulter', 'direction', 'Travaux par enseignants'),
(184, 26, 'consulter', 'affectation', 'Re-Affectation des sujets'),
(185, 1, 'consulter', 'tableau.recherche', 'Tableau de bord Recherche'),
(186, 2, 'ajouter', 'config_universite', 'Mon Université'),
(187, 23, 'modifier', 'valider_biblio', 'Validation des publications'),
(188, 27, 'ajouter', 'configuration_frais', 'Configuration des Frais'),
(189, 27, 'ajouter', 'paiement', 'Encoder les paiements'),
(190, 27, 'consulter', 'suivi_paiement', 'Suivi des paiements'),
(191, 2, 'ajouter', 'session', 'Sessions'),
(192, 2, 'ajouter', 'semestre', 'Semestres'),
(193, 26, 'consulter', 'choix_etudiant', 'Validation des sujets'),
(194, 2, 'ajouter', 'grade.add', 'Grades'),
(197, 28, 'ajouter', 'horaires', 'Gestion des horaires'),
(199, 26, 'ajouter', 'soutenances', 'Tableau de contrôle'),
(200, 28, 'ajouter', 'unites_enseignement', 'Gestion des Cours'),
(201, 24, 'consulter', 'mes_cours', 'Mes cours'),
(202, 27, 'ajouter', 'paiement_soutenance', 'Paiement Frais soutenances'),
(203, 26, 'ajouter', 'depot_soutenance', 'Dépôts et soutenances'),
(204, 2, 'ajouter', 'salle', 'Encoder une salle'),
(205, 20, 'ajouter', 'enseignant', 'Affectation'),
(206, 28, 'consulter', 'occupation_salles', 'Occupation des salles'),
(207, 28, 'consulter', 'occupation_promotions', 'Occupation des promotions'),
(208, 26, 'ajouter', 'gestion_jurys', 'Gestion des jurys'),
(209, 2, 'ajouter', 'jury', 'Configurer les jurys'),
(210, 29, 'ajouter', 'config_deliberation', 'Configurer les critères'),
(211, 29, 'ajouter', 'encodage_points', 'Encodage des points'),
(212, 29, 'ajouter', 'seances', 'Délibérations'),
(213, 29, 'consulter', 'grille_notes', 'Générer la Grille'),
(216, 1, 'consulter', 'tableau.inscriptions', 'Tableau des inscriptions'),
(217, 4, 'ajouter', 'agent.add.rapide', 'Gestion des accès'),
(218, 1, 'consulter', 'tableau.grh', 'Tableau de bord GRH'),
(219, 4, 'consulter', 'agent.edition', 'Données de l\'agent'),
(221, 4, 'ajouter', 'conges.types', 'Types de congé'),
(222, 29, 'ajouter', 'recours', 'Encodage des recours'),
(223, 29, 'ajouter', 'validation_recours', 'Traitement des recours'),
(224, 24, 'consulter', 'mes_recours', 'Gestion des recours'),
(225, 29, 'consulter', 'statistiques/recours_jury', 'Tableau de bord des recours'),
(226, 1, 'consulter', 'profile_completion', 'Taubleau de bord complétude'),
(227, 30, 'ajouter', 'produits.list', 'Nouveau article'),
(228, 30, 'ajouter', 'categories.list', 'Catégories'),
(231, 50, 'ajouter', 'stock.entree.list', 'Entrées'),
(232, 50, 'ajouter', 'stock.sortie.list', 'Sorties'),
(233, 50, 'ajouter', 'inventaire.list', 'Inventaires'),
(234, 50, 'ajouter', 'transfert.list', 'Transferts'),
(235, 51, 'ajouter', 'compte.list', 'Plan comptable'),
(236, 2, 'ajouter', 'unite.list', 'Unités de stockage'),
(237, 52, 'ajouter', 'depots.list', 'Dépôts'),
(238, 53, 'ajouter', 'clients.list', 'Clients'),
(239, 54, 'ajouter', 'fournisseurs.list', 'Fournisseurs'),
(240, 2, 'ajouter', 'depot_permissions', 'Dépot permissions'),
(241, 50, 'consulter', 'inventaire.fiche', 'Fiche d\'inventaire'),
(242, 50, 'consulter', 'rapports_stock', 'Rapports de stock'),
(243, 2, 'consulter', 'fraude_presence', 'Fraudes des présences'),
(244, 55, 'ajouter', 'seances.list', 'Séances des présences'),
(245, 56, 'ajouter', 'laboratoire.list', 'Ajouter'),
(246, 56, 'ajouter', 'seance.list', 'Séances du labo'),
(247, 57, 'consulter', 'demandes/demandes.list', 'Demande de prix'),
(248, 57, 'consulter', 'commandes/commandes.list', 'Commandes'),
(249, 57, 'consulter', 'receptions/receptions.list', 'Réceptions'),
(250, 57, 'consulter', 'factures/factures.list', 'Factures fournisseurs'),
(251, 58, 'consulter', 'devis/devis.list', 'Ajouter dévis'),
(252, 3, 'consulter', 'documents_obligatoires', 'Documents obligatoires'),
(253, 3, 'consulter', 'suivi_documents_etudiants', 'Suivi des documents'),
(254, 59, 'ajouter', 'palmares_archives', 'Encoder palmarès'),
(256, 59, 'ajouter', 'suivi_documents_etudiants', 'Contrôle de scolarité'),
(257, 25, 'consulter', 'documents_etudiants', 'Documents de l\'étudiant'),
(258, 29, 'consulter', 'deverrouillage_notes', 'Déverouillage notes'),
(259, 25, 'consulter', 'recours', 'Encodage des recours'),
(260, 29, 'consulter', 'pv_deliberation', 'PV Délibération'),
(261, 59, 'consulter', 'recherche_palmares', 'Recherche dans Palmarès'),
(262, 11, 'ajouter', 'config_finance', 'Configurations finances'),
(263, 11, 'ajouter', 'config_comptes_bancaires', 'Comptes bancaires'),
(264, 11, 'ajouter', 'config_caisses', 'Caisses'),
(265, 11, 'ajouter', 'config_acces_caisses', 'Autorisations caisses'),
(266, 11, 'ajouter', 'sessions_caisse', 'Ouvrir une caisse'),
(267, 11, 'ajouter', 'operations_caisse', 'Opérations de caisse'),
(268, 11, 'ajouter', 'config_categories_budget', 'Masses Budgetaires'),
(269, 11, 'ajouter', 'config_budget', 'Budgets'),
(270, 11, 'ajouter', 'config_exercices_budgetaires', 'Exercices budgétaires'),
(271, 11, 'ajouter', 'categories_frais', 'Catégorie des frais'),
(272, 11, 'ajouter', 'creation_frais', 'Création des frais'),
(273, 11, 'ajouter', 'affectation_frais', 'Affectation frais'),
(274, 11, 'ajouter', 'paiements_etudiants', 'Paiements étudiants'),
(275, 11, 'ajouter', 'rapport/index', 'Rapports Finance'),
(276, 8, 'ajouter', 'rendez_vous.add', 'Rendez-vous'),
(277, 8, 'ajouter', 'mes_rendez_vous', 'Mes rendez-vous'),
(278, 8, 'ajouter', 'visites.dashboard', 'Rapport des visites'),
(279, 2, 'ajouter', 'chef_promotion', 'Chef des promotions'),
(280, 60, 'ajouter', 'gestion_dettes', 'Gestion des dettes'),
(281, 60, 'ajouter', 'rapports_dettes', 'Rapport des dettes'),
(282, 28, 'ajouter', 'suivi_enseignements', 'Suivi des enseignements'),
(283, 28, 'ajouter', 'suivi_global_enseignements', 'Avancement des cours'),
(284, 28, 'consulter', 'tableau_bord_section', 'Tableau de bord Section'),
(285, 3, 'ajouter', 'liens_inscription_externe', 'Inscriptions en ligne'),
(286, 3, 'ajouter', 'tableau_bord_inscriptions', 'Statistiques des inscriptions'),
(287, 29, 'ajouter', 'grilles_anciennes', 'Importer grilles anciennes'),
(288, 59, 'ajouter', 'grilles_anciennes', 'Importer les grilles Anciennes');

-- --------------------------------------------------------

--
-- Structure de la table `t_roles`
--

CREATE TABLE `t_roles` (
  `idRole` int(255) NOT NULL,
  `nomRole` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `t_roles`
--

INSERT INTO `t_roles` (`idRole`, `nomRole`) VALUES
(1, 'Administrateur'),
(16, 'Enseignant'),
(17, 'Commission sujet');

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

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `acces_archive`
--
ALTER TABLE `acces_archive`
  ADD PRIMARY KEY (`idacces`),
  ADD KEY `fk_acces_archive` (`idarchive`),
  ADD KEY `fk_acces_role` (`idRole`),
  ADD KEY `fk_acces_user` (`idUser`);

--
-- Index pour la table `activite_projet`
--
ALTER TABLE `activite_projet`
  ADD PRIMARY KEY (`idActivite_projet`),
  ADD KEY `fk_Activite_projet_Projet1_idx` (`Projet_idProjet`);

--
-- Index pour la table `actualites`
--
ALTER TABLE `actualites`
  ADD PRIMARY KEY (`idactualite`),
  ADD KEY `fk_actualite_section` (`idsection`),
  ADD KEY `fk_actualite_user` (`idUser`);

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
-- Index pour la table `chapitre_plan`
--
ALTER TABLE `chapitre_plan`
  ADD PRIMARY KEY (`idchapitre_plan`),
  ADD KEY `idx_chapitre_plan` (`idplan_travail`),
  ADD KEY `idx_chapitre_numero` (`numero_chapitre`),
  ADD KEY `idx_chapitre_deadline` (`deadline`),
  ADD KEY `idx_chapitre_statut` (`statut`),
  ADD KEY `idx_chapitre_plan_ordre` (`idplan_travail`,`ordre_affichage`),
  ADD KEY `idx_chapitre_deadline_statut` (`deadline`,`statut`);

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
-- Index pour la table `credit_ue`
--
ALTER TABLE `credit_ue`
  ADD PRIMARY KEY (`idcredit`),
  ADD UNIQUE KEY `unique_credit_ue` (`idUE`,`annee_acad_idannee_acad`),
  ADD KEY `fk_credit_ue` (`idUE`),
  ADD KEY `fk_credit_annee` (`annee_acad_idannee_acad`);

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
-- Index pour la table `details_rapprochements`
--
ALTER TABLE `details_rapprochements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_details_rapprochement` (`rapprochement_id`),
  ADD KEY `fk_details_transaction` (`transaction_id`);

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
-- Index pour la table `documents_inscription_externe`
--
ALTER TABLE `documents_inscription_externe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inscription_externe` (`inscription_externe_id`),
  ADD KEY `idx_lien_document` (`lien_document_id`),
  ADD KEY `idx_statut_validation` (`statut_validation`),
  ADD KEY `id_validateur` (`id_validateur`);

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
-- Index pour la table `doc_activite`
--
ALTER TABLE `doc_activite`
  ADD PRIMARY KEY (`idDoc_activite`);

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
  ADD KEY `idx_echange_reponse` (`reponse_a`),
  ADD KEY `idx_echange_chapitre_date` (`idchapitre_plan`,`date_echange`);

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
-- Index pour la table `etats_paiement`
--
ALTER TABLE `etats_paiement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_reference_etat` (`reference`),
  ADD UNIQUE KEY `idx_etat_mensuel` (`mois`,`annee`,`type_agent`,`departement_id`);

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
-- Index pour la table `etudiant_tempon`
--
ALTER TABLE `etudiant_tempon`
  ADD PRIMARY KEY (`idetudiant`);

--
-- Index pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`idevaluation`);

--
-- Index pour la table `excel_tokens`
--
ALTER TABLE `excel_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `evaluation_id` (`evaluation_id`);

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
-- Index pour la table `frais_soutenance`
--
ALTER TABLE `frais_soutenance`
  ADD PRIMARY KEY (`idfrais_soutenance`);

--
-- Index pour la table `grade`
--
ALTER TABLE `grade`
  ADD PRIMARY KEY (`idgrade`);

--
-- Index pour la table `grilles_anciennes_ecue`
--
ALTER TABLE `grilles_anciennes_ecue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ue_ecue` (`ue_id`,`code_ecue`);

--
-- Index pour la table `grilles_anciennes_etudiants`
--
ALTER TABLE `grilles_anciennes_etudiants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_import_matricule` (`import_id`,`matricule`),
  ADD KEY `idx_import_matricule` (`import_id`,`matricule`);

--
-- Index pour la table `grilles_anciennes_imports`
--
ALTER TABLE `grilles_anciennes_imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_annee_session` (`annee_academique`,`session`),
  ADD KEY `idx_promotion` (`promotion`),
  ADD KEY `idx_section_id` (`section_id`);

--
-- Index pour la table `grilles_anciennes_notes`
--
ALTER TABLE `grilles_anciennes_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_etudiant_ecue` (`etudiant_id`,`ecue_id`),
  ADD KEY `idx_etudiant_notes` (`etudiant_id`),
  ADD KEY `idx_ecue_notes` (`ecue_id`);

--
-- Index pour la table `grilles_anciennes_resultats`
--
ALTER TABLE `grilles_anciennes_resultats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `import_id` (`import_id`),
  ADD KEY `idx_etudiant_resultats` (`etudiant_id`,`type_resultat`),
  ADD KEY `idx_ue_resultats` (`ue_id`);

--
-- Index pour la table `grilles_anciennes_ue`
--
ALTER TABLE `grilles_anciennes_ue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_import_ue` (`import_id`,`code_ue`);

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
-- Index pour la table `inscriptions_externes`
--
ALTER TABLE `inscriptions_externes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_inscription` (`reference_inscription`),
  ADD KEY `idx_lien_inscription` (`lien_inscription_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `id_validateur` (`id_validateur`);

--
-- Index pour la table `intervention_jury`
--
ALTER TABLE `intervention_jury`
  ADD PRIMARY KEY (`idintervention`);

--
-- Index pour la table `inventaire`
--
ALTER TABLE `inventaire`
  ADD PRIMARY KEY (`id_inventaire`),
  ADD UNIQUE KEY `numero_inventaire_UNIQUE` (`numero_inventaire`);

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
-- Index pour la table `liens_inscription_externe`
--
ALTER TABLE `liens_inscription_externe`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD UNIQUE KEY `token_unique` (`token_unique`),
  ADD KEY `idx_promotion` (`promotion_id`),
  ADD KEY `idx_annee_acad` (`annee_acad_id`),
  ADD KEY `idx_token` (`token_unique`),
  ADD KEY `idx_actif` (`est_actif`),
  ADD KEY `idUser` (`idUser`);

--
-- Index pour la table `lien_inscription_documents`
--
ALTER TABLE `lien_inscription_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lien_inscription` (`lien_inscription_id`),
  ADD KEY `idx_document_obligatoire` (`document_obligatoire_id`);

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
  ADD KEY `idx_notif_statut` (`statut_lecture`),
  ADD KEY `idx_notif_destinataire_statut` (`destinataire_id`,`type_destinataire`,`statut_lecture`);

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
  ADD KEY `idx_plan_validateur` (`idValidateur`),
  ADD KEY `idx_avancement` (`pourcentage_avancement`),
  ADD KEY `idx_plan_sujet_statut` (`idsujets`,`statut_validation`);

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
-- Index pour la table `rapprochements_bancaires`
--
ALTER TABLE `rapprochements_bancaires`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`idresponsable_orientation`),
  ADD KEY `idx_est_chef` (`est_chef`,`orientation_idorientation`,`annee_acad_idannee_acad`);

--
-- Index pour la table `responsable_section`
--
ALTER TABLE `responsable_section`
  ADD PRIMARY KEY (`idresponsable_section`),
  ADD KEY `idx_responsable_chef` (`section_idsection`,`est_chef`,`annee_acad_idannee_acad`);

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
-- Index pour la table `sujet_historique`
--
ALTER TABLE `sujet_historique`
  ADD PRIMARY KEY (`id_historique`),
  ADD KEY `idx_historique_sujet` (`idsujets`),
  ADD KEY `idx_historique_date` (`date_action`),
  ADD KEY `idx_historique_action` (`action`);

--
-- Index pour la table `sujet_reformulations`
--
ALTER TABLE `sujet_reformulations`
  ADD PRIMARY KEY (`id_reformulation`),
  ADD KEY `idx_reformulation_sujet` (`idsujets`),
  ADD KEY `idx_reformulation_etudiant` (`etudiant_idetudiant`),
  ADD KEY `idx_reformulation_statut` (`statut_reformulation`);

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
-- AUTO_INCREMENT pour la table `acces_archive`
--
ALTER TABLE `acces_archive`
  MODIFY `idacces` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `activite_projet`
--
ALTER TABLE `activite_projet`
  MODIFY `idActivite_projet` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `actualites`
--
ALTER TABLE `actualites`
  MODIFY `idactualite` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT pour la table `details_rapprochements`
--
ALTER TABLE `details_rapprochements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `documents_inscription_externe`
--
ALTER TABLE `documents_inscription_externe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `doc_activite`
--
ALTER TABLE `doc_activite`
  MODIFY `idDoc_activite` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `etats_paiement`
--
ALTER TABLE `etats_paiement`
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
-- AUTO_INCREMENT pour la table `excel_tokens`
--
ALTER TABLE `excel_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `frais_soutenance`
--
ALTER TABLE `frais_soutenance`
  MODIFY `idfrais_soutenance` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grade`
--
ALTER TABLE `grade`
  MODIFY `idgrade` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grilles_anciennes_ecue`
--
ALTER TABLE `grilles_anciennes_ecue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grilles_anciennes_etudiants`
--
ALTER TABLE `grilles_anciennes_etudiants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grilles_anciennes_imports`
--
ALTER TABLE `grilles_anciennes_imports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grilles_anciennes_notes`
--
ALTER TABLE `grilles_anciennes_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grilles_anciennes_resultats`
--
ALTER TABLE `grilles_anciennes_resultats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grilles_anciennes_ue`
--
ALTER TABLE `grilles_anciennes_ue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `inscriptions_externes`
--
ALTER TABLE `inscriptions_externes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intervention_jury`
--
ALTER TABLE `intervention_jury`
  MODIFY `idintervention` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inventaire`
--
ALTER TABLE `inventaire`
  MODIFY `id_inventaire` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `liens_inscription_externe`
--
ALTER TABLE `liens_inscription_externe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lien_inscription_documents`
--
ALTER TABLE `lien_inscription_documents`
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
-- AUTO_INCREMENT pour la table `rapprochements_bancaires`
--
ALTER TABLE `rapprochements_bancaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `sujet_historique`
--
ALTER TABLE `sujet_historique`
  MODIFY `id_historique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sujet_reformulations`
--
ALTER TABLE `sujet_reformulations`
  MODIFY `id_reformulation` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `idMod` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT pour la table `t_permissions`
--
ALTER TABLE `t_permissions`
  MODIFY `idPerm` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=289;

--
-- AUTO_INCREMENT pour la table `t_roles`
--
ALTER TABLE `t_roles`
  MODIFY `idRole` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
-- Contraintes pour la table `details_rapprochements`
--
ALTER TABLE `details_rapprochements`
  ADD CONSTRAINT `fk_details_rapprochement` FOREIGN KEY (`rapprochement_id`) REFERENCES `rapprochements_bancaires` (`id`),
  ADD CONSTRAINT `fk_details_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`);

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
-- Contraintes pour la table `documents_inscription_externe`
--
ALTER TABLE `documents_inscription_externe`
  ADD CONSTRAINT `documents_inscription_externe_ibfk_1` FOREIGN KEY (`inscription_externe_id`) REFERENCES `inscriptions_externes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_inscription_externe_ibfk_2` FOREIGN KEY (`lien_document_id`) REFERENCES `lien_inscription_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_inscription_externe_ibfk_3` FOREIGN KEY (`id_validateur`) REFERENCES `t_users` (`idUser`);

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
-- Contraintes pour la table `grilles_anciennes_ecue`
--
ALTER TABLE `grilles_anciennes_ecue`
  ADD CONSTRAINT `grilles_anciennes_ecue_ibfk_1` FOREIGN KEY (`ue_id`) REFERENCES `grilles_anciennes_ue` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `grilles_anciennes_etudiants`
--
ALTER TABLE `grilles_anciennes_etudiants`
  ADD CONSTRAINT `grilles_anciennes_etudiants_ibfk_1` FOREIGN KEY (`import_id`) REFERENCES `grilles_anciennes_imports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `grilles_anciennes_imports`
--
ALTER TABLE `grilles_anciennes_imports`
  ADD CONSTRAINT `fk_grilles_anciennes_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`idsection`) ON DELETE SET NULL;

--
-- Contraintes pour la table `grilles_anciennes_notes`
--
ALTER TABLE `grilles_anciennes_notes`
  ADD CONSTRAINT `grilles_anciennes_notes_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `grilles_anciennes_etudiants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grilles_anciennes_notes_ibfk_2` FOREIGN KEY (`ecue_id`) REFERENCES `grilles_anciennes_ecue` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `grilles_anciennes_resultats`
--
ALTER TABLE `grilles_anciennes_resultats`
  ADD CONSTRAINT `grilles_anciennes_resultats_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `grilles_anciennes_etudiants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grilles_anciennes_resultats_ibfk_2` FOREIGN KEY (`ue_id`) REFERENCES `grilles_anciennes_ue` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grilles_anciennes_resultats_ibfk_3` FOREIGN KEY (`import_id`) REFERENCES `grilles_anciennes_imports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `grilles_anciennes_ue`
--
ALTER TABLE `grilles_anciennes_ue`
  ADD CONSTRAINT `grilles_anciennes_ue_ibfk_1` FOREIGN KEY (`import_id`) REFERENCES `grilles_anciennes_imports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `historique_visites`
--
ALTER TABLE `historique_visites`
  ADD CONSTRAINT `fk_historique_visite` FOREIGN KEY (`idVisite`) REFERENCES `visites` (`idVisite`) ON DELETE CASCADE;

--
-- Contraintes pour la table `inscriptions_externes`
--
ALTER TABLE `inscriptions_externes`
  ADD CONSTRAINT `inscriptions_externes_ibfk_1` FOREIGN KEY (`lien_inscription_id`) REFERENCES `liens_inscription_externe` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscriptions_externes_ibfk_2` FOREIGN KEY (`id_validateur`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `journal_comptable`
--
ALTER TABLE `journal_comptable`
  ADD CONSTRAINT `journal_comptable_ibfk_1` FOREIGN KEY (`id_user_creation`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `liens_inscription_externe`
--
ALTER TABLE `liens_inscription_externe`
  ADD CONSTRAINT `liens_inscription_externe_ibfk_1` FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`idpromotion`) ON DELETE CASCADE,
  ADD CONSTRAINT `liens_inscription_externe_ibfk_2` FOREIGN KEY (`annee_acad_id`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE,
  ADD CONSTRAINT `liens_inscription_externe_ibfk_3` FOREIGN KEY (`idUser`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `lien_inscription_documents`
--
ALTER TABLE `lien_inscription_documents`
  ADD CONSTRAINT `lien_inscription_documents_ibfk_1` FOREIGN KEY (`lien_inscription_id`) REFERENCES `liens_inscription_externe` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lien_inscription_documents_ibfk_2` FOREIGN KEY (`document_obligatoire_id`) REFERENCES `documents_obligatoires` (`id`) ON DELETE SET NULL;

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
