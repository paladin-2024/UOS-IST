-- Configuration financière globale
CREATE TABLE IF NOT EXISTS `config_finance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `est_actif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Comptes bancaires de l'université
CREATE TABLE IF NOT EXISTS `comptes_bancaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_compte` (`numero_compte`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Caisses de l'université
CREATE TABLE IF NOT EXISTS `caisses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sessions de caisse (ouverture/fermeture)
CREATE TABLE IF NOT EXISTS `sessions_caisse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idValidateur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sessions_caisse` (`caisse_id`),
  KEY `fk_sessions_agent` (`idAgent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Catégories de frais académiques
CREATE TABLE IF NOT EXISTS `categories_frais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `est_obligatoire` tinyint(1) DEFAULT 1,
  `est_echelonnable` tinyint(1) DEFAULT 0,
  `est_remboursable` tinyint(1) DEFAULT 0,
  `compte_comptable` varchar(50) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `frais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_frais_categorie` (`categorie_id`),
  KEY `fk_frais_annee_acad` (`annee_acad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tranches_paiement_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tranche_config` (`frais_id`, `numero_tranche`)
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

CREATE TABLE IF NOT EXISTS `orientation` (
  `idorientation` int(11) NOT NULL,
  `designationOrientation` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp(),
  `section_idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `section` (
  `idsection` int(11) NOT NULL,
  `designationSection` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `idAnnee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `affectation_frais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_affectation_frais_2` (`frais_id`),
  KEY `fk_affectation_promotion` (`promotion_id`),
  KEY `fk_affectation_etudiant` (`etudiant_id`),
  KEY `idx_matricule_affectation` (`matricule_etudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `echelonnement_paiement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `affectation_id` int(11) NOT NULL,
  `numero_tranche` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `pourcentage` decimal(5,2) DEFAULT NULL,
  `date_echeance` date NOT NULL,
  `est_requis_inscription` tinyint(1) DEFAULT 0,
  `est_requis_examens` tinyint(1) DEFAULT 0,
  `est_requis_deliberation` tinyint(1) DEFAULT 0,
  `statut_paiement` enum('Non payé','Partiel','Complet') DEFAULT 'Non payé',
  `montant_paye` decimal(15,2) DEFAULT 0.00,
  `montant_restant` decimal(15,2) DEFAULT NULL,
  `date_dernier_paiement` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_echelonnement_affectation` (`affectation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Transactions financières (entrées et sorties d'argent)
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `type` enum('Recette','Dépense','Transfert','Ajustement') NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `taux_change` decimal(15,6) DEFAULT NULL,
  `montant_devise_principale` decimal(15,2) DEFAULT NULL,
  `date_transaction` datetime NOT NULL,
  `source` enum('Caisse','Banque') NOT NULL,
  `source_id` int(11) NOT NULL COMMENT 'ID de la caisse ou du compte bancaire',
  `destination_id` int(11) DEFAULT NULL COMMENT 'Pour les transferts',
  `categorie_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `pieces_jointes` varchar(255) DEFAULT NULL,
  `statut` enum('Provisoire','Confirmée','Annulée') NOT NULL DEFAULT 'Provisoire',
  `motif_annulation` text DEFAULT NULL,
  `idAgent` int(11) NOT NULL,
  `session_caisse_id` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  `date_validation` datetime DEFAULT NULL,
  `idValidateur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference` (`reference`),
  KEY `idx_transactions_date` (`date_transaction`),
  KEY `idx_transactions_source` (`source`, `source_id`),
  KEY `idx_transactions_session` (`session_caisse_id`)
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

-- Table pour suivre les paiements de chaque étudiant pour un frais affecté à une promotion
CREATE TABLE IF NOT EXISTS `suivi_paiements_promotion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `affectation_id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `matricule_etudiant` varchar(245) NOT NULL,
  `montant_specifique` decimal(15,2) DEFAULT NULL COMMENT 'Montant individuel si différent',
  `montant_paye` decimal(15,2) DEFAULT 0.00,
  `montant_restant` decimal(15,2) DEFAULT NULL,
  `statut_paiement` enum('Non payé','Partiel','Complet') DEFAULT 'Non payé',
  `date_dernier_paiement` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_affectation_etudiant` (`affectation_id`,`etudiant_id`),
  KEY `fk_suivi_affectation` (`affectation_id`),
  KEY `fk_suivi_etudiant` (`etudiant_id`),
  KEY `idx_suivi_matricule` (`matricule_etudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `echelonnement_paiement_etudiant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `echelonnement_id` int(11) NOT NULL COMMENT 'Référence à l''échelonnement principal',
  `affectation_id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `matricule_etudiant` varchar(245) NOT NULL,
  `numero_tranche` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `date_echeance` date NOT NULL,
  `statut_paiement` enum('Non payé','Partiel','Complet') DEFAULT 'Non payé',
  `montant_paye` decimal(15,2) DEFAULT 0.00,
  `date_dernier_paiement` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_echelonnement_etudiant` (`echelonnement_id`,`etudiant_id`),
  KEY `fk_echelonnement_etudiant` (`etudiant_id`),
  KEY `fk_echelonnement_affectation` (`affectation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Paiements des frais par les étudiants
CREATE TABLE IF NOT EXISTS `paiements_frais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `matricule_etudiant` varchar(245) DEFAULT NULL,
  `affectation_id` int(11) NOT NULL,
  `echelonnement_id` int(11) DEFAULT NULL,
  `montant` decimal(15,2) NOT NULL,
  `mode_paiement` enum('Espèces','Chèque','Virement','Mobile Money','Carte bancaire','Autre') NOT NULL,
  `reference_externe` varchar(100) DEFAULT NULL,
  `date_valeur` date DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `recu_numero` varchar(50) DEFAULT NULL,
  `recu_fichier` varchar(255) DEFAULT NULL,
  `est_confirme` tinyint(1) DEFAULT 0,
  `date_confirmation` datetime DEFAULT NULL,
  `idConfirmateur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_paiements_transaction` (`transaction_id`),
  KEY `fk_paiements_etudiant` (`etudiant_id`),
  KEY `fk_paiements_affectation` (`affectation_id`),
  KEY `idx_matricule_paiement` (`matricule_etudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE paiements_frais ADD COLUMN devise VARCHAR(10) NOT NULL DEFAULT 'USD' AFTER montant;

CREATE TABLE IF NOT EXISTS `paiements_tranches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `echelonnement_id` int(11) NOT NULL,
  `paiement_id` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_paiement_tranche_echelonnement` (`echelonnement_id`),
  KEY `fk_paiement_tranche_paiement` (`paiement_id`),
  CONSTRAINT `fk_paiement_tranche_echelonnement` FOREIGN KEY (`echelonnement_id`) REFERENCES `echelonnement_paiement` (`id`),
  CONSTRAINT `fk_paiement_tranche_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `paiements_frais` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Exercices budgétaires
CREATE TABLE IF NOT EXISTS `exercices_budgetaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `designation` varchar(100) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `est_actif` tinyint(1) DEFAULT 0,
  `est_cloture` tinyint(1) DEFAULT 0,
  `date_cloture` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Plan comptable
CREATE TABLE IF NOT EXISTS `plan_comptable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `type` enum('Actif','Passif','Charge','Produit') NOT NULL,
  `niveau` int(11) NOT NULL DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `est_analytique` tinyint(1) DEFAULT 0,
  `est_budgetaire` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code_compte` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Catégories budgétaires
CREATE TABLE IF NOT EXISTS `categories_budget` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('Recette','Dépense') NOT NULL,
  `compte_comptable_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  -- Suite de la table catégories_budget
  `niveau` int(11) NOT NULL DEFAULT 1,
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code_categorie` (`code`),
  KEY `fk_categories_compte` (`compte_comptable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Budget de l'université
CREATE TABLE IF NOT EXISTS `budget` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exercice_id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `montant_prevu` decimal(15,2) NOT NULL,
  `montant_revise` decimal(15,2) DEFAULT NULL,
  `montant_engage` decimal(15,2) DEFAULT 0.00,
  `montant_realise` decimal(15,2) DEFAULT 0.00,
  `disponible` decimal(15,2) DEFAULT 0.00,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_budget_categorie_exercice` (`exercice_id`,`categorie_id`),
  KEY `fk_budget_exercice` (`exercice_id`),
  KEY `fk_budget_categorie` (`categorie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE transactions ADD COLUMN beneficiaire VARCHAR(255) DEFAULT NULL AFTER idValidateur;


-- États de besoin (demandes de dépenses)
CREATE TABLE IF NOT EXISTS `etats_besoin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference_besoin` (`reference`),
  KEY `fk_besoin_categorie` (`categorie_budget_id`),
  KEY `fk_besoin_exercice` (`exercice_id`),
  KEY `fk_besoin_demandeur` (`demandeur_id`),
  KEY `fk_besoin_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Détails des états de besoin
CREATE TABLE IF NOT EXISTS `details_etats_besoin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `etat_besoin_id` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantite` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unite_mesure` varchar(50) DEFAULT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_details_etat_besoin` (`etat_besoin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Validations des états de besoin
CREATE TABLE IF NOT EXISTS `validations_etats_besoin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `etat_besoin_id` int(11) NOT NULL,
  `etape` varchar(100) NOT NULL,
  `decision` enum('Approuvé','Rejeté','En attente information','Modification demandée') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_validation` datetime DEFAULT current_timestamp(),
  `validateur_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_validations_etat_besoin` (`etat_besoin_id`),
  KEY `fk_validations_validateur` (`validateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Dépenses
CREATE TABLE IF NOT EXISTS `depenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_depense_transaction` (`transaction_id`),
  KEY `fk_depense_engagement` (`engagement_id`),
  KEY `fk_depense_categorie` (`categorie_budget_id`),
  KEY `fk_depense_exercice` (`exercice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Opérations de rapprochement bancaire
CREATE TABLE IF NOT EXISTS `rapprochements_bancaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idValidateur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rapprochement_compte` (`compte_bancaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Détails des rapprochements bancaires
CREATE TABLE IF NOT EXISTS `details_rapprochements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_details_rapprochement` (`rapprochement_id`),
  KEY `fk_details_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Historique des soldes
CREATE TABLE IF NOT EXISTS `historique_soldes` (
  -- Suite de la table historique_soldes
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_solde_source_date` (`type`,`source_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bénéficiaires des paiements
CREATE TABLE IF NOT EXISTS `beneficiaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_beneficiaire_type_ref` (`type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Autorisations de paiement
CREATE TABLE IF NOT EXISTS `autorisations_paiement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idExecuteur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference_autorisation` (`reference`),
  KEY `fk_autorisation_beneficiaire` (`beneficiaire_id`),
  KEY `fk_autorisation_besoin` (`etat_besoin_id`),
  KEY `fk_autorisation_engagement` (`engagement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Alertes et notifications financières
CREATE TABLE IF NOT EXISTS `alertes_financieres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('Budget','Caisse','Échéance','Rapprochement','Validation','Autre') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `severite` enum('Info','Warning','Danger','Success') NOT NULL DEFAULT 'Info',
  `lien` varchar(255) DEFAULT NULL,
  `est_lu` tinyint(1) DEFAULT 0,
  `date_lecture` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `destinataire_id` int(11) DEFAULT NULL,
  `role_destinataire` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alertes_destinataire` (`destinataire_id`),
  KEY `idx_alertes_role` (`role_destinataire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Situation financière des étudiants
CREATE TABLE IF NOT EXISTS `situation_financiere_etudiant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `etudiant_id` int(11) NOT NULL,
  `matricule_etudiant` varchar(245) DEFAULT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `total_du` decimal(15,2) DEFAULT 0.00,
  `total_paye` decimal(15,2) DEFAULT 0.00,
  `solde` decimal(15,2) DEFAULT 0.00,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `date_derniere_maj` datetime DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_situation_etudiant_annee` (`etudiant_id`,`annee_acad_id`),
  KEY `fk_situation_etudiant` (`etudiant_id`),
  KEY `fk_situation_annee` (`annee_acad_id`),
  KEY `idx_matricule_situation` (`matricule_etudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Droits d'accès spécifiques aux finances
CREATE TABLE IF NOT EXISTS `droits_acces_finances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idUser` int(11) NOT NULL,
  `type` enum('Caisse','Banque','Budget','Validation','Rapports') NOT NULL,
  `niveau` enum('Lecture','Écriture','Validation','Administration') NOT NULL DEFAULT 'Lecture',
  `entite_id` int(11) DEFAULT NULL COMMENT 'ID de la caisse, banque, etc. concernée',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idCreateur` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_droits_user_type` (`idUser`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Objectifs financiers pluriannuels
CREATE TABLE IF NOT EXISTS `objectifs_financiers` (
 -- Suite de la table objectifs_financiers
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_objectif_planification` (`planification_id`),
  KEY `fk_objectif_categorie` (`categorie_budget_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Avances sur salaires
CREATE TABLE IF NOT EXISTS `avances_salaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `idValidateur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference_avance` (`reference`),
  KEY `fk_avance_agent` (`agent_id`),
  KEY `fk_avance_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Mouvements de remboursement des avances
CREATE TABLE IF NOT EXISTS `remboursements_avances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `avance_id` int(11) NOT NULL,
  `date_remboursement` date NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `mode_paiement` enum('Prélèvement salaire','Paiement direct') NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_remboursement_avance` (`avance_id`),
  KEY `fk_remboursement_avance_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour définir les types de paie et leurs paramètres
CREATE TABLE IF NOT EXISTS `types_remuneration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `designation` varchar(100) NOT NULL,
  `type_agent` enum('Enseignant','Administratif','Recherche') NOT NULL,
  `mode_calcul` enum('Forfaitaire','Horaire','Journalier','Mensuel','Par crédit') NOT NULL,
  `montant_base` decimal(15,2) NOT NULL,
  `devise` varchar(10) NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE echelonnement_paiement_etudiant 
ADD COLUMN date_creation datetime DEFAULT current_timestamp() AFTER date_dernier_paiement;
ALTER TABLE echelonnement_paiement_etudiant 
ADD COLUMN montant_restant decimal(15,2) DEFAULT NULL AFTER montant_paye;

CREATE TABLE `t_user_permissions` (
  `idUP` int(255) NOT NULL,
  `idRole` int(255) NOT NULL,
  `idPerm` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `t_roles` (
  `idRole` int(255) NOT NULL,
  `nomRole` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




