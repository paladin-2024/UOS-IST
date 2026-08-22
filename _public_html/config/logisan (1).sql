-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 31 déc. 2024 à 08:59
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
-- Base de données : `logisan`
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
  `dateEnregistrement` datetime DEFAULT NULL,
  `idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `agent`
--

INSERT INTO `agent` (`idAgent`, `noms`, `lieuNaissance`, `dateNaissance`, `sexe`, `etatCivil`, `niveauEtude`, `dateEnregistrement`, `idStructure`) VALUES
(1, 'ZIRHUMANA KALUMUNA', 'BUKAVU', '1994-01-15', 'M', 'Marié', 'L2', NULL, 1),
(3, 'SARAH MUGISHO', 'GOMA', '1990-10-10', 'F', 'Marié', 'L2', NULL, 2);

-- --------------------------------------------------------

--
-- Structure de la table `banque`
--

CREATE TABLE `banque` (
  `idBanque` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `solde` decimal(14,2) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
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
  `Structure_idStructure` int(11) NOT NULL
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
  `typeCompte` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `serviceConcerne` varchar(245) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `userConcerne` int(11) DEFAULT NULL,
  `Service_idService` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `Etat_de_besoin_idEtat_de_besoin` int(11) NOT NULL
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
-- Structure de la table `ecriture`
--

CREATE TABLE `ecriture` (
  `idEcriture` int(11) NOT NULL,
  `montant` decimal(15,2) DEFAULT NULL,
  `dateEcriture` date DEFAULT NULL,
  `numeroPiece` varchar(145) DEFAULT NULL,
  `description` varchar(245) DEFAULT NULL,
  `idCompte_debit` int(11) DEFAULT NULL,
  `idCompte_credit` int(11) DEFAULT NULL,
  `Journaux_idJournaux` int(11) NOT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `statut` varchar(45) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Service_idService` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `journaux`
--

