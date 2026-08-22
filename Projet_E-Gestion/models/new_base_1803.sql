
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
-- Structure de la table `admin_info`
--

CREATE TABLE `admin_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `direction` varchar(255) DEFAULT NULL,
  `division` varchar(255) DEFAULT NULL,
  `decision_grade` varchar(255) DEFAULT NULL,
  `notification_grade` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
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
-- Structure de la table `annee_acad`
--

CREATE TABLE `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
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
-- Structure de la table `budget`
--

CREATE TABLE `budget` (
  `id_budget` int(11) NOT NULL,
  `code_budget` varchar(20) NOT NULL,
  `libelle_budget` varchar(100) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `montant_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `etat` enum('En cours','Validé','Clôturé') NOT NULL DEFAULT 'En cours',
  `description` text DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
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
-- Structure de la table `caisse`
--

CREATE TABLE `caisse` (
  `id_caisse` int(11) NOT NULL,
  `code_caisse` varchar(10) NOT NULL,
  `libelle_caisse` varchar(100) NOT NULL,
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
-- Structure de la table `categorie_produit`
--

CREATE TABLE `categorie_produit` (
  `id_categorie` int(11) NOT NULL,
  `code_categorie` varchar(20) NOT NULL,
  `libelle_categorie` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` int(11) NOT NULL,
  `id_compte_comptable` int(11) DEFAULT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `client`
--

CREATE TABLE `client` (
  `id_client` int(11) NOT NULL,
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
  `id_commande` int(11) NOT NULL,
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
  `id_commande` int(11) NOT NULL,
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
  `id_compte_bancaire` int(11) NOT NULL,
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
  `calculer_moyenne_avec_notes_vides` tinyint(1) DEFAULT 0 COMMENT 'Calculer la moyenne même si certaines notes sont vides'
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
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Structure de la table `demande_prix`
--

CREATE TABLE `demande_prix` (
  `id_demande_prix` int(11) NOT NULL,
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
  `id_depot` int(11) NOT NULL,
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
-- Structure de la table `detail_entree_stock`
--

CREATE TABLE `detail_entree_stock` (
  `id_detail_entree` int(11) NOT NULL,
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
  `id_detail_inventaire` int(11) NOT NULL,
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
  `id_detail_livraison_lot` int(11) NOT NULL,
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
-- Structure de la table `detail_sortie_lot`
--

CREATE TABLE `detail_sortie_lot` (
  `id_detail_sortie_lot` int(11) NOT NULL,
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
  `id_detail_sortie` int(11) NOT NULL,
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
  `id_detail_transfert` int(11) NOT NULL,
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
  `id_devis` int(11) NOT NULL,
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
-- Structure de la table `document_produit`
--

CREATE TABLE `document_produit` (
  `id_document` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `type_document` varchar(50) NOT NULL,
  `titre_document` varchar(255) NOT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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


-- --------------------------------------------------------

--
-- Structure de la table `entree_stock`
--

CREATE TABLE `entree_stock` (
  `id_entree` int(11) NOT NULL,
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
  `idUser` int(11) NOT NULL
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
-- Structure de la table `facture_client`
--

CREATE TABLE `facture_client` (
  `id_facture` int(11) NOT NULL,
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
  `id_facture` int(11) NOT NULL,
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
-- Structure de la table `fournisseur`
--

CREATE TABLE `fournisseur` (
  `id_fournisseur` int(11) NOT NULL,
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
-- Structure de la table `frais`
--

CREATE TABLE `frais` (
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
  `date_activite` datetime NOT NULL DEFAULT current_timestamp()
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
-- Structure de la table `ligne_commande_client`
--

CREATE TABLE `ligne_commande_client` (
  `id_ligne_commande` int(11) NOT NULL,
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
  `id_ligne_commande` int(11) NOT NULL,
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
  `id_ligne_demande` int(11) NOT NULL,
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
  `id_ligne_devis` int(11) NOT NULL,
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
-- Structure de la table `ligne_facture_fournisseur`
--

CREATE TABLE `ligne_facture_fournisseur` (
  `id_ligne_facture` int(11) NOT NULL,
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
  `id_ligne_livraison` int(11) NOT NULL,
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
  `id_ligne_reception` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Structure de la table `livraison_client`
--

CREATE TABLE `livraison_client` (
  `id_livraison` int(11) NOT NULL,
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
-- Structure de la table `lot_produit`
--

CREATE TABLE `lot_produit` (
  `id_lot` int(11) NOT NULL,
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
-- Structure de la table `paiement_client`
--

CREATE TABLE `paiement_client` (
  `id_paiement` int(11) NOT NULL,
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
  `id_paiement` int(11) NOT NULL,
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
-- Structure de la table `produit`
--

CREATE TABLE `produit` (
  `id_produit` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Structure de la table `produit_fournisseur`
--

CREATE TABLE `produit_fournisseur` (
  `id_produit_fournisseur` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `id_fournisseur` int(11) NOT NULL,
  `prix_achat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `delai_livraison` int(11) DEFAULT NULL,
  `est_fournisseur_principal` tinyint(1) NOT NULL DEFAULT 0,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
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
-- Structure de la table `reception_fournisseur`
--

CREATE TABLE `reception_fournisseur` (
  `id_reception` int(11) NOT NULL,
  `numero_reception` varchar(20) NOT NULL,
  `date_reception` date NOT NULL,
  `id_fournisseur` int(11) NOT NULL,
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
-- Structure de la table `sortie_stock`
--

CREATE TABLE `sortie_stock` (
  `id_sortie` int(11) NOT NULL,
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
  `idsection` int(11) NOT NULL
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
-- Structure de la table `succursale`
--

CREATE TABLE `succursale` (
  `id_succursale` int(11) NOT NULL,
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
  `statut_validation` enum('En attente','Validé','Rejeté','Modifié') NOT NULL DEFAULT 'En attente',
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
-- Structure de la table `transfert_stock`
--

CREATE TABLE `transfert_stock` (
  `id_transfert` int(11) NOT NULL,
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
-- Structure de la table `travaux_scientifiques`
--

CREATE TABLE `travaux_scientifiques` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `type_document` enum('Mémoire','Thèse','Rapport de stage','Article scientifique','Projet tutoré','Livre','Cours','Mémoire Master Complémentaire') NOT NULL,
  `nom_auteur` varchar(255) DEFAULT NULL,
  `type_auteur` enum('Etudiant','Enseignant','Autre') NOT NULL,
  `departement_id` int(11) DEFAULT NULL,
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
-- Structure de la table `unite_mesure`
--

CREATE TABLE `unite_mesure` (
  `id_unite` int(11) NOT NULL,
  `code_unite` varchar(10) NOT NULL,
  `libelle_unite` varchar(50) NOT NULL,
  `actif` int(11) NOT NULL,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Structure de la table `unite_recherche_section`
--

CREATE TABLE `unite_recherche_section` (
  `idur_section` int(11) NOT NULL,
  `idunite_recherche` int(11) NOT NULL,
  `idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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

