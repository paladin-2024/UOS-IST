--
-- Structure de la table `client`
--

CREATE TABLE `client` (
  `id_client` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_client` varchar(20) NOT NULL,
  `type_client` enum('Particulier','Entreprise') NOT NULL,
  `nom_client` varchar(255) NOT NULL,
  `adresse` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nif` varchar(50) DEFAULT NULL,
  `rccm` varchar(50) DEFAULT NULL,
  `id_compte_comptable` int(11) NOT NULL,
  `plafond_credit` decimal(15,2) DEFAULT 0.00,
  `delai_paiement` int(11) DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande_client`
--

CREATE TABLE `commande_client` (
  `id_commande` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_commande` varchar(20) NOT NULL,
  `date_commande` date NOT NULL,
  `id_client` int(11) NOT NULL,
  `id_devis` int(11) DEFAULT NULL,
  `montant_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `date_livraison_prevue` date DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Livré','Facturé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande_fournisseur`
--

CREATE TABLE `commande_fournisseur` (
  `id_commande` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_commande` varchar(20) NOT NULL,
  `date_commande` date NOT NULL,
  `id_fournisseur` int(11) NOT NULL,
  `id_demande_prix` int(11) DEFAULT NULL,
  `montant_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `date_livraison_prevue` date DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Réceptionné','Facturé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `compte_bancaire`
--

CREATE TABLE `compte_bancaire` (
  `id_compte_bancaire` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_banque` int(11) NOT NULL,
  `numero_compte` varchar(50) NOT NULL,
  `intitule_compte` varchar(100) NOT NULL,
  `devise` varchar(3) NOT NULL DEFAULT 'USD',
  `solde_initial` decimal(15,2) NOT NULL DEFAULT 0.00,
  `solde_actuel` decimal(15,2) NOT NULL DEFAULT 0.00,
  `id_compte_comptable` int(11) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `compte_comptable`
--

CREATE TABLE `compte_comptable` (
  `id_compte` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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
-- Structure de la table `demande_prix`
--

CREATE TABLE `demande_prix` (
  `id_demande_prix` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_demande` varchar(20) NOT NULL,
  `date_demande` date NOT NULL,
  `id_fournisseur` int(11) NOT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Transformé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depot`
--

CREATE TABLE `depot` (
  `id_depot` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_depot` varchar(20) NOT NULL,
  `libelle_depot` varchar(100) NOT NULL,
  `adresse` text DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_entree_stock`
--

CREATE TABLE `detail_entree_stock` (
  `id_detail_entree` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_entree` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_inventaire`
--

CREATE TABLE `detail_inventaire` (
  `id_detail_inventaire` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_inventaire` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `stock_theorique` decimal(15,2) NOT NULL,
  `stock_physique` decimal(15,2) NOT NULL,
  `ecart` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `observation` text DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_livraison_lot`
--

CREATE TABLE `detail_livraison_lot` (
  `id_detail_livraison_lot` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_ligne_livraison` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_rapport_financier`
--

CREATE TABLE `detail_rapport_financier` (
  `id_detail_rapport` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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
-- Structure de la table `detail_sortie_lot`
--

CREATE TABLE `detail_sortie_lot` (
  `id_detail_sortie_lot` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_detail_sortie` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_sortie_stock`
--

CREATE TABLE `detail_sortie_stock` (
  `id_detail_sortie` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_sortie` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `detail_transfert_stock`
--

CREATE TABLE `detail_transfert_stock` (
  `id_detail_transfert` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_transfert` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `devis`
--

CREATE TABLE `devis` (
  `id_devis` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_devis` varchar(20) NOT NULL,
  `date_devis` date NOT NULL,
  `id_client` int(11) NOT NULL,
  `montant_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `validite` int(11) NOT NULL DEFAULT 30,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Transformé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


CREATE TABLE `document_produit` (
  `id_document` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_produit` int(11) NOT NULL,
  `type_document` varchar(50) NOT NULL,
  `titre_document` varchar(255) NOT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecriture_comptable`
--

CREATE TABLE `ecriture_comptable` (
  `id_ecriture` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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

--
-- Structure de la table `entree_stock`
--

CREATE TABLE `entree_stock` (
  `id_entree` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_entree` varchar(20) NOT NULL,
  `date_entree` date NOT NULL,
  `id_depot` int(11) NOT NULL,
  `type_entree` enum('Achat','Transfert','Inventaire','Autre') NOT NULL,
  `reference_document` varchar(30) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `exercice_comptable` (
  `id_exercice` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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
-- Structure de la table `facture_client`
--

CREATE TABLE `facture_client` (
  `id_facture` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_facture` varchar(20) NOT NULL,
  `date_facture` date NOT NULL,
  `id_client` int(11) NOT NULL,
  `id_livraison` int(11) DEFAULT NULL,
  `montant_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_paye` decimal(15,2) NOT NULL DEFAULT 0.00,
  `solde` decimal(15,2) NOT NULL DEFAULT 0.00,
  `date_echeance` date NOT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Payé partiellement','Payé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `facture_fournisseur`
--

CREATE TABLE `facture_fournisseur` (
  `id_facture` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_facture` varchar(20) NOT NULL,
  `reference_fournisseur` varchar(50) DEFAULT NULL,
  `date_facture` date NOT NULL,
  `id_fournisseur` int(11) NOT NULL,
  `id_reception` int(11) DEFAULT NULL,
  `montant_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_paye` decimal(15,2) NOT NULL DEFAULT 0.00,
  `solde` decimal(15,2) NOT NULL DEFAULT 0.00,
  `date_echeance` date NOT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Payé partiellement','Payé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fournisseur`
--

CREATE TABLE `fournisseur` (
  `id_fournisseur` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_fournisseur` varchar(20) NOT NULL,
  `nom_fournisseur` varchar(255) NOT NULL,
  `adresse` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nif` varchar(50) DEFAULT NULL,
  `rccm` varchar(50) DEFAULT NULL,
  `id_compte_comptable` int(11) NOT NULL,
  `delai_paiement` int(11) DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `journal_comptable`
--

CREATE TABLE `journal_comptable` (
  `id_journal` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_journal` varchar(10) NOT NULL,
  `libelle_journal` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `ligne_budget`
--

CREATE TABLE `ligne_budget` (
  `id_ligne_budget` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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
-- Structure de la table `ligne_commande_client`
--

CREATE TABLE `ligne_commande_client` (
  `id_ligne_commande` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_commande` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `remise` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_remise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ht` decimal(15,2) NOT NULL,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_commande_fournisseur`
--

CREATE TABLE `ligne_commande_fournisseur` (
  `id_ligne_commande` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_commande` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `remise` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_remise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ht` decimal(15,2) NOT NULL,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_demande_prix`
--

CREATE TABLE `ligne_demande_prix` (
  `id_ligne_demande` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_demande_prix` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) DEFAULT NULL,
  `montant_total` decimal(15,2) DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_devis`
--

CREATE TABLE `ligne_devis` (
  `id_ligne_devis` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_devis` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `remise` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_remise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ht` decimal(15,2) NOT NULL,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_ecriture_comptable`
--

CREATE TABLE `ligne_ecriture_comptable` (
  `id_ligne_ecriture` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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
-- Structure de la table `ligne_facture_fournisseur`
--

CREATE TABLE `ligne_facture_fournisseur` (
  `id_ligne_facture` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_facture` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `remise` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_remise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ht` decimal(15,2) NOT NULL,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_tva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_livraison_client`
--

CREATE TABLE `ligne_livraison_client` (
  `id_ligne_livraison` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_livraison` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_reception_fournisseur`
--

CREATE TABLE `ligne_reception_fournisseur` (
  `id_ligne_reception` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_reception` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantite` decimal(15,2) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `numero_lot` varchar(50) NOT NULL,
  `date_peremption` date DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE reception_fournisseur 
ADD COLUMN montant_total decimal(15,2) NOT NULL DEFAULT 0.00;


-- --------------------------------------------------------

--
-- Structure de la table `livraison_client`
--

CREATE TABLE `livraison_client` (
  `id_livraison` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_livraison` varchar(20) NOT NULL,
  `date_livraison` date NOT NULL,
  `id_client` int(11) NOT NULL,
  `id_commande` int(11) DEFAULT NULL,
  `id_depot` int(11) NOT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Facturé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `log_operation`
--

CREATE TABLE `log_operation` (
  `id_log` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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
-- Structure de la table `lot_produit`
--

CREATE TABLE `lot_produit` (
  `id_lot` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_lot` varchar(50) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `id_detail_entree` int(11) NOT NULL,
  `quantite_initiale` decimal(15,2) NOT NULL,
  `quantite_disponible` decimal(15,2) NOT NULL,
  `prix_unitaire_achat` decimal(15,2) NOT NULL,
  `prix_unitaire_vente` decimal(15,2) NOT NULL,
  `date_peremption` date DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


CREATE TABLE `mode_paiement` (
  `id_mode_paiement` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_mode` varchar(10) NOT NULL,
  `libelle_mode` varchar(50) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `operation_bancaire` (
  `id_operation` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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
  `id_operation` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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

CREATE TABLE `paiement_client` (
  `id_paiement` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_paiement` varchar(20) NOT NULL,
  `date_paiement` date NOT NULL,
  `id_client` int(11) NOT NULL,
  `id_facture` int(11) DEFAULT NULL,
  `montant` decimal(15,2) NOT NULL,
  `id_mode_paiement` int(11) NOT NULL,
  `reference_paiement` varchar(50) DEFAULT NULL,
  `id_caisse` int(11) DEFAULT NULL,
  `id_compte_bancaire` int(11) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement_fournisseur`
--

CREATE TABLE `paiement_fournisseur` (
  `id_paiement` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_paiement` varchar(20) NOT NULL,
  `date_paiement` date NOT NULL,
  `id_fournisseur` int(11) NOT NULL,
  `id_facture` int(11) DEFAULT NULL,
  `montant` decimal(15,2) NOT NULL,
  `id_mode_paiement` int(11) NOT NULL,
  `reference_paiement` varchar(50) DEFAULT NULL,
  `id_caisse` int(11) DEFAULT NULL,
  `id_compte_bancaire` int(11) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `unite_mesure` (
  `id_unite` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_unite` varchar(10) NOT NULL,
  `libelle_unite` varchar(50) NOT NULL,
  `symbole_unite` varchar(100) NOT NULL,
  `actif` int(11) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `produit` (
  `id_produit` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_produit` varchar(30) NOT NULL,
  `libelle_produit` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `id_categorie` int(11) NOT NULL,
  `type_produit` varchar(50) NOT NULL,
  `famille` varchar(50) DEFAULT NULL,
  `id_unite_stockage` int(11) NOT NULL,
  `id_unite_vente` int(11) NOT NULL,
  `conditionnement` decimal(10,2) NOT NULL DEFAULT 1.00,
  `marge_beneficiaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_produit` varchar(255) DEFAULT NULL,
  `poids` decimal(10,3) DEFAULT NULL,
  `volume` decimal(10,3) DEFAULT NULL,
  `id_compte_comptable` int(11) NOT NULL,
  `est_stock_suivi` tinyint(1) NOT NULL DEFAULT 1,
  `est_peremption_suivi` tinyint(1) NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `produit` 
ADD COLUMN `seuil_min` decimal(10,2) NOT NULL DEFAULT 0.00,
ADD COLUMN `seuil_max` decimal(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE reception_fournisseur ADD COLUMN reference_bl VARCHAR(50) DEFAULT NULL;
ALTER TABLE reception_fournisseur ADD COLUMN id_entree_stock INT(11) DEFAULT NULL;

-- --------------------------------------------------------

--
-- Structure de la table `produit_fournisseur`
--

CREATE TABLE `produit_fournisseur` (
  `id_produit_fournisseur` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_produit` int(11) NOT NULL,
  `id_fournisseur` int(11) NOT NULL,
  `prix_achat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `delai_livraison` int(11) DEFAULT NULL,
  `est_fournisseur_principal` tinyint(1) NOT NULL DEFAULT 0,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `rapport_financier` (
  `id_rapport` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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



CREATE TABLE `sortie_stock` (
  `id_sortie` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_sortie` varchar(20) NOT NULL,
  `date_sortie` date NOT NULL,
  `id_depot` int(11) NOT NULL,
  `type_sortie` enum('Vente','Transfert','Inventaire','Perte','Autre') NOT NULL,
  `reference_document` varchar(30) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE sortie_stock ADD COLUMN montant_total decimal(15,2) NOT NULL DEFAULT 0.00;


-- --------------------------------------------------------

--
-- Structure de la table `succursale`
--

CREATE TABLE `succursale` (
  `id_succursale` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_succursale` varchar(10) NOT NULL,
  `nom_succursale` varchar(100) NOT NULL,
  `adresse` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


CREATE TABLE `transfert_stock` (
  `id_transfert` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `numero_transfert` varchar(20) NOT NULL,
  `date_transfert` date NOT NULL,
  `id_depot_source` int(11) NOT NULL,
  `id_depot_destination` int(11) NOT NULL,
  `observation` text DEFAULT NULL,
  `etat` enum('En cours','Validé','Annulé') NOT NULL DEFAULT 'En cours',
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user_validation` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `t_users`
--

CREATE TABLE `t_users` (
  `idUser` int(255) PRIMARY KEY NOT NULL AUTO_INCREMENT,
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

-- Structure de la table pour les autorisations d'accès aux dépôts
CREATE TABLE `autorisation_depot` (
  `id_autorisation` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_depot` int(11) NOT NULL,
  `peut_consulter` tinyint(1) NOT NULL DEFAULT 1,
  `peut_modifier` tinyint(1) NOT NULL DEFAULT 0,
  `peut_valider` tinyint(1) NOT NULL DEFAULT 0,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `uk_user_depot` (`id_user`, `id_depot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