CREATE TABLE `journaux` (
  `idJournaux` int(11) NOT NULL,
  `nom_journal` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `code_journal` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL
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
  `Groupe_depense_structure_idGroupe_depense_structure` int(11) NOT NULL
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
  `Groupe_recette_structure_idGroupe_recette_structure` int(11) NOT NULL
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
  `Facture_fournisseur_idFacture_fournisseur` int(11) NOT NULL
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
-- Structure de la table `presence_agent`
--

CREATE TABLE `presence_agent` (
  `idPresence_agent` int(11) NOT NULL,
  `annee` varchar(45) DEFAULT NULL,
  `mois` varchar(45) DEFAULT NULL,
  `joursPresence` int(11) DEFAULT NULL,
  `joursAbsence` int(11) DEFAULT NULL,
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
-- Structure de la table `service`
--

CREATE TABLE `service` (
  `idService` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `Responsable` varchar(145) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `service`
--

INSERT INTO `service` (`idService`, `designation`, `Responsable`, `Structure_idStructure`) VALUES
(1, 'Finance', 'AGATHE LWABOSHI', 1),
(3, 'Pharmacie', 'SARAH MUGISHO', 2),
(4, 'Pharmacie', 'SARAH MUGISHO', 1);

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

--
-- Déchargement des données de la table `structure`
--

INSERT INTO `structure` (`idStructure`, `designation`, `adresse`, `siteweb`, `phone1`, `phone2`, `logo`, `joursOuvrables`, `IPR`, `taux_retenu_absence`, `dateEnregistrement`, `nJoursRecouvrement`) VALUES
(1, 'HGR CIRIRI', 'Bukavu, CIRIRI chez rau', 'www.ciriri.com', '0976526633', '0817575953', 'hgr_ciriri_logo.png', 30, 15, 0, '2024-12-30 09:18:39', 30),
(2, 'HGR NYANTENDE', 'Bukavu, NYANTENDE', 'www.nyantende.com', '0898557663', '0817575953', 'hgr_nyantende_logo.png', 30, 15, 0, '2024-12-30 18:08:56', 30);

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
(3, 'Gestion des patients', 'patient'),
(4, 'GRH', 'grh'),
(5, 'Comptabilité', 'comptabilite'),
(6, 'Budget', 'budget'),
(7, 'Logistique', 'logistique'),
(8, 'Réception', 'reception'),
(9, 'Projet', 'projet'),
(10, 'Production', 'production');

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
(5, 4, 'ajouter', 'agent.add', 'Ajouter Agent'),
(6, 4, 'modifier', 'agent.edit', 'Modifier agent'),
(7, 4, 'consulter', 'agent.list', 'Liste des agents'),
(8, 4, 'ajouter', 'agent.famille.add', 'Ajout Dossier famille'),
(9, 4, 'modifier', 'agent.famille.edit', 'MAJ Dossier Famille'),
(10, 4, 'consulter', 'agent.famille.list', 'Consulter la famille'),
(11, 4, 'ajouter', 'agent.contrat.add', 'Ajouter contrat'),
(12, 4, 'modifier', 'agent.contrat.edit', 'Modifier contrat'),
(13, 4, 'consulter', 'agent.contrat.list', 'Liste des contrats'),
(14, 4, 'ajouter', 'agent.doc.add', 'Ajouter document'),
(15, 4, 'modifier', 'agent.doc.edit', 'Modifier document'),
(16, 4, 'consulter', 'agent.doc.list', 'Liste des documents'),
(17, 4, 'ajouter', 'agent.pres.add', 'Ajouter Présence'),
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
(30, 5, 'consulter', 'journal.list', 'Visualiser journal'),
(31, 5, 'consulter', 'compte.historique', 'Historique compte'),
(32, 5, 'ajouter', 'facture_cl.add', 'Ajouter facture client'),
(33, 5, 'modifier', 'facture_cl.edit', 'Modifier facture client'),
(34, 5, 'consulter', 'facture_cl.list', 'Liste factures clients'),
(35, 5, 'consulter', 'facture_cl.historique', 'Historique factures clients'),
(36, 5, 'ajouter', 'facture_frs.add', 'Ajouter facture fournisseur'),
(37, 5, 'modifier', 'facture_frs.edit', 'Modifier facture fournisseur'),
(38, 5, 'consulter', 'facture_frs.list', 'Liste factures fournisseurs'),
(39, 5, 'consulter', 'facture_frs.historique', 'Historique factures fournisseurs'),
(40, 5, 'ajouter', 'paiement.facture.client.add', 'Paiement Facture client'),
(41, 5, 'modifier', 'paiement.facture.client.edit', 'Modifier paiement facture client'),
(42, 5, 'ajouter', 'paiement.facture.fourni.add', 'Paiement facture Fournisseur'),
(43, 5, 'modifier', 'paiement.facture.fourni.edit', 'MAJ Paiement Facture fournisseur'),
(44, 6, 'ajouter', 'budget.recette.add', 'Ajouter un Budget Récette'),
(45, 6, 'modifier', 'budget.recette.edit', 'Modifier budget récette'),
(46, 6, 'visualiser', 'budget.recette.view', 'Visualiser budget recette'),
(47, 6, 'ajouter', 'budget.recette.grp.add', 'Ajouter groupe recette'),
(48, 6, 'modifier', 'budget.recette.grp.edit', 'Modifier groupe récette'),
(49, 6, 'ajouter', 'budget.recette.ligne.add', 'Ajouter ligne récette'),
(50, 6, 'modifier', 'budget.recette.ligne.edit', 'Modifier ligne récette'),
(51, 6, 'ajouter', 'budget.depense.add', 'Ajouter budget dépense'),
(52, 6, 'modifier', 'budget.depense.edit', 'Modifier budget dépense'),
(53, 6, 'visualiser', 'budget.depense.list', 'Visualiser budget dépense'),
(54, 6, 'ajouter', 'budget.depense.groupe.add', 'Ajouter groupe de dépense'),
(55, 6, 'modifier', 'budget.depense.groupe.edit', 'Modifier groupe de dépense'),
(56, 6, 'ajouter', 'budget.depense.ligne.add', 'Ajouter ligne dépense'),
(57, 6, 'modifier', 'budget.depense.ligne.edit', 'Modifier ligne dépense'),
(58, 5, 'ajouter', 'recette.add', 'Ajouter récette'),
(59, 5, 'modifier', 'recette.edit', 'Modifier récette'),
(60, 5, 'visualiser', 'recette.list', 'Visualiser récettes'),
(61, 5, 'ajouter', 'depense.add', 'Ajouter dépense'),
(62, 5, 'modifier', 'depense.edit', 'Modifier dépense'),
(63, 5, 'visualiser', 'depense.list', 'Visualiser les dépenses'),
(64, 5, 'visualiser', 'bilan', 'Visualiser bilan'),
(65, 6, 'visualiser', 'budget.execution', 'Exécution budgétaire'),
(66, 7, 'ajouter', 'etat_besoin.add', 'Ajouter état de besoins'),
(67, 7, 'modifier', 'etat_besoin.edit', 'MAJ état de besoins'),
(68, 7, 'visualiser', 'etat_besoin.view', 'Visualiser état de besoins'),
(69, 7, 'modifier', 'etat_besoin.valid', 'Valider état de besoins'),
(70, 7, 'ajouter', 'depot.add', 'Ajouter dépôt'),
(71, 7, 'modifier', 'depot.edit', 'Modifier dépôt'),
(72, 7, 'visualiser', 'depot.view', 'Visualiser dépôt'),
(73, 7, 'ajouter', 'depot.entree.add', 'Entrée au dépôt'),
(74, 7, 'ajouter', 'depot.sortie.add', 'Sortie au dépôt'),
(75, 7, 'visualiser', 'depot.sortie.list', 'Liste des sorties'),
(76, 7, 'visualiser', 'depot.entree.list', 'Liste des entrées'),
(77, 8, 'ajouter', 'courriel.add', 'Enregistrer un courriel'),
(78, 8, 'modifier', 'courriel.edit', 'Modifier un couriel'),
(79, 8, 'visualiser', 'courriel.list', 'Visualiser les couriels'),
(80, 8, 'modifier', 'courriel.coment', 'Commenter un courriel'),
(81, 9, 'ajouter', 'projet.add', 'Ajouter projet'),
(82, 9, 'modifier', 'projet.edit', 'Modifier projet'),
(83, 9, 'visualiser', 'projet.view', 'Liste des projets'),
(84, 9, 'ajouter', 'activite.add', 'Ajouter activité'),
(85, 9, 'modifier', 'activite.edit', 'Modifier activité'),
(86, 9, 'visualiser', 'activite.list', 'Liste des activités'),
(87, 9, 'ajouter', 'document.add', 'Ajouter document activité'),
(88, 9, 'modifier', 'document.edit', 'Modifier doc activité'),
(89, 9, 'visualiser', 'document.list', 'Liste des documents activités'),
(90, 10, 'ajouter', 'labo.add', 'Ajouter laboratoire'),
(91, 2, 'ajouter', 'structure.add', 'Gérer structure'),
(92, 2, 'ajouter', 'service.add', 'Ajouter Service'),
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
(107, 2, 'visualiser', 'service.list', 'Liste des services');

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
(2, 'Medecin'),
(3, 'Receptionniste'),
(4, 'Responsable RH'),
(5, 'Super Administrateur');

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
  `dernier_connexion` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `t_users`
--

INSERT INTO `t_users` (`idUser`, `idRole`, `nomUser`, `loginUser`, `pw`, `imageUser`, `etatUser`, `dernier_connexion`) VALUES
(1, 1, 'WEB MASTER', 'admin', '$2y$10$Oz4kVJ.6hAUdmz1BaAIIv.iNKjVSz/gSMaLFUCAEQdPy.pfHmVW0C', 'USER-6771936bb35622.47596287.png', 1, '2024-12-31'),
(2, 4, 'SARAH MUGISHO', 'sarah', '$2y$10$LQ.IaGD6m.qz4HtV/h8SVuI3pO03A9v0NkXcfRAJM.zv9YsMPG0Vm', 'Anayah Ok.png', 1, '2024-12-30'),
(3, 3, 'ALLIANCE BORA', 'bora', '$2y$10$nppvvip6iDF/H/3p6RrhvOTh80EmBsWe1J8CatGJgenBDrrv1K2D.', 'bb anayah.jpg', 1, '2024-12-30');

-- --------------------------------------------------------

--
-- Structure de la table `t_user_permissions`
--

CREATE TABLE `t_user_permissions` (
  `idUP` int(255) NOT NULL,
  `idRole` int(255) NOT NULL,
  `idPerm` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `t_user_permissions`
--

INSERT INTO `t_user_permissions` (`idUP`, `idRole`, `idPerm`) VALUES
(59, 4, 5),
(60, 4, 6),
(61, 4, 7),
(62, 4, 8),
(63, 4, 9),
(64, 4, 10),
(65, 4, 11),
(66, 4, 12),
(67, 4, 13),
(68, 4, 14),
(69, 4, 15),
(70, 4, 16),
(71, 4, 17),
(72, 4, 18),
(173, 1, 2),
(174, 1, 3),
(175, 1, 4),
(176, 1, 91),
(177, 1, 92),
(178, 1, 93),
(179, 1, 107),
(180, 1, 5),
(181, 1, 6),
(182, 1, 7),
(183, 1, 8),
(184, 1, 9),
(185, 1, 10),
(186, 1, 11),
(187, 1, 12),
(188, 1, 13),
(189, 1, 14),
(190, 1, 15),
(191, 1, 16),
(192, 1, 17),
(193, 1, 18),
(194, 1, 66),
(195, 1, 90),
(196, 1, 81),
(197, 1, 77),
(198, 1, 1),
(199, 1, 19);

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
-- Structure de la table `user_depot`
--

CREATE TABLE `user_depot` (
  `iduser_depot` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Depot_idDepot` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Structure de la table `user_structure`
--

CREATE TABLE `user_structure` (
  `id_user_structure` int(11) NOT NULL,
  `toutvoir` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `user_structure`
--

INSERT INTO `user_structure` (`id_user_structure`, `toutvoir`, `idUser`, `idStructure`) VALUES
(2, 0, 2, 1),
(4, 0, 1, 1),
(8, 0, 1, 2);

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
-- Index pour la table `banque`
--
ALTER TABLE `banque`
  ADD PRIMARY KEY (`idBanque`),
  ADD KEY `fk_Banque_Structure1_idx` (`Structure_idStructure`);

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
-- Index pour la table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`idClient`),
  ADD KEY `fk_Client_Structure1_idx` (`Structure_idStructure`);

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
  ADD KEY `fk_Compte_Structure1_idx` (`Structure_idStructure`);

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
-- Index pour la table `ecriture`
--
ALTER TABLE `ecriture`
  ADD PRIMARY KEY (`idEcriture`),
  ADD KEY `fk_Ecriture_Journaux1_idx` (`Journaux_idJournaux`);

--
-- Index pour la table `etat_de_besoin`
--
ALTER TABLE `etat_de_besoin`
  ADD PRIMARY KEY (`idEtat_de_besoin`),
  ADD KEY `fk_Etat_de_besoin_Service1_idx` (`Service_idService`);

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
  ADD KEY `fk_Fournisseur_Structure1_idx` (`Structure_idStructure`);

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
  ADD KEY `fk_ligne_depense_structure_Groupe_depense_structure1_idx` (`Groupe_depense_structure_idGroupe_depense_structure`);

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
  ADD KEY `fk_ligne_recette_structure_Groupe_recette_structure1_idx` (`Groupe_recette_structure_idGroupe_recette_structure`);

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
  ADD KEY `fk_Manifeste_entree_Facture_fournisseur1_idx` (`Facture_fournisseur_idFacture_fournisseur`);

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
-- Index pour la table `recette_structure`
--
ALTER TABLE `recette_structure`
  ADD PRIMARY KEY (`idRecette_structure`),
  ADD KEY `fk_Recette_structure_ligne_recette_structure1_idx` (`ligne_recette_structure_idligne_recette_structure`),
  ADD KEY `fk_Recette_structure_Banque1_idx` (`Banque_idBanque`);

--
-- Index pour la table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`idService`),
  ADD KEY `fk_Service_Structure1_idx` (`Structure_idStructure`);

--
-- Index pour la table `sortie_pf`
--
ALTER TABLE `sortie_pf`
  ADD PRIMARY KEY (`idsortie_pf`),
  ADD KEY `fk_sortie_pf_Laboratoire_production1_idx` (`Laboratoire_production_idLaboratoire_production`);

--
-- Index pour la table `structure`
--
ALTER TABLE `structure`
  ADD PRIMARY KEY (`idStructure`);

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
-- Index pour la table `user_activite_projet`
--
ALTER TABLE `user_activite_projet`
  ADD PRIMARY KEY (`iduser_activite_projet`),
  ADD KEY `fk_user_activite_projet_Activite_projet1_idx` (`Activite_projet_idActivite_projet`);

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
-- Index pour la table `user_depot`
--
ALTER TABLE `user_depot`
  ADD PRIMARY KEY (`iduser_depot`),
  ADD KEY `fk_user_depot_Depot1_idx` (`Depot_idDepot`);

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
-- Index pour la table `user_structure`
--
ALTER TABLE `user_structure`
  ADD PRIMARY KEY (`id_user_structure`);

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
  MODIFY `idAgent` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT pour la table `ecriture`
--
ALTER TABLE `ecriture`
  MODIFY `idEcriture` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etat_de_besoin`
--
ALTER TABLE `etat_de_besoin`
  MODIFY `idEtat_de_besoin` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `recette_structure`
--
ALTER TABLE `recette_structure`
  MODIFY `idRecette_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `service`
--
ALTER TABLE `service`
  MODIFY `idService` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `sortie_pf`
--
ALTER TABLE `sortie_pf`
  MODIFY `idsortie_pf` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `structure`
--
ALTER TABLE `structure`
  MODIFY `idStructure` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `t_modules`
--
ALTER TABLE `t_modules`
  MODIFY `idMod` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `t_permissions`
--
ALTER TABLE `t_permissions`
  MODIFY `idPerm` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT pour la table `t_roles`
--
ALTER TABLE `t_roles`
  MODIFY `idRole` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `t_users`
--
ALTER TABLE `t_users`
  MODIFY `idUser` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `t_user_permissions`
--
ALTER TABLE `t_user_permissions`
  MODIFY `idUP` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT pour la table `user_activite_projet`
--
ALTER TABLE `user_activite_projet`
  MODIFY `iduser_activite_projet` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `user_depot`
--
ALTER TABLE `user_depot`
  MODIFY `iduser_depot` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `user_structure`
--
ALTER TABLE `user_structure`
  MODIFY `id_user_structure` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  ADD CONSTRAINT `fk_Banque_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

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
-- Contraintes pour la table `client`
--
ALTER TABLE `client`
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
-- Contraintes pour la table `etat_de_besoin`
--
ALTER TABLE `etat_de_besoin`
  ADD CONSTRAINT `fk_Etat_de_besoin_Service1` FOREIGN KEY (`Service_idService`) REFERENCES `service` (`idService`) ON DELETE NO ACTION ON UPDATE NO ACTION;

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
  ADD CONSTRAINT `fk_Fournisseur_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

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
-- Contraintes pour la table `laboratoire_production`
--
ALTER TABLE `laboratoire_production`
  ADD CONSTRAINT `fk_Laboratoire_production_Structure1` FOREIGN KEY (`Structure_idStructure`) REFERENCES `structure` (`idStructure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `ligne_depense_structure`
--
ALTER TABLE `ligne_depense_structure`
  ADD CONSTRAINT `fk_ligne_depense_structure_Groupe_depense_structure1` FOREIGN KEY (`Groupe_depense_structure_idGroupe_depense_structure`) REFERENCES `groupe_depense_structure` (`idGroupe_depense_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `ligne_etat_besoin`
--
ALTER TABLE `ligne_etat_besoin`
  ADD CONSTRAINT `fk_Ligne_etat_besoin_Etat_de_besoin1` FOREIGN KEY (`Etat_de_besoin_idEtat_de_besoin`) REFERENCES `etat_de_besoin` (`idEtat_de_besoin`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `ligne_recette_structure`
--
ALTER TABLE `ligne_recette_structure`
  ADD CONSTRAINT `fk_ligne_recette_structure_Groupe_recette_structure1` FOREIGN KEY (`Groupe_recette_structure_idGroupe_recette_structure`) REFERENCES `groupe_recette_structure` (`idGroupe_recette_structure`) ON DELETE NO ACTION ON UPDATE NO ACTION;

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
  ADD CONSTRAINT `fk_Manifeste_entree_Facture_fournisseur1` FOREIGN KEY (`Facture_fournisseur_idFacture_fournisseur`) REFERENCES `facture_fournisseur` (`idFacture_fournisseur`) ON DELETE NO ACTION ON UPDATE NO ACTION;

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
-- Contraintes pour la table `user_labo_production`
--
ALTER TABLE `user_labo_production`
  ADD CONSTRAINT `fk_user_labo_production_Laboratoire_production1` FOREIGN KEY (`Laboratoire_production_idLaboratoire_production`) REFERENCES `laboratoire_production` (`idLaboratoire_production`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `user_projet`
--
ALTER TABLE `user_projet`
  ADD CONSTRAINT `fk_user_projet_Projet1` FOREIGN KEY (`Projet_idProjet`) REFERENCES `projet` (`idProjet`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
