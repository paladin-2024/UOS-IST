-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 11 mars 2025 à 14:23
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
  `telephone` varchar(200) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `photo` varchar(200) DEFAULT NULL,
  `codeAgent` varchar(200) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT current_timestamp(),
  `idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `annee_acad`
--

CREATE TABLE `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `banque`
--

CREATE TABLE `banque` (
  `idBanque` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `numeroCompte` varchar(255) NOT NULL,
  `solde` decimal(14,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Compte_idCompte` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `budget_depense_structure`
--

CREATE TABLE `budget_depense_structure` (
  `idBudget_depense_structure` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `annee` varchar(45) DEFAULT NULL,
  `solde_b_depense` decimal(14,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `budget_recette_structure`
--

CREATE TABLE `budget_recette_structure` (
  `idBudget_recette_structure` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `annee` varchar(45) DEFAULT NULL,
  `solde_b_recette` decimal(14,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `categorie_indicateur`
--

CREATE TABLE `categorie_indicateur` (
  `idCategorie` int(11) NOT NULL,
  `nomCategorie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `client`
--

CREATE TABLE `client` (
  `idClient` int(11) NOT NULL,
  `noms` varchar(145) DEFAULT NULL,
  `adresse` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `telephone` varchar(45) DEFAULT NULL,
  `solde` decimal(15,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL,
  `Compte_idCompte` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commentaire_couriel`
--

CREATE TABLE `commentaire_couriel` (
  `idcommentaire_couriel` int(11) NOT NULL,
  `dateComentaire` datetime DEFAULT NULL,
  `contenu` text DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `couriels_recu_idcouriels_recu` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `compte`
--

CREATE TABLE `compte` (
  `idCompte` int(11) NOT NULL,
  `numeroCompte` varchar(45) DEFAULT NULL,
  `intituleCompte` varchar(255) NOT NULL,
  `typeCompte` varchar(45) DEFAULT NULL,
  `classeCompte` int(11) NOT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `travail_id` int(11) DEFAULT NULL,
  `date_consultation` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contact_patient`
--

CREATE TABLE `contact_patient` (
  `idContact_patient` int(11) NOT NULL,
  `typeContact` varchar(245) DEFAULT NULL COMMENT 'Consultation, hospitalisation',
  `dateContacte` date DEFAULT NULL,
  `provenance` varchar(45) DEFAULT NULL,
  `accompagne_par` varchar(45) DEFAULT NULL,
  `telephone` varchar(45) DEFAULT NULL,
  `evolution` varchar(45) DEFAULT NULL,
  `dateFin` date DEFAULT NULL,
  `categorie` varchar(45) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Patient_idPatient` int(11) NOT NULL,
  `Service_idService` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `departement`
--

CREATE TABLE `departement` (
  `iddepartement` int(11) NOT NULL,
  `designationDepartement` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `section_idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depense_structure`
--

CREATE TABLE `depense_structure` (
  `idDepense_structure` int(11) NOT NULL,
  `montantD` decimal(14,2) DEFAULT NULL,
  `motifD` varchar(245) DEFAULT NULL,
  `beneficiaire` varchar(45) DEFAULT NULL,
  `dateoperation` date DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `ligne_depense_structure_idligne_depense_structure` int(11) NOT NULL,
  `Banque_idBanque` int(11) NOT NULL,
  `Etat_de_besoin_idEtat_de_besoin` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depot`
--

CREATE TABLE `depot` (
  `idDepot` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `adresse` varchar(145) DEFAULT NULL,
  `typeDepot` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `encadreur` int(11) NOT NULL,
  `etudiant_idetudiant` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `details_retenu`
--

CREATE TABLE `details_retenu` (
  `idDetails_retenu` int(11) NOT NULL,
  `mois` varchar(45) DEFAULT NULL,
  `annee` varchar(45) DEFAULT NULL,
  `montant_retenu` decimal(10,0) DEFAULT NULL,
  `motif` text DEFAULT NULL,
  `dateOperation` date DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Contrat_agent_idContrat_agent` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_entree`
--

CREATE TABLE `detail_entree` (
  `idDetail_entree` int(11) NOT NULL,
  `unite` varchar(45) DEFAULT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  `Manifeste_entree_idManifeste_entree` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_prevision`
--

CREATE TABLE `detail_prevision` (
  `iddetail_prevision` int(11) NOT NULL,
  `quantite` float DEFAULT NULL,
  `Prevision_production_idPrevision_production` int(11) NOT NULL,
  `produits_labo_idproduits_labo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_production`
--

CREATE TABLE `detail_production` (
  `iddetail_production` int(11) NOT NULL,
  `quantiteProduite` float DEFAULT NULL,
  `datePeremption` date DEFAULT NULL,
  `numeroLot` varchar(45) DEFAULT NULL,
  `Production_labo_idProduction_labo` int(11) NOT NULL,
  `produits_labo_idproduits_labo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_sortie`
--

CREATE TABLE `detail_sortie` (
  `idDetail_sortie` int(11) NOT NULL,
  `unite` varchar(45) DEFAULT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  `Manifeste_sortie_idManifeste_sortie` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_sortie_pf`
--

CREATE TABLE `detail_sortie_pf` (
  `iddetail_sortie_pf` int(11) NOT NULL,
  `quantite` float DEFAULT NULL,
  `numeroLot` varchar(45) DEFAULT NULL,
  `datePeremption` date DEFAULT NULL,
  `sortie_pf_idsortie_pf` int(11) NOT NULL,
  `produits_labo_idproduits_labo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `document_clinique`
--

CREATE TABLE `document_clinique` (
  `idDocument_clinique` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateElaboration` date DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Patient_idPatient` int(11) NOT NULL
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
-- Structure de la table `ecriture`
--

CREATE TABLE `ecriture` (
  `idEcriture` int(11) NOT NULL,
  `montant` decimal(15,2) DEFAULT NULL,
  `dateEcriture` date DEFAULT NULL,
  `numeroPiece` varchar(145) DEFAULT NULL,
  `description` varchar(245) DEFAULT NULL,
  `Journaux_idJournaux` int(11) NOT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecriture_detail`
--

CREATE TABLE `ecriture_detail` (
  `idDetail` int(11) NOT NULL,
  `idEcriture` int(11) NOT NULL,
  `compteId` varchar(100) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `typeCompte` enum('debit','credit') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `UE_idUE` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `enseignant`
--

CREATE TABLE `enseignant` (
  `idenseignant` int(11) NOT NULL,
  `nomEnseignant` varchar(245) NOT NULL,
  `grade` varchar(145) DEFAULT NULL,
  `idAgent` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `idDepartement` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `enseignant_ecue`
--

CREATE TABLE `enseignant_ecue` (
  `idenseignant_ecue` int(11) NOT NULL,
  `poste` varchar(145) DEFAULT NULL,
  `idEnseignant` int(11) DEFAULT NULL,
  `idECUE` int(11) DEFAULT NULL,
  `anneeAcad` varchar(45) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `pwd` varchar(250) DEFAULT NULL,
  `sexe` varchar(100) NOT NULL,
  `nationalite` varchar(100) NOT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `facture_client`
--

CREATE TABLE `facture_client` (
  `idFacture_client` int(11) NOT NULL,
  `dateFacture` date DEFAULT NULL,
  `montant` decimal(15,2) DEFAULT NULL,
  `motif` varchar(45) DEFAULT NULL,
  `numeroFacture` varchar(45) DEFAULT NULL,
  `statut` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Client_idClient` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `facture_fournisseur`
--

CREATE TABLE `facture_fournisseur` (
  `idFacture_fournisseur` int(11) NOT NULL,
  `dateFacture` date DEFAULT NULL,
  `montant` decimal(15,2) DEFAULT NULL,
  `motif` varchar(145) DEFAULT NULL,
  `numeroFacture` varchar(45) DEFAULT NULL,
  `statut` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Fournisseur_idFournisseur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ficheconsultation`
--

CREATE TABLE `ficheconsultation` (
  `idFicheConsultation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `fournisseur`
--

CREATE TABLE `fournisseur` (
  `idFournisseur` int(11) NOT NULL,
  `nom` varchar(145) DEFAULT NULL,
  `adresse` varchar(145) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `telephone` varchar(45) DEFAULT NULL,
  `solde` decimal(15,2) DEFAULT NULL,
  `dateEnregistrement` date DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL,
  `Compte_idCompte` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `frais`
--

CREATE TABLE `frais` (
  `idfrais` int(11) NOT NULL,
  `designationFrais` varchar(245) NOT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `frais_promotion`
--

CREATE TABLE `frais_promotion` (
  `idfrais_promotion` int(11) NOT NULL,
  `frais_idfrais` int(11) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `lienPaiement` varchar(255) DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `groupe_depense_structure`
--

CREATE TABLE `groupe_depense_structure` (
  `idGroupe_depense_structure` int(11) NOT NULL,
  `designationGD` varchar(245) DEFAULT NULL,
  `soldeGD` decimal(14,2) DEFAULT NULL,
  `Budget_depense_structure_idBudget_depense_structure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `groupe_recette_structure`
--

CREATE TABLE `groupe_recette_structure` (
  `idGroupe_recette_structure` int(11) NOT NULL,
  `designationGR` varchar(245) DEFAULT NULL,
  `soldeGR` varchar(45) DEFAULT NULL,
  `Budget_recette_structure_idBudget_recette_structure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `indicateur`
--

CREATE TABLE `indicateur` (
  `idIndicateur` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `Idcategorie` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `journal_automatique`
--

CREATE TABLE `journal_automatique` (
  `idJournal` int(11) NOT NULL,
  `dateOperation` date NOT NULL,
  `compte` varchar(200) NOT NULL,
  `libelle_compte` varchar(255) NOT NULL,
  `montant_debit` decimal(10,2) NOT NULL,
  `montant_credit` decimal(10,2) NOT NULL,
  `libele` text NOT NULL,
  `numPiece` varchar(255) NOT NULL,
  `Structure_idStructure` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `journal_ciriri`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `journal_ciriri` (
`idJournal` int(11)
,`dateOperation` date
,`compte` varchar(200)
,`libelle_compte` varchar(255)
,`montant_debit` decimal(10,2)
,`montant_credit` decimal(10,2)
,`libele` text
,`numPiece` varchar(255)
,`Structure_idStructure` int(11)
);

-- --------------------------------------------------------

--
-- Structure de la table `journaux`
--

CREATE TABLE `journaux` (
  `idJournaux` int(11) NOT NULL,
  `nom_journal` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `code_journal` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `laboratoire_production`
--

CREATE TABLE `laboratoire_production` (
  `idLaboratoire_production` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `adresse` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_depense_structure`
--

CREATE TABLE `ligne_depense_structure` (
  `idligne_depense_structure` int(11) NOT NULL,
  `codeLigne` varchar(45) DEFAULT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `montant` decimal(14,2) DEFAULT NULL,
  `solde` decimal(14,2) DEFAULT NULL,
  `Groupe_depense_structure_idGroupe_depense_structure` int(11) NOT NULL,
  `Compte_idCompte` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_etat_besoin`
--

CREATE TABLE `ligne_etat_besoin` (
  `idLigne_etat_besoin` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `quantite` float DEFAULT NULL,
  `prixUnitaire` decimal(14,2) DEFAULT NULL,
  `observation` varchar(245) DEFAULT NULL,
  `dateEnregistrement` varchar(45) DEFAULT NULL,
  `Etat_de_besoin_idEtat_de_besoin` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_recette_structure`
--

CREATE TABLE `ligne_recette_structure` (
  `idligne_recette_structure` int(11) NOT NULL,
  `codeLigne` varchar(45) DEFAULT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `montant` decimal(14,2) DEFAULT NULL,
  `solde` decimal(14,2) DEFAULT NULL,
  `Groupe_recette_structure_idGroupe_recette_structure` int(11) NOT NULL,
  `Compte_idCompte` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lots_pf`
--

CREATE TABLE `lots_pf` (
  `idLots_pf` int(11) NOT NULL,
  `quantite` float DEFAULT NULL,
  `datePeremption` date DEFAULT NULL,
  `dateEnregistrement` varchar(45) DEFAULT NULL,
  `numeroLot` varchar(45) DEFAULT NULL,
  `produits_labo_idproduits_labo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `manifeste_entree`
--

CREATE TABLE `manifeste_entree` (
  `idManifeste_entree` int(11) NOT NULL,
  `dateOperation` date DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `transporteur` varchar(145) DEFAULT NULL,
  `reference_document` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Depot_idDepot` int(11) NOT NULL,
  `Fournisseur_idFournisseur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `manifeste_sortie`
--

CREATE TABLE `manifeste_sortie` (
  `idManifeste_sortie` int(11) NOT NULL,
  `dateSortie` date DEFAULT NULL,
  `motif` text DEFAULT NULL,
  `transporteur` varchar(145) DEFAULT NULL,
  `reference_document` varchar(45) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Depot_idDepot` int(11) NOT NULL,
  `Client_idClient` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `matieriel_production`
--

CREATE TABLE `matieriel_production` (
  `idMateriel_production` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `typeMateriel` varchar(45) DEFAULT NULL,
  `unite` varchar(45) DEFAULT NULL,
  `quantite_recu` float DEFAULT NULL,
  `quantite_utilise` float DEFAULT NULL,
  `quantite_retourne` float DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Production_labo_idProduction_labo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mouvement_pf`
--

CREATE TABLE `mouvement_pf` (
  `idmouvement_pf` int(11) NOT NULL,
  `dateMouvement` date DEFAULT NULL,
  `quantite` float DEFAULT NULL,
  `typeMouvement` varchar(45) DEFAULT NULL,
  `referenceDoc` varchar(45) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `produits_labo_idproduits_labo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement`
--

CREATE TABLE `paiement` (
  `idpaiement` int(11) NOT NULL,
  `etudiant_idetudiant` int(11) NOT NULL,
  `frais_promotion_idfrais_promotion` int(11) NOT NULL,
  `montant_paye` decimal(10,2) NOT NULL,
  `datePaiement` datetime DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement_client`
--

CREATE TABLE `paiement_client` (
  `idPaiement_client` int(11) NOT NULL,
  `montant` decimal(14,2) DEFAULT NULL,
  `datePaiement` date DEFAULT NULL,
  `libelle` varchar(145) DEFAULT NULL,
  `depositaire` varchar(145) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Facture_client_idFacture_client` int(11) NOT NULL,
  `Banque_idBanque` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement_fournisseur`
--

CREATE TABLE `paiement_fournisseur` (
  `idPaiement_fournisseur` int(11) NOT NULL,
  `montant` decimal(14,2) DEFAULT NULL,
  `datePaiement` date DEFAULT NULL,
  `libelle` varchar(145) DEFAULT NULL,
  `beneficiaire` varchar(45) DEFAULT NULL,
  `modePaiement` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Facture_fournisseur_idFacture_fournisseur` int(11) NOT NULL,
  `Banque_idBanque` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `patient`
--

CREATE TABLE `patient` (
  `idPatient` int(11) NOT NULL,
  `noms` varchar(245) DEFAULT NULL,
  `dateNaissance` date DEFAULT NULL,
  `lieuNaissance` varchar(45) DEFAULT NULL,
  `sexe` varchar(45) DEFAULT NULL,
  `etatCivil` varchar(45) DEFAULT NULL,
  `adresse` varchar(45) DEFAULT NULL,
  `nationalite` varchar(45) DEFAULT NULL,
  `soldat` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `points`
--

CREATE TABLE `points` (
  `idpoints` int(11) NOT NULL,
  `CC` float DEFAULT NULL,
  `EX` float DEFAULT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Structure de la table `production_labo`
--

CREATE TABLE `production_labo` (
  `idProduction_labo` int(11) NOT NULL,
  `numeroDocument` varchar(45) DEFAULT NULL,
  `dateDebut` date DEFAULT NULL,
  `dateFin` date DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Laboratoire_production_idLaboratoire_production` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits_labo`
--

CREATE TABLE `produits_labo` (
  `idproduits_labo` int(11) NOT NULL,
  `designation` varchar(245) DEFAULT NULL,
  `qtStock` float DEFAULT NULL,
  `conditionnement` varchar(45) DEFAULT NULL,
  `famille` varchar(145) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `departement_iddepartement` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recette_structure`
--

CREATE TABLE `recette_structure` (
  `idRecette_structure` int(11) NOT NULL,
  `montantR` decimal(14,2) DEFAULT NULL,
  `motif` varchar(245) DEFAULT NULL,
  `depositaire` varchar(145) DEFAULT NULL,
  `dateOperation` date DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `ligne_recette_structure_idligne_recette_structure` int(11) NOT NULL,
  `Banque_idBanque` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `semestre`
--

CREATE TABLE `semestre` (
  `idsemestre` int(11) NOT NULL,
  `numeroSemestre` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL
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
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sortie_pf`
--

CREATE TABLE `sortie_pf` (
  `idsortie_pf` int(11) NOT NULL,
  `numeroSortie` varchar(45) DEFAULT NULL,
  `dateSortie` date DEFAULT NULL,
  `motif` varchar(245) DEFAULT NULL,
  `destination` varchar(145) DEFAULT NULL,
  `Laboratoire_production_idLaboratoire_production` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `specialisation`
--

CREATE TABLE `specialisation` (
  `idSpecialisation` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `dateCreation` datetime NOT NULL,
  `idUnite_recherche` int(11) NOT NULL
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
-- Structure de la table `sujets`
--

CREATE TABLE `sujets` (
  `idsujets` int(11) NOT NULL,
  `intitule` text NOT NULL,
  `etatSujet` varchar(145) DEFAULT NULL,
  `idDirecteur` int(11) DEFAULT NULL,
  `idEncadreur` int(11) DEFAULT NULL,
  `etudiant_idetudiant` int(11) DEFAULT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `cycle` enum('Premier','Deuxieme','Troisieme','') NOT NULL,
  `idSpecialisation` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL
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
  `sujets_idsujets` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `travaux_scientifiques`
--

CREATE TABLE `travaux_scientifiques` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `type_document` enum('Mémoire','Thèse','Rapport de stage','Article scientifique','Projet tutoré','Livre','Cours') NOT NULL,
  `nom_auteur` varchar(255) DEFAULT NULL,
  `type_auteur` enum('Etudiant','Enseignant','Autre') NOT NULL,
  `departement_id` int(11) DEFAULT NULL,
  `specialisation_id` int(11) DEFAULT NULL,
  `annee_academique_id` int(11) DEFAULT NULL,
  `directeur_id` int(11) DEFAULT NULL,
  `mots_cles` text DEFAULT NULL,
  `resume` text DEFAULT NULL,
  `fichier_path` varchar(255) NOT NULL,
  `date_depot` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut` enum('En attente','Validé','Rejeté') DEFAULT 'En attente',
  `est_public` tinyint(1) DEFAULT 0
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
  `dernier_connexion` date NOT NULL,
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
  `CMI` float DEFAULT NULL,
  `TD` float DEFAULT NULL,
  `TP` float DEFAULT NULL,
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
  `dateCreation` datetime DEFAULT NULL,
  `departement_iddepartement` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

CREATE TABLE `user` (
  `username` varchar(16) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(32) NOT NULL,
  `create_time` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_activite_projet`
--

CREATE TABLE `user_activite_projet` (
  `iduser_activite_projet` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Activite_projet_idActivite_projet` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_banque`
--

CREATE TABLE `user_banque` (
  `iduser_banque` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Banque_idBanque` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_budget_depense`
--

CREATE TABLE `user_budget_depense` (
  `iduser_budget_depense` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Budget_depense_structure_idBudget_depense_structure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_budget_recette`
--

CREATE TABLE `user_budget_recette` (
  `idUser_budget_recette` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Budget_recette_structure_idBudget_recette_structure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_departement`
--

CREATE TABLE `user_departement` (
  `iduser_departement` int(11) NOT NULL,
  `idDepartement` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_depot`
--

CREATE TABLE `user_depot` (
  `iduser_depot` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Depot_idDepot` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_document`
--

CREATE TABLE `user_document` (
  `idUser_document` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `id_document` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_etudiant`
--

CREATE TABLE `user_etudiant` (
  `iduser_etudiant` int(11) NOT NULL,
  `matriculeEtudiant` varchar(145) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_journal`
--

CREATE TABLE `user_journal` (
  `id_user_journal` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `dateEnregistrement` datetime NOT NULL DEFAULT current_timestamp(),
  `Journal_idJournal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_labo_production`
--

CREATE TABLE `user_labo_production` (
  `iduser_labo_production` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Laboratoire_production_idLaboratoire_production` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_projet`
--

CREATE TABLE `user_projet` (
  `iduser_projet` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Projet_idProjet` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_section`
--

CREATE TABLE `user_section` (
  `iduser_section` int(11) NOT NULL,
  `idSection` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_structure`
--

CREATE TABLE `user_structure` (
  `id_user_structure` int(11) NOT NULL,
  `toutvoir` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `valeur_indicateur`
--

CREATE TABLE `valeur_indicateur` (
  `idValeur` int(11) NOT NULL,
  `idStructure` int(11) NOT NULL,
  `idIndicateur` int(11) NOT NULL,
  `dateEnregistrement` date NOT NULL,
  `valeur` float DEFAULT NULL,
  `observation` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la vue `journal_ciriri`
--
DROP TABLE IF EXISTS `journal_ciriri`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `journal_ciriri`  AS SELECT `journal_automatique`.`idJournal` AS `idJournal`, `journal_automatique`.`dateOperation` AS `dateOperation`, `journal_automatique`.`compte` AS `compte`, `journal_automatique`.`libelle_compte` AS `libelle_compte`, `journal_automatique`.`montant_debit` AS `montant_debit`, `journal_automatique`.`montant_credit` AS `montant_credit`, `journal_automatique`.`libele` AS `libele`, `journal_automatique`.`numPiece` AS `numPiece`, `journal_automatique`.`Structure_idStructure` AS `Structure_idStructure` FROM `journal_automatique` WHERE `journal_automatique`.`Structure_idStructure` = 1 ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activite_projet`
--
ALTER TABLE `activite_projet`
  ADD PRIMARY KEY (`idActivite_projet`),
  ADD KEY `fk_Activite_projet_Projet1_idx` (`Projet_idProjet`);

--
-- Index pour la table `agent`
--
ALTER TABLE `agent`
  ADD PRIMARY KEY (`idAgent`);

--
-- Index pour la table `annee_acad`
--
ALTER TABLE `annee_acad`
  ADD PRIMARY KEY (`idannee_acad`);

--
-- Index pour la table `banque`
--
ALTER TABLE `banque`
  ADD PRIMARY KEY (`idBanque`),
  ADD KEY `fk_Banque_Structure1` (`Compte_idCompte`);

--
-- Index pour la table `budget_depense_structure`
--
ALTER TABLE `budget_depense_structure`
  ADD PRIMARY KEY (`idBudget_depense_structure`),
  ADD KEY `fk_Budget_depense_structure_Structure1_idx` (`Structure_idStructure`);

--
-- Index pour la table `budget_recette_structure`
--
ALTER TABLE `budget_recette_structure`
  ADD PRIMARY KEY (`idBudget_recette_structure`),
  ADD KEY `fk_Budget_recette_structure_Structure1_idx` (`Structure_idStructure`);

--
-- Index pour la table `categories_doc`
--
ALTER TABLE `categories_doc`
  ADD PRIMARY KEY (`id_categorie`),
  ADD KEY `idStructure` (`idStructure`);

--
-- Index pour la table `categorie_indicateur`
--
ALTER TABLE `categorie_indicateur`
  ADD PRIMARY KEY (`idCategorie`);

--
-- Index pour la table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`idClient`),
  ADD KEY `fk_Client_Structure1_idx` (`Structure_idStructure`),
  ADD KEY `Compte_idCompte` (`Compte_idCompte`);

--
-- Index pour la table `commentaire_couriel`
--
ALTER TABLE `commentaire_couriel`
  ADD PRIMARY KEY (`idcommentaire_couriel`),
  ADD KEY `fk_commentaire_couriel_couriels_recu1_idx` (`couriels_recu_idcouriels_recu`);

--
-- Index pour la table `compte`
--
ALTER TABLE `compte`
  ADD PRIMARY KEY (`idCompte`),
  ADD KEY `Structure_idStructure` (`Structure_idStructure`);

--
-- Index pour la table `configuration_universite`
--
ALTER TABLE `configuration_universite`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `travail_id` (`travail_id`);

--
-- Index pour la table `contact_patient`
--
ALTER TABLE `contact_patient`
  ADD PRIMARY KEY (`idContact_patient`),
  ADD KEY `fk_Contact_patient_Patient1_idx` (`Patient_idPatient`),
  ADD KEY `fk_Contact_patient_Service1_idx` (`Service_idService`);

--
-- Index pour la table `contrat_agent`
--
ALTER TABLE `contrat_agent`
  ADD PRIMARY KEY (`idContrat_agent`),
  ADD KEY `fk_Contrat_agent_Agent_idx` (`Agent_idAgent`),
  ADD KEY `fk_Contrat_agent_Service1_idx` (`Service_idService`);

--
-- Index pour la table `couriels_recu`
--
ALTER TABLE `couriels_recu`
  ADD PRIMARY KEY (`idcouriels_recu`),
  ADD KEY `fk_couriels_recu_Service1_idx` (`Service_idService`);

--
-- Index pour la table `departement`
--
ALTER TABLE `departement`
  ADD PRIMARY KEY (`iddepartement`),
  ADD KEY `fk_departement_section_idx` (`section_idsection`);

--
-- Index pour la table `depense_structure`
--
ALTER TABLE `depense_structure`
  ADD PRIMARY KEY (`idDepense_structure`),
  ADD KEY `fk_Depense_structure_ligne_depense_structure1_idx` (`ligne_depense_structure_idligne_depense_structure`),
  ADD KEY `fk_Depense_structure_Banque1_idx` (`Banque_idBanque`),
  ADD KEY `fk_Depense_structure_Etat_de_besoin1_idx` (`Etat_de_besoin_idEtat_de_besoin`);

--
-- Index pour la table `depot`
--
ALTER TABLE `depot`
  ADD PRIMARY KEY (`idDepot`),
  ADD KEY `fk_Depot_Structure1_idx` (`Structure_idStructure`);

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
-- Index pour la table `details_retenu`
--
ALTER TABLE `details_retenu`
  ADD PRIMARY KEY (`idDetails_retenu`),
  ADD KEY `fk_Details_retenu_Contrat_agent1_idx` (`Contrat_agent_idContrat_agent`);

--
-- Index pour la table `detail_entree`
--
ALTER TABLE `detail_entree`
  ADD PRIMARY KEY (`idDetail_entree`),
  ADD KEY `fk_Detail_entree_Manifeste_entree1_idx` (`Manifeste_entree_idManifeste_entree`);

--
-- Index pour la table `detail_prevision`
--
ALTER TABLE `detail_prevision`
  ADD PRIMARY KEY (`iddetail_prevision`),
  ADD KEY `fk_detail_prevision_Prevision_production1_idx` (`Prevision_production_idPrevision_production`),
  ADD KEY `fk_detail_prevision_produits_labo1_idx` (`produits_labo_idproduits_labo`);

--
-- Index pour la table `detail_production`
--
ALTER TABLE `detail_production`
  ADD PRIMARY KEY (`iddetail_production`),
  ADD KEY `fk_detail_production_Production_labo1_idx` (`Production_labo_idProduction_labo`),
  ADD KEY `fk_detail_production_produits_labo1_idx` (`produits_labo_idproduits_labo`);

--
-- Index pour la table `detail_sortie`
--
ALTER TABLE `detail_sortie`
  ADD PRIMARY KEY (`idDetail_sortie`),
  ADD KEY `fk_Detail_sortie_Manifeste_sortie1_idx` (`Manifeste_sortie_idManifeste_sortie`);

--
-- Index pour la table `detail_sortie_pf`
--
ALTER TABLE `detail_sortie_pf`
  ADD PRIMARY KEY (`iddetail_sortie_pf`),
  ADD KEY `fk_detail_sortie_pf_sortie_pf1_idx` (`sortie_pf_idsortie_pf`),
  ADD KEY `fk_detail_sortie_pf_produits_labo1_idx` (`produits_labo_idproduits_labo`);

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
-- Index pour la table `document_clinique`
--
ALTER TABLE `document_clinique`
  ADD PRIMARY KEY (`idDocument_clinique`),
  ADD KEY `fk_Document_clinique_Patient1_idx` (`Patient_idPatient`);

--
-- Index pour la table `doc_activite`
--
ALTER TABLE `doc_activite`
  ADD PRIMARY KEY (`idDoc_activite`),
  ADD KEY `fk_Doc_activite_Activite_projet1_idx` (`Activite_projet_idActivite_projet`);

--
-- Index pour la table `dossier_famille`
--
ALTER TABLE `dossier_famille`
  ADD PRIMARY KEY (`idDossier_famille`),
  ADD KEY `fk_Dossier_famille_Agent1_idx` (`Agent_idAgent`);

--
-- Index pour la table `echanges_taches`
--
ALTER TABLE `echanges_taches`
  ADD PRIMARY KEY (`idechange`),
  ADD KEY `fk_echanges_taches` (`taches_idtaches`);

--
-- Index pour la table `ecriture`
--
ALTER TABLE `ecriture`
  ADD PRIMARY KEY (`idEcriture`),
  ADD KEY `fk_Ecriture_Journaux1_idx` (`Journaux_idJournaux`);

--
-- Index pour la table `ecriture_detail`
--
ALTER TABLE `ecriture_detail`
  ADD PRIMARY KEY (`idDetail`),
  ADD KEY `idEcriture` (`idEcriture`);

--
-- Index pour la table `ecue`
--
ALTER TABLE `ecue`
  ADD PRIMARY KEY (`idECUE`),
  ADD KEY `fk_ECUE_UE1_idx` (`UE_idUE`);

--
-- Index pour la table `enseignant`
--
ALTER TABLE `enseignant`
  ADD PRIMARY KEY (`idenseignant`);

--
-- Index pour la table `enseignant_ecue`
--
ALTER TABLE `enseignant_ecue`
  ADD PRIMARY KEY (`idenseignant_ecue`);

--
-- Index pour la table `enseignant_uniterecherche`
--
ALTER TABLE `enseignant_uniterecherche`
  ADD PRIMARY KEY (`idAffectation`);

--
-- Index pour la table `etat_de_besoin`
--
ALTER TABLE `etat_de_besoin`
  ADD PRIMARY KEY (`idEtat_de_besoin`),
  ADD KEY `fk_Etat_de_besoin_Service1_idx` (`Service_idService`);

--
-- Index pour la table `etudiant`
--
ALTER TABLE `etudiant`
  ADD PRIMARY KEY (`idetudiant`),
  ADD KEY `fk_etudiant_annee_acad_idx` (`annee_acad_idannee_acad`),
  ADD KEY `fk_etudiant_promotion_idx` (`promotion_idpromotion`);

--
-- Index pour la table `facture_client`
--
ALTER TABLE `facture_client`
  ADD PRIMARY KEY (`idFacture_client`),
  ADD KEY `fk_Facture_client_Client1_idx` (`Client_idClient`);

--
-- Index pour la table `facture_fournisseur`
--
ALTER TABLE `facture_fournisseur`
  ADD PRIMARY KEY (`idFacture_fournisseur`),
  ADD KEY `fk_Facture_fournisseur_Fournisseur1_idx` (`Fournisseur_idFournisseur`);

--
-- Index pour la table `ficheconsultation`
--
ALTER TABLE `ficheconsultation`
  ADD PRIMARY KEY (`idFicheConsultation`);

--
-- Index pour la table `fiche_paie`
--
ALTER TABLE `fiche_paie`
  ADD PRIMARY KEY (`idFiche_paie`),
  ADD KEY `fk_Fiche_paie_Contrat_agent1_idx` (`Contrat_agent_idContrat_agent`);

--
-- Index pour la table `fournisseur`
--
ALTER TABLE `fournisseur`
  ADD PRIMARY KEY (`idFournisseur`),
  ADD KEY `fk_Fournisseur_Structure1_idx` (`Structure_idStructure`),
  ADD KEY `Compte_idCompte` (`Compte_idCompte`);

--
-- Index pour la table `frais`
--
ALTER TABLE `frais`
  ADD PRIMARY KEY (`idfrais`);

--
-- Index pour la table `frais_promotion`
--
ALTER TABLE `frais_promotion`
  ADD PRIMARY KEY (`idfrais_promotion`),
  ADD UNIQUE KEY `unique_frais_promotion_annee` (`frais_idfrais`,`promotion_idpromotion`,`annee_acad_idannee_acad`),
  ADD KEY `promotion_idpromotion` (`promotion_idpromotion`),
  ADD KEY `annee_acad_idannee_acad` (`annee_acad_idannee_acad`);

--
-- Index pour la table `groupe_depense_structure`
--
ALTER TABLE `groupe_depense_structure`
  ADD PRIMARY KEY (`idGroupe_depense_structure`),
  ADD KEY `fk_Groupe_depense_structure_Budget_depense_structure1_idx` (`Budget_depense_structure_idBudget_depense_structure`);

--
-- Index pour la table `groupe_recette_structure`
--
ALTER TABLE `groupe_recette_structure`
  ADD PRIMARY KEY (`idGroupe_recette_structure`),
  ADD KEY `fk_Groupe_recette_structure_Budget_recette_structure1_idx` (`Budget_recette_structure_idBudget_recette_structure`);

--
-- Index pour la table `indicateur`
--
ALTER TABLE `indicateur`
  ADD PRIMARY KEY (`idIndicateur`);

--
-- Index pour la table `journal_automatique`
--
ALTER TABLE `journal_automatique`
  ADD PRIMARY KEY (`idJournal`),
  ADD KEY `Structure_idStructure` (`Structure_idStructure`);

--
-- Index pour la table `journaux`
--
ALTER TABLE `journaux`
  ADD PRIMARY KEY (`idJournaux`);

--
-- Index pour la table `laboratoire_production`
--
ALTER TABLE `laboratoire_production`
  ADD PRIMARY KEY (`idLaboratoire_production`),
  ADD KEY `fk_Laboratoire_production_Structure1_idx` (`Structure_idStructure`);

--
-- Index pour la table `ligne_depense_structure`
--
ALTER TABLE `ligne_depense_structure`
  ADD PRIMARY KEY (`idligne_depense_structure`),
  ADD KEY `fk_ligne_depense_structure_Groupe_depense_structure1_idx` (`Groupe_depense_structure_idGroupe_depense_structure`),
  ADD KEY `Compte_idCompte` (`Compte_idCompte`);

--
-- Index pour la table `ligne_etat_besoin`
--
ALTER TABLE `ligne_etat_besoin`
  ADD PRIMARY KEY (`idLigne_etat_besoin`),
  ADD KEY `fk_Ligne_etat_besoin_Etat_de_besoin1_idx` (`Etat_de_besoin_idEtat_de_besoin`);

--
-- Index pour la table `ligne_recette_structure`
--
ALTER TABLE `ligne_recette_structure`
  ADD PRIMARY KEY (`idligne_recette_structure`),
  ADD KEY `fk_ligne_recette_structure_Groupe_recette_structure1_idx` (`Groupe_recette_structure_idGroupe_recette_structure`),
  ADD KEY `Compte_idCompte` (`Compte_idCompte`);

--
-- Index pour la table `lots_pf`
--
ALTER TABLE `lots_pf`
  ADD PRIMARY KEY (`idLots_pf`),
  ADD KEY `fk_Lots_pf_produits_labo1_idx` (`produits_labo_idproduits_labo`);

--
-- Index pour la table `manifeste_entree`
--
ALTER TABLE `manifeste_entree`
  ADD PRIMARY KEY (`idManifeste_entree`),
  ADD KEY `fk_Manifeste_entree_Depot1_idx` (`Depot_idDepot`),
  ADD KEY `fk_Manifeste_entree_Facture_fournisseur1_idx` (`Fournisseur_idFournisseur`);

--
-- Index pour la table `manifeste_sortie`
--
ALTER TABLE `manifeste_sortie`
  ADD PRIMARY KEY (`idManifeste_sortie`),
  ADD KEY `fk_Manifeste_sortie_Depot1_idx` (`Depot_idDepot`),
  ADD KEY `fk_Manifeste_sortie_Client1_idx` (`Client_idClient`);

--
-- Index pour la table `matieriel_production`
--
ALTER TABLE `matieriel_production`
  ADD PRIMARY KEY (`idMateriel_production`),
  ADD KEY `fk_Matieriel_production_Production_labo1_idx` (`Production_labo_idProduction_labo`);

--
-- Index pour la table `mouvement_pf`
--
ALTER TABLE `mouvement_pf`
  ADD PRIMARY KEY (`idmouvement_pf`),
  ADD KEY `fk_mouvement_pf_produits_labo1_idx` (`produits_labo_idproduits_labo`);

--
-- Index pour la table `paiement`
--
ALTER TABLE `paiement`
  ADD PRIMARY KEY (`idpaiement`);

--
-- Index pour la table `paiement_client`
--
ALTER TABLE `paiement_client`
  ADD PRIMARY KEY (`idPaiement_client`),
  ADD KEY `fk_Paiement_client_Facture_client1_idx` (`Facture_client_idFacture_client`),
  ADD KEY `fk_Paiement_client_Banque1_idx` (`Banque_idBanque`);

--
-- Index pour la table `paiement_fournisseur`
--
ALTER TABLE `paiement_fournisseur`
  ADD PRIMARY KEY (`idPaiement_fournisseur`),
  ADD KEY `fk_Paiement_fournisseur_Facture_fournisseur1_idx` (`Facture_fournisseur_idFacture_fournisseur`),
  ADD KEY `fk_Paiement_fournisseur_Banque1_idx` (`Banque_idBanque`);

--
-- Index pour la table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`idPatient`),
  ADD KEY `fk_Patient_Structure1_idx` (`Structure_idStructure`);

--
-- Index pour la table `points`
--
ALTER TABLE `points`
  ADD PRIMARY KEY (`idpoints`),
  ADD KEY `fk_points_ECUE1_idx` (`ECUE_idECUE`),
  ADD KEY `fk_points_session1_idx` (`session_idsession`);

--
-- Index pour la table `presence_agent`
--
ALTER TABLE `presence_agent`
  ADD PRIMARY KEY (`idPresence_agent`),
  ADD KEY `fk_Presence_agent_Agent1_idx` (`Agent_idAgent`);

--
-- Index pour la table `prevision_production`
--
ALTER TABLE `prevision_production`
  ADD PRIMARY KEY (`idPrevision_production`),
  ADD KEY `fk_Prevision_production_Laboratoire_production1_idx` (`Laboratoire_production_idLaboratoire_production`);

--
-- Index pour la table `production_labo`
--
ALTER TABLE `production_labo`
  ADD PRIMARY KEY (`idProduction_labo`),
  ADD KEY `fk_Production_labo_Laboratoire_production1_idx` (`Laboratoire_production_idLaboratoire_production`);

--
-- Index pour la table `produits_labo`
--
ALTER TABLE `produits_labo`
  ADD PRIMARY KEY (`idproduits_labo`);

--
-- Index pour la table `projet`
--
ALTER TABLE `projet`
  ADD PRIMARY KEY (`idProjet`),
  ADD KEY `fk_Projet_Structure1_idx` (`Structure_idStructure`);

--
-- Index pour la table `promotion`
--
ALTER TABLE `promotion`
  ADD PRIMARY KEY (`idpromotion`),
  ADD KEY `fk_promotion_departement_idx` (`departement_iddepartement`),
  ADD KEY `fk_promotion_annee_acad_idx` (`annee_acad_idannee_acad`);

--
-- Index pour la table `recette_structure`
--
ALTER TABLE `recette_structure`
  ADD PRIMARY KEY (`idRecette_structure`),
  ADD KEY `fk_Recette_structure_ligne_recette_structure1_idx` (`ligne_recette_structure_idligne_recette_structure`),
  ADD KEY `fk_Recette_structure_Banque1_idx` (`Banque_idBanque`);

--
-- Index pour la table `responsable_departement`
--
ALTER TABLE `responsable_departement`
  ADD PRIMARY KEY (`idresponsable_departement`),
  ADD KEY `fk_responsable_departement_departement1_idx` (`departement_iddepartement`),
  ADD KEY `fk_responsable_departement_annee_acad1_idx` (`annee_acad_idannee_acad`);

--
-- Index pour la table `responsable_section`
--
ALTER TABLE `responsable_section`
  ADD PRIMARY KEY (`idresponsable_section`),
  ADD KEY `fk_responsable_section_section1_idx` (`section_idsection`),
  ADD KEY `fk_responsable_section_annee_acad1_idx` (`annee_acad_idannee_acad`);

--
-- Index pour la table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`idsection`);

--
-- Index pour la table `semestre`
--
ALTER TABLE `semestre`
  ADD PRIMARY KEY (`idsemestre`),
  ADD KEY `fk_semestre_promotion1_idx` (`promotion_idpromotion`),
  ADD KEY `fk_semestre_session1_idx` (`session_idsession`);

--
-- Index pour la table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`idService`),
  ADD KEY `fk_Service_Structure1_idx` (`Structure_idStructure`);

--
-- Index pour la table `session`
--
ALTER TABLE `session`
  ADD PRIMARY KEY (`idsession`);

--
-- Index pour la table `sortie_pf`
--
ALTER TABLE `sortie_pf`
  ADD PRIMARY KEY (`idsortie_pf`),
  ADD KEY `fk_sortie_pf_Laboratoire_production1_idx` (`Laboratoire_production_idLaboratoire_production`);

--
-- Index pour la table `specialisation`
--
ALTER TABLE `specialisation`
  ADD PRIMARY KEY (`idSpecialisation`);

--
-- Index pour la table `structure`
--
ALTER TABLE `structure`
  ADD PRIMARY KEY (`idStructure`);

--
-- Index pour la table `sujets`
--
ALTER TABLE `sujets`
  ADD PRIMARY KEY (`idsujets`),
  ADD KEY `fk_sujets_etudiant_idx` (`etudiant_idetudiant`),
  ADD KEY `fk_sujets_annee_acad_idx` (`annee_acad_idannee_acad`);

--
-- Index pour la table `taches`
--
ALTER TABLE `taches`
  ADD PRIMARY KEY (`idtaches`),
  ADD KEY `fk_taches_sujets1_idx` (`sujets_idsujets`);

--
-- Index pour la table `travaux_scientifiques`
--
ALTER TABLE `travaux_scientifiques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `departement_id` (`departement_id`),
  ADD KEY `specialisation_id` (`specialisation_id`),
  ADD KEY `annee_academique_id` (`annee_academique_id`);

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
  ADD PRIMARY KEY (`idUE`),
  ADD KEY `fk_UE_semestre1_idx` (`semestre_idsemestre`);

--
-- Index pour la table `unite_recherche`
--
ALTER TABLE `unite_recherche`
  ADD PRIMARY KEY (`idunite_recherche`),
  ADD KEY `fk_unite_recherche_departement1_idx` (`departement_iddepartement`);

--
-- Index pour la table `user_activite_projet`
--
ALTER TABLE `user_activite_projet`
  ADD PRIMARY KEY (`iduser_activite_projet`),
  ADD KEY `fk_user_activite_projet_Activite_projet1_idx` (`Activite_projet_idActivite_projet`);

--
-- Index pour la table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `user_banque`
--
ALTER TABLE `user_banque`
  ADD PRIMARY KEY (`iduser_banque`),
  ADD KEY `fk_user_banque_Banque1_idx` (`Banque_idBanque`);

--
-- Index pour la table `user_budget_depense`
--
ALTER TABLE `user_budget_depense`
  ADD PRIMARY KEY (`iduser_budget_depense`),
  ADD KEY `fk_user_budget_depense_Budget_depense_structure1_idx` (`Budget_depense_structure_idBudget_depense_structure`);

--
-- Index pour la table `user_budget_recette`
--
ALTER TABLE `user_budget_recette`
  ADD PRIMARY KEY (`idUser_budget_recette`),
  ADD KEY `fk_User_budget_recette_Budget_recette_structure1_idx` (`Budget_recette_structure_idBudget_recette_structure`);

--
-- Index pour la table `user_departement`
--
ALTER TABLE `user_departement`
  ADD PRIMARY KEY (`iduser_departement`);

--
-- Index pour la table `user_depot`
--
ALTER TABLE `user_depot`
  ADD PRIMARY KEY (`iduser_depot`),
  ADD KEY `fk_user_depot_Depot1_idx` (`Depot_idDepot`);

--
-- Index pour la table `user_document`
--
ALTER TABLE `user_document`
  ADD PRIMARY KEY (`idUser_document`);

--
-- Index pour la table `user_etudiant`
--
ALTER TABLE `user_etudiant`
  ADD PRIMARY KEY (`iduser_etudiant`);

--
-- Index pour la table `user_journal`
--
ALTER TABLE `user_journal`
  ADD PRIMARY KEY (`id_user_journal`),
  ADD KEY `journal` (`Journal_idJournal`);

--
-- Index pour la table `user_labo_production`
--
ALTER TABLE `user_labo_production`
  ADD PRIMARY KEY (`iduser_labo_production`),
  ADD KEY `fk_user_labo_production_Laboratoire_production1_idx` (`Laboratoire_production_idLaboratoire_production`);

--
-- Index pour la table `user_projet`
--
ALTER TABLE `user_projet`
  ADD PRIMARY KEY (`iduser_projet`),
  ADD KEY `fk_user_projet_Projet1_idx` (`Projet_idProjet`);

--
-- Index pour la table `user_section`
--
ALTER TABLE `user_section`
  ADD PRIMARY KEY (`iduser_section`);

--
-- Index pour la table `user_structure`
--
ALTER TABLE `user_structure`
  ADD PRIMARY KEY (`id_user_structure`);

--
-- Index pour la table `valeur_indicateur`
--
ALTER TABLE `valeur_indicateur`
  ADD PRIMARY KEY (`idValeur`),
  ADD KEY `idStructure` (`idStructure`),
  ADD KEY `idIndicateur` (`idIndicateur`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activite_projet`
--
ALTER TABLE `activite_projet`
  MODIFY `idActivite_projet` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `agent`
--
ALTER TABLE `agent`
  MODIFY `idAgent` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `annee_acad`
--
ALTER TABLE `annee_acad`
  MODIFY `idannee_acad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `banque`
--
ALTER TABLE `banque`
  MODIFY `idBanque` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `budget_depense_structure`
--
ALTER TABLE `budget_depense_structure`
  MODIFY `idBudget_depense_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `budget_recette_structure`
--
ALTER TABLE `budget_recette_structure`
  MODIFY `idBudget_recette_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories_doc`
--
ALTER TABLE `categories_doc`
  MODIFY `id_categorie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categorie_indicateur`
--
ALTER TABLE `categorie_indicateur`
  MODIFY `idCategorie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `client`
--
ALTER TABLE `client`
  MODIFY `idClient` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commentaire_couriel`
--
ALTER TABLE `commentaire_couriel`
  MODIFY `idcommentaire_couriel` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `compte`
--
ALTER TABLE `compte`
  MODIFY `idCompte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `configuration_universite`
--
ALTER TABLE `configuration_universite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contact_patient`
--
ALTER TABLE `contact_patient`
  MODIFY `idContact_patient` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contrat_agent`
--
ALTER TABLE `contrat_agent`
  MODIFY `idContrat_agent` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `couriels_recu`
--
ALTER TABLE `couriels_recu`
  MODIFY `idcouriels_recu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `departement`
--
ALTER TABLE `departement`
  MODIFY `iddepartement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depense_structure`
--
ALTER TABLE `depense_structure`
  MODIFY `idDepense_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depot`
--
ALTER TABLE `depot`
  MODIFY `idDepot` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depot_memoire`
--
ALTER TABLE `depot_memoire`
  MODIFY `iddepot_memoire` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depot_rapport`
--
ALTER TABLE `depot_rapport`
  MODIFY `iddepot_rapport` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `details_retenu`
--
ALTER TABLE `details_retenu`
  MODIFY `idDetails_retenu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `detail_entree`
--
ALTER TABLE `detail_entree`
  MODIFY `idDetail_entree` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `detail_prevision`
--
ALTER TABLE `detail_prevision`
  MODIFY `iddetail_prevision` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `detail_production`
--
ALTER TABLE `detail_production`
  MODIFY `iddetail_production` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `detail_sortie`
--
ALTER TABLE `detail_sortie`
  MODIFY `idDetail_sortie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `detail_sortie_pf`
--
ALTER TABLE `detail_sortie_pf`
  MODIFY `iddetail_sortie_pf` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `document_clinique`
--
ALTER TABLE `document_clinique`
  MODIFY `idDocument_clinique` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `echanges_taches`
--
ALTER TABLE `echanges_taches`
  MODIFY `idechange` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ecriture`
--
ALTER TABLE `ecriture`
  MODIFY `idEcriture` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ecriture_detail`
--
ALTER TABLE `ecriture_detail`
  MODIFY `idDetail` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ecue`
--
ALTER TABLE `ecue`
  MODIFY `idECUE` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `enseignant`
--
ALTER TABLE `enseignant`
  MODIFY `idenseignant` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `enseignant_ecue`
--
ALTER TABLE `enseignant_ecue`
  MODIFY `idenseignant_ecue` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `enseignant_uniterecherche`
--
ALTER TABLE `enseignant_uniterecherche`
  MODIFY `idAffectation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etat_de_besoin`
--
ALTER TABLE `etat_de_besoin`
  MODIFY `idEtat_de_besoin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etudiant`
--
ALTER TABLE `etudiant`
  MODIFY `idetudiant` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `facture_client`
--
ALTER TABLE `facture_client`
  MODIFY `idFacture_client` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `facture_fournisseur`
--
ALTER TABLE `facture_fournisseur`
  MODIFY `idFacture_fournisseur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ficheconsultation`
--
ALTER TABLE `ficheconsultation`
  MODIFY `idFicheConsultation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fiche_paie`
--
ALTER TABLE `fiche_paie`
  MODIFY `idFiche_paie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fournisseur`
--
ALTER TABLE `fournisseur`
  MODIFY `idFournisseur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `frais`
--
ALTER TABLE `frais`
  MODIFY `idfrais` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `frais_promotion`
--
ALTER TABLE `frais_promotion`
  MODIFY `idfrais_promotion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `groupe_depense_structure`
--
ALTER TABLE `groupe_depense_structure`
  MODIFY `idGroupe_depense_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `groupe_recette_structure`
--
ALTER TABLE `groupe_recette_structure`
  MODIFY `idGroupe_recette_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `indicateur`
--
ALTER TABLE `indicateur`
  MODIFY `idIndicateur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `journal_automatique`
--
ALTER TABLE `journal_automatique`
  MODIFY `idJournal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `journaux`
--
ALTER TABLE `journaux`
  MODIFY `idJournaux` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `laboratoire_production`
--
ALTER TABLE `laboratoire_production`
  MODIFY `idLaboratoire_production` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ligne_depense_structure`
--
ALTER TABLE `ligne_depense_structure`
  MODIFY `idligne_depense_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ligne_etat_besoin`
--
ALTER TABLE `ligne_etat_besoin`
  MODIFY `idLigne_etat_besoin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ligne_recette_structure`
--
ALTER TABLE `ligne_recette_structure`
  MODIFY `idligne_recette_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lots_pf`
--
ALTER TABLE `lots_pf`
  MODIFY `idLots_pf` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `manifeste_entree`
--
ALTER TABLE `manifeste_entree`
  MODIFY `idManifeste_entree` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `manifeste_sortie`
--
ALTER TABLE `manifeste_sortie`
  MODIFY `idManifeste_sortie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `matieriel_production`
--
ALTER TABLE `matieriel_production`
  MODIFY `idMateriel_production` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mouvement_pf`
--
ALTER TABLE `mouvement_pf`
  MODIFY `idmouvement_pf` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiement`
--
ALTER TABLE `paiement`
  MODIFY `idpaiement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiement_client`
--
ALTER TABLE `paiement_client`
  MODIFY `idPaiement_client` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiement_fournisseur`
--
ALTER TABLE `paiement_fournisseur`
  MODIFY `idPaiement_fournisseur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `patient`
--
ALTER TABLE `patient`
  MODIFY `idPatient` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `points`
--
ALTER TABLE `points`
  MODIFY `idpoints` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `presence_agent`
--
ALTER TABLE `presence_agent`
  MODIFY `idPresence_agent` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `prevision_production`
--
ALTER TABLE `prevision_production`
  MODIFY `idPrevision_production` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `production_labo`
--
ALTER TABLE `production_labo`
  MODIFY `idProduction_labo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `produits_labo`
--
ALTER TABLE `produits_labo`
  MODIFY `idproduits_labo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `projet`
--
ALTER TABLE `projet`
  MODIFY `idProjet` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `promotion`
--
ALTER TABLE `promotion`
  MODIFY `idpromotion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recette_structure`
--
ALTER TABLE `recette_structure`
  MODIFY `idRecette_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `responsable_departement`
--
ALTER TABLE `responsable_departement`
  MODIFY `idresponsable_departement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `responsable_section`
--
ALTER TABLE `responsable_section`
  MODIFY `idresponsable_section` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `section`
--
ALTER TABLE `section`
  MODIFY `idsection` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `sortie_pf`
--
ALTER TABLE `sortie_pf`
  MODIFY `idsortie_pf` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `specialisation`
--
ALTER TABLE `specialisation`
  MODIFY `idSpecialisation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `structure`
--
ALTER TABLE `structure`
  MODIFY `idStructure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sujets`
--
ALTER TABLE `sujets`
  MODIFY `idsujets` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `idtaches` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `travaux_scientifiques`
--
ALTER TABLE `travaux_scientifiques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `user_activite_projet`
--
ALTER TABLE `user_activite_projet`
  MODIFY `iduser_activite_projet` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_banque`
--
ALTER TABLE `user_banque`
  MODIFY `iduser_banque` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_budget_depense`
--
ALTER TABLE `user_budget_depense`
  MODIFY `iduser_budget_depense` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_budget_recette`
--
ALTER TABLE `user_budget_recette`
  MODIFY `idUser_budget_recette` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_departement`
--
ALTER TABLE `user_departement`
  MODIFY `iduser_departement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_depot`
--
ALTER TABLE `user_depot`
  MODIFY `iduser_depot` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_document`
--
ALTER TABLE `user_document`
  MODIFY `idUser_document` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_etudiant`
--
ALTER TABLE `user_etudiant`
  MODIFY `iduser_etudiant` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_journal`
--
ALTER TABLE `user_journal`
  MODIFY `id_user_journal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_labo_production`
--
ALTER TABLE `user_labo_production`
  MODIFY `iduser_labo_production` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_projet`
--
ALTER TABLE `user_projet`
  MODIFY `iduser_projet` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_section`
--
ALTER TABLE `user_section`
  MODIFY `iduser_section` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_structure`
--
ALTER TABLE `user_structure`
  MODIFY `id_user_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `valeur_indicateur`
--
ALTER TABLE `valeur_indicateur`
  MODIFY `idValeur` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activite_projet`
--
ALTER TABLE `activite_projet`
  ADD CONSTRAINT `fk_Activite_projet_Projet1` FOREIGN KEY (`Projet_idProjet`) REFERENCES `projet` (`idProjet`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `banque`
--
ALTER TABLE `banque`
  ADD CONSTRAINT `fk_Banque_Structure1` FOREIGN KEY (`Compte_idCompte`) REFERENCES `compte` (`idCompte`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `budget_depense_structure`
--
ALTER TABLE `budget_depense_structure`
  ADD CONSTRAINT `fk_Budget_depense_structure_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `budget_recette_structure`
--
ALTER TABLE `budget_recette_structure`
  ADD CONSTRAINT `fk_Budget_recette_structure_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `categories_doc`
--
ALTER TABLE `categories_doc`
  ADD CONSTRAINT `categories_doc_ibfk_1` FOREIGN KEY (`idStructure`) REFERENCES `structure` (`idStructure`);

--
-- Contraintes pour la table `client`
--
ALTER TABLE `client`
  ADD CONSTRAINT `client_ibfk_1` FOREIGN KEY (`Compte_idCompte`) REFERENCES `compte` (`idCompte`),
  ADD CONSTRAINT `fk_Client_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `commentaire_couriel`
--
ALTER TABLE `commentaire_couriel`
  ADD CONSTRAINT `fk_commentaire_couriel_couriels_recu1` FOREIGN KEY (`couriels_recu_idcouriels_recu`) REFERENCES `couriels_recu` (`idcouriels_recu`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `compte`
--
ALTER TABLE `compte`
  ADD CONSTRAINT `fk_Compte_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`travail_id`) REFERENCES `travaux_scientifiques` (`id`);

--
-- Contraintes pour la table `contact_patient`
--
ALTER TABLE `contact_patient`
  ADD CONSTRAINT `fk_Contact_patient_Patient1` FOREIGN KEY (`Patient_idPatient`) REFERENCES `patient` (`idPatient`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Contact_patient_Service1` FOREIGN KEY (`Service_idService`) REFERENCES `service` (`idService`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `contrat_agent`
--
ALTER TABLE `contrat_agent`
  ADD CONSTRAINT `fk_Contrat_agent_Agent` FOREIGN KEY (`Agent_idAgent`) REFERENCES `agent` (`idAgent`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Contrat_agent_Service1` FOREIGN KEY (`Service_idService`) REFERENCES `service` (`idService`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `couriels_recu`
--
ALTER TABLE `couriels_recu`
  ADD CONSTRAINT `fk_couriels_recu_Service1` FOREIGN KEY (`Service_idService`) REFERENCES `service` (`idService`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `departement`
--
ALTER TABLE `departement`
  ADD CONSTRAINT `fk_departement_section` FOREIGN KEY (`section_idsection`) REFERENCES `section` (`idsection`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `depense_structure`
--
ALTER TABLE `depense_structure`
  ADD CONSTRAINT `fk_Depense_structure_Banque1` FOREIGN KEY (`Banque_idBanque`) REFERENCES `banque` (`idBanque`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Depense_structure_Etat_de_besoin1` FOREIGN KEY (`Etat_de_besoin_idEtat_de_besoin`) REFERENCES `etat_de_besoin` (`idEtat_de_besoin`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Depense_structure_ligne_depense_structure1` FOREIGN KEY (`ligne_depense_structure_idligne_depense_structure`) REFERENCES `ligne_depense_structure` (`idligne_depense_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `depot`
--
ALTER TABLE `depot`
  ADD CONSTRAINT `fk_Depot_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `depot_memoire`
--
ALTER TABLE `depot_memoire`
  ADD CONSTRAINT `fk_depot_memoire_sujets` FOREIGN KEY (`sujets_idsujets`) REFERENCES `sujets` (`idsujets`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `depot_rapport`
--
ALTER TABLE `depot_rapport`
  ADD CONSTRAINT `fk_depot_rapport_encadreur` FOREIGN KEY (`encadreur`) REFERENCES `enseignant` (`idenseignant`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_depot_rapport_etudiant` FOREIGN KEY (`etudiant_idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `details_retenu`
--
ALTER TABLE `details_retenu`
  ADD CONSTRAINT `fk_Details_retenu_Contrat_agent1` FOREIGN KEY (`Contrat_agent_idContrat_agent`) REFERENCES `contrat_agent` (`idContrat_agent`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `detail_entree`
--
ALTER TABLE `detail_entree`
  ADD CONSTRAINT `fk_Detail_entree_Manifeste_entree1` FOREIGN KEY (`Manifeste_entree_idManifeste_entree`) REFERENCES `manifeste_entree` (`idManifeste_entree`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `detail_prevision`
--
ALTER TABLE `detail_prevision`
  ADD CONSTRAINT `fk_detail_prevision_Prevision_production1` FOREIGN KEY (`Prevision_production_idPrevision_production`) REFERENCES `prevision_production` (`idPrevision_production`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_detail_prevision_produits_labo1` FOREIGN KEY (`produits_labo_idproduits_labo`) REFERENCES `produits_labo` (`idproduits_labo`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `detail_production`
--
ALTER TABLE `detail_production`
  ADD CONSTRAINT `fk_detail_production_Production_labo1` FOREIGN KEY (`Production_labo_idProduction_labo`) REFERENCES `production_labo` (`idProduction_labo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_detail_production_produits_labo1` FOREIGN KEY (`produits_labo_idproduits_labo`) REFERENCES `produits_labo` (`idproduits_labo`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `detail_sortie`
--
ALTER TABLE `detail_sortie`
  ADD CONSTRAINT `fk_Detail_sortie_Manifeste_sortie1` FOREIGN KEY (`Manifeste_sortie_idManifeste_sortie`) REFERENCES `manifeste_sortie` (`idManifeste_sortie`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `detail_sortie_pf`
--
ALTER TABLE `detail_sortie_pf`
  ADD CONSTRAINT `fk_detail_sortie_pf_produits_labo1` FOREIGN KEY (`produits_labo_idproduits_labo`) REFERENCES `produits_labo` (`idproduits_labo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_detail_sortie_pf_sortie_pf1` FOREIGN KEY (`sortie_pf_idsortie_pf`) REFERENCES `sortie_pf` (`idsortie_pf`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `documents_public`
--
ALTER TABLE `documents_public`
  ADD CONSTRAINT `documents_public_ibfk_1` FOREIGN KEY (`id_categorie`) REFERENCES `categories_doc` (`id_categorie`),
  ADD CONSTRAINT `documents_public_ibfk_2` FOREIGN KEY (`idUser`) REFERENCES `t_users` (`idUser`);

--
-- Contraintes pour la table `document_agent`
--
ALTER TABLE `document_agent`
  ADD CONSTRAINT `fk_Document_agent_Agent1` FOREIGN KEY (`Agent_idAgent`) REFERENCES `agent` (`idAgent`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `document_clinique`
--
ALTER TABLE `document_clinique`
  ADD CONSTRAINT `fk_Document_clinique_Patient1` FOREIGN KEY (`Patient_idPatient`) REFERENCES `patient` (`idPatient`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `doc_activite`
--
ALTER TABLE `doc_activite`
  ADD CONSTRAINT `fk_Doc_activite_Activite_projet1` FOREIGN KEY (`Activite_projet_idActivite_projet`) REFERENCES `activite_projet` (`idActivite_projet`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `dossier_famille`
--
ALTER TABLE `dossier_famille`
  ADD CONSTRAINT `fk_Dossier_famille_Agent1` FOREIGN KEY (`Agent_idAgent`) REFERENCES `agent` (`idAgent`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `ecriture`
--
ALTER TABLE `ecriture`
  ADD CONSTRAINT `fk_Ecriture_Journaux1` FOREIGN KEY (`Journaux_idJournaux`) REFERENCES `journaux` (`idJournaux`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `ecriture_detail`
--
ALTER TABLE `ecriture_detail`
  ADD CONSTRAINT `ecriture_detail_ibfk_1` FOREIGN KEY (`idEcriture`) REFERENCES `ecriture` (`idEcriture`);

--
-- Contraintes pour la table `etat_de_besoin`
--
ALTER TABLE `etat_de_besoin`
  ADD CONSTRAINT `fk_Etat_de_besoin_Service1` FOREIGN KEY (`Service_idService`) REFERENCES `service` (`idService`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `etudiant`
--
ALTER TABLE `etudiant`
  ADD CONSTRAINT `fk_etudiant_annee_acad` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_etudiant_promotion` FOREIGN KEY (`promotion_idpromotion`) REFERENCES `promotion` (`idpromotion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `facture_client`
--
ALTER TABLE `facture_client`
  ADD CONSTRAINT `fk_Facture_client_Client1` FOREIGN KEY (`Client_idClient`) REFERENCES `client` (`idClient`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `facture_fournisseur`
--
ALTER TABLE `facture_fournisseur`
  ADD CONSTRAINT `fk_Facture_fournisseur_Fournisseur1` FOREIGN KEY (`Fournisseur_idFournisseur`) REFERENCES `fournisseur` (`idFournisseur`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `fiche_paie`
--
ALTER TABLE `fiche_paie`
  ADD CONSTRAINT `fk_Fiche_paie_Contrat_agent1` FOREIGN KEY (`Contrat_agent_idContrat_agent`) REFERENCES `contrat_agent` (`idContrat_agent`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `fournisseur`
--
ALTER TABLE `fournisseur`
  ADD CONSTRAINT `fk_Fournisseur_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fournisseur_ibfk_1` FOREIGN KEY (`Compte_idCompte`) REFERENCES `compte` (`idCompte`);

--
-- Contraintes pour la table `frais_promotion`
--
ALTER TABLE `frais_promotion`
  ADD CONSTRAINT `frais_promotion_ibfk_1` FOREIGN KEY (`frais_idfrais`) REFERENCES `frais` (`idfrais`) ON DELETE CASCADE,
  ADD CONSTRAINT `frais_promotion_ibfk_2` FOREIGN KEY (`promotion_idpromotion`) REFERENCES `promotion` (`idpromotion`) ON DELETE CASCADE,
  ADD CONSTRAINT `frais_promotion_ibfk_3` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE;

--
-- Contraintes pour la table `groupe_depense_structure`
--
ALTER TABLE `groupe_depense_structure`
  ADD CONSTRAINT `fk_Groupe_depense_structure_Budget_depense_structure1` FOREIGN KEY (`Budget_depense_structure_idBudget_depense_structure`) REFERENCES `budget_depense_structure` (`idBudget_depense_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `groupe_recette_structure`
--
ALTER TABLE `groupe_recette_structure`
  ADD CONSTRAINT `fk_Groupe_recette_structure_Budget_recette_structure1` FOREIGN KEY (`Budget_recette_structure_idBudget_recette_structure`) REFERENCES `budget_recette_structure` (`idBudget_recette_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `journal_automatique`
--
ALTER TABLE `journal_automatique`
  ADD CONSTRAINT `journal_automatique_ibfk_1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`);

--
-- Contraintes pour la table `laboratoire_production`
--
ALTER TABLE `laboratoire_production`
  ADD CONSTRAINT `fk_Laboratoire_production_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `ligne_depense_structure`
--
ALTER TABLE `ligne_depense_structure`
  ADD CONSTRAINT `fk_ligne_depense_structure_Groupe_depense_structure1` FOREIGN KEY (`Groupe_depense_structure_idGroupe_depense_structure`) REFERENCES `groupe_depense_structure` (`idGroupe_depense_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `ligne_depense_structure_ibfk_1` FOREIGN KEY (`Compte_idCompte`) REFERENCES `compte` (`idCompte`);

--
-- Contraintes pour la table `ligne_etat_besoin`
--
ALTER TABLE `ligne_etat_besoin`
  ADD CONSTRAINT `fk_Ligne_etat_besoin_Etat_de_besoin1` FOREIGN KEY (`Etat_de_besoin_idEtat_de_besoin`) REFERENCES `etat_de_besoin` (`idEtat_de_besoin`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `ligne_recette_structure`
--
ALTER TABLE `ligne_recette_structure`
  ADD CONSTRAINT `fk_ligne_recette_structure_Groupe_recette_structure1` FOREIGN KEY (`Groupe_recette_structure_idGroupe_recette_structure`) REFERENCES `groupe_recette_structure` (`idGroupe_recette_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `ligne_recette_structure_ibfk_1` FOREIGN KEY (`Compte_idCompte`) REFERENCES `compte` (`idCompte`);

--
-- Contraintes pour la table `lots_pf`
--
ALTER TABLE `lots_pf`
  ADD CONSTRAINT `fk_Lots_pf_produits_labo1` FOREIGN KEY (`produits_labo_idproduits_labo`) REFERENCES `produits_labo` (`idproduits_labo`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `manifeste_entree`
--
ALTER TABLE `manifeste_entree`
  ADD CONSTRAINT `fk_Manifeste_entree_Depot1` FOREIGN KEY (`Depot_idDepot`) REFERENCES `depot` (`idDepot`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Manifeste_entree_Facture_fournisseur1` FOREIGN KEY (`Fournisseur_idFournisseur`) REFERENCES `fournisseur` (`idFournisseur`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `manifeste_sortie`
--
ALTER TABLE `manifeste_sortie`
  ADD CONSTRAINT `fk_Manifeste_sortie_Client1` FOREIGN KEY (`Client_idClient`) REFERENCES `client` (`idClient`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Manifeste_sortie_Depot1` FOREIGN KEY (`Depot_idDepot`) REFERENCES `depot` (`idDepot`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `matieriel_production`
--
ALTER TABLE `matieriel_production`
  ADD CONSTRAINT `fk_Matieriel_production_Production_labo1` FOREIGN KEY (`Production_labo_idProduction_labo`) REFERENCES `production_labo` (`idProduction_labo`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `mouvement_pf`
--
ALTER TABLE `mouvement_pf`
  ADD CONSTRAINT `fk_mouvement_pf_produits_labo1` FOREIGN KEY (`produits_labo_idproduits_labo`) REFERENCES `produits_labo` (`idproduits_labo`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `paiement_client`
--
ALTER TABLE `paiement_client`
  ADD CONSTRAINT `fk_Paiement_client_Banque1` FOREIGN KEY (`Banque_idBanque`) REFERENCES `banque` (`idBanque`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Paiement_client_Facture_client1` FOREIGN KEY (`Facture_client_idFacture_client`) REFERENCES `facture_client` (`idFacture_client`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `paiement_fournisseur`
--
ALTER TABLE `paiement_fournisseur`
  ADD CONSTRAINT `fk_Paiement_fournisseur_Banque1` FOREIGN KEY (`Banque_idBanque`) REFERENCES `banque` (`idBanque`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Paiement_fournisseur_Facture_fournisseur1` FOREIGN KEY (`Facture_fournisseur_idFacture_fournisseur`) REFERENCES `facture_fournisseur` (`idFacture_fournisseur`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `patient`
--
ALTER TABLE `patient`
  ADD CONSTRAINT `fk_Patient_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `presence_agent`
--
ALTER TABLE `presence_agent`
  ADD CONSTRAINT `fk_Presence_agent_Agent1` FOREIGN KEY (`Agent_idAgent`) REFERENCES `agent` (`idAgent`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `prevision_production`
--
ALTER TABLE `prevision_production`
  ADD CONSTRAINT `fk_Prevision_production_Laboratoire_production1` FOREIGN KEY (`Laboratoire_production_idLaboratoire_production`) REFERENCES `laboratoire_production` (`idLaboratoire_production`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `production_labo`
--
ALTER TABLE `production_labo`
  ADD CONSTRAINT `fk_Production_labo_Laboratoire_production1` FOREIGN KEY (`Laboratoire_production_idLaboratoire_production`) REFERENCES `laboratoire_production` (`idLaboratoire_production`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `projet`
--
ALTER TABLE `projet`
  ADD CONSTRAINT `fk_Projet_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `promotion`
--
ALTER TABLE `promotion`
  ADD CONSTRAINT `fk_promotion_annee_acad` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_promotion_departement` FOREIGN KEY (`departement_iddepartement`) REFERENCES `departement` (`iddepartement`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `recette_structure`
--
ALTER TABLE `recette_structure`
  ADD CONSTRAINT `fk_Recette_structure_Banque1` FOREIGN KEY (`Banque_idBanque`) REFERENCES `banque` (`idBanque`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Recette_structure_ligne_recette_structure1` FOREIGN KEY (`ligne_recette_structure_idligne_recette_structure`) REFERENCES `ligne_recette_structure` (`idligne_recette_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `service`
--
ALTER TABLE `service`
  ADD CONSTRAINT `fk_Service_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `sortie_pf`
--
ALTER TABLE `sortie_pf`
  ADD CONSTRAINT `fk_sortie_pf_Laboratoire_production1` FOREIGN KEY (`Laboratoire_production_idLaboratoire_production`) REFERENCES `laboratoire_production` (`idLaboratoire_production`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `sujets`
--
ALTER TABLE `sujets`
  ADD CONSTRAINT `fk_sujets_annee_acad` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sujets_etudiant` FOREIGN KEY (`etudiant_idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `travaux_scientifiques`
--
ALTER TABLE `travaux_scientifiques`
  ADD CONSTRAINT `travaux_scientifiques_ibfk_1` FOREIGN KEY (`departement_id`) REFERENCES `departement` (`iddepartement`),
  ADD CONSTRAINT `travaux_scientifiques_ibfk_2` FOREIGN KEY (`specialisation_id`) REFERENCES `specialisation` (`idSpecialisation`),
  ADD CONSTRAINT `travaux_scientifiques_ibfk_3` FOREIGN KEY (`annee_academique_id`) REFERENCES `annee_acad` (`idannee_acad`);

--
-- Contraintes pour la table `user_activite_projet`
--
ALTER TABLE `user_activite_projet`
  ADD CONSTRAINT `fk_user_activite_projet_Activite_projet1` FOREIGN KEY (`Activite_projet_idActivite_projet`) REFERENCES `activite_projet` (`idActivite_projet`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `user_banque`
--
ALTER TABLE `user_banque`
  ADD CONSTRAINT `fk_user_banque_Banque1` FOREIGN KEY (`Banque_idBanque`) REFERENCES `banque` (`idBanque`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `user_budget_depense`
--
ALTER TABLE `user_budget_depense`
  ADD CONSTRAINT `fk_user_budget_depense_Budget_depense_structure1` FOREIGN KEY (`Budget_depense_structure_idBudget_depense_structure`) REFERENCES `budget_depense_structure` (`idBudget_depense_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `user_budget_recette`
--
ALTER TABLE `user_budget_recette`
  ADD CONSTRAINT `fk_User_budget_recette_Budget_recette_structure1` FOREIGN KEY (`Budget_recette_structure_idBudget_recette_structure`) REFERENCES `budget_recette_structure` (`idBudget_recette_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `user_depot`
--
ALTER TABLE `user_depot`
  ADD CONSTRAINT `fk_user_depot_Depot1` FOREIGN KEY (`Depot_idDepot`) REFERENCES `depot` (`idDepot`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `user_journal`
--
ALTER TABLE `user_journal`
  ADD CONSTRAINT `user_journal_ibfk_1` FOREIGN KEY (`Journal_idJournal`) REFERENCES `journaux` (`idJournaux`);

--
-- Contraintes pour la table `user_labo_production`
--
ALTER TABLE `user_labo_production`
  ADD CONSTRAINT `fk_user_labo_production_Laboratoire_production1` FOREIGN KEY (`Laboratoire_production_idLaboratoire_production`) REFERENCES `laboratoire_production` (`idLaboratoire_production`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `user_projet`
--
ALTER TABLE `user_projet`
  ADD CONSTRAINT `fk_user_projet_Projet1` FOREIGN KEY (`Projet_idProjet`) REFERENCES `projet` (`idProjet`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `valeur_indicateur`
--
ALTER TABLE `valeur_indicateur`
  ADD CONSTRAINT `valeur_indicateur_ibfk_1` FOREIGN KEY (`idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE CASCADE,
  ADD CONSTRAINT `valeur_indicateur_ibfk_2` FOREIGN KEY (`idIndicateur`) REFERENCES `indicateur` (`idIndicateur`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
