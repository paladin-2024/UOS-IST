CREATE TABLE `acces_archive` (
  `idacces` int(11) NOT NULL,
  `idarchive` int(11) NOT NULL,
  `idRole` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `agent_section` (
  `idagent_section` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idsection` int(11) NOT NULL,
  `dateAffectation` datetime DEFAULT current_timestamp(),
  `estPrincipal` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `annee_acad` (
  `idannee_acad` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `est_active` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `bureau_jury_promotion` (
  `id` int(11) NOT NULL,
  `idbureau` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `date_association` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `categories_doc` (
  `id_categorie` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `idStructure` int(11) NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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

CREATE TABLE `categorie_indicateur` (
  `idCategorie` int(11) NOT NULL,
  `nomCategorie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `travail_id` int(11) DEFAULT NULL,
  `date_consultation` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `conversation` (
  `idconversation` int(11) NOT NULL,
  `sujets_idsujets` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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


CREATE TABLE `depot_memoire` (
  `iddepot_memoire` int(11) NOT NULL,
  `dateDepot` date DEFAULT NULL,
  `fichier` varchar(145) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `sujets_idsujets` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `documents_prive` (
  `id_document` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `id_categorie` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `date_ajout` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `documents_public` (
  `id_document` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `id_categorie` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `date_ajout` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `document_agent` (
  `idDocument_agent` int(11) NOT NULL,
  `titre` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fichier` varchar(145) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `Agent_idAgent` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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

CREATE TABLE `ecard_access_keys` (
  `id` int(11) NOT NULL,
  `access_key` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `echanges_taches` (
  `idechange` int(11) NOT NULL,
  `dateEchange` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `fichierJoint` varchar(145) DEFAULT NULL,
  `taches_idtaches` int(11) NOT NULL,
  `type_auteur` enum('Directeur','Encadreur','Etudiant') NOT NULL,
  `idAuteur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `ecue_notes_verrouillage` (
  `id` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `idsession` int(11) NOT NULL,
  `idannee_acad` int(11) NOT NULL,
  `date_verrouillage` datetime NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `enseignant_ecue` (
  `idenseignant_ecue` int(11) NOT NULL,
  `poste` varchar(145) DEFAULT NULL,
  `idAgent` int(11) DEFAULT NULL,
  `idECUE` int(11) DEFAULT NULL,
  `anneeAcad` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `enseignant_specialisation` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `idSpecialisation` int(11) NOT NULL,
  `dateAffectation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `enseignant_uniterecherche` (
  `idAffectation` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `idSpecialisation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `etudiants_palmares_archives` (
  `idetudiant_palmares` int(11) NOT NULL,
  `idpalmares` int(11) NOT NULL,
  `matricule` varchar(50) NOT NULL,
  `nom_complet` varchar(255) NOT NULL,
  `pourcentage` decimal(5,2) DEFAULT NULL,
  `decision` varchar(50) NOT NULL,
  `session` varchar(50) NOT NULL DEFAULT 'Première session'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `etudiant_documents_historique` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `statut_precedent` enum('Valide','En attente de validation','Rejeté') NOT NULL,
  `nouveau_statut` enum('Valide','En attente de validation','Rejeté') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `etudiant_historique` (
  `id` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `idpromotion` int(11) NOT NULL,
  `idannee_acad` int(11) NOT NULL,
  `date_inscription` date NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `grade` (
  `idgrade` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type_agent` enum('Enseignant','Administratif','Recherche') DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `indicateur` (
  `idIndicateur` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `Idcategorie` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `journal_comptable` (
  `id_journal` int(11) NOT NULL,
  `code_journal` varchar(10) NOT NULL,
  `libelle_journal` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `jury_soutenance` (
  `idjury` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `role` enum('Président','Secrétaire','Membre','Lecteur 1','Lecteur 2') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `lecteurs_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `idenseignant` int(11) NOT NULL,
  `est_premier_lecteur` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `membre_bureau_jury` (
  `idmembre` int(11) NOT NULL,
  `idbureau` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `fonction` varchar(100) DEFAULT NULL,
  `date_ajout` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `mention_speciale` (
  `idmention` int(11) NOT NULL,
  `type_mention` enum('Félicitations','Encouragements','Avertissement','Blâme') NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `iddeliberation` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `mode_paiement` (
  `id_mode_paiement` int(11) NOT NULL,
  `code_mode` varchar(10) NOT NULL,
  `libelle_mode` varchar(50) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_user_creation` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
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

CREATE TABLE `notifications_documents` (
  `id` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `objet` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `orientation` (
  `idorientation` int(11) NOT NULL,
  `designationOrientation` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp(),
  `section_idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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

CREATE TABLE `paiements_tranches` (
  `id` int(11) NOT NULL,
  `echelonnement_id` int(11) NOT NULL,
  `paiement_id` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `palmares_historique` (
  `id_historique` int(11) NOT NULL,
  `id_palmares` int(11) NOT NULL,
  `action` enum('Creation','Modification','Suppression') NOT NULL,
  `details` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parties_cours` (
  `idpartie` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ordre` int(11) NOT NULL DEFAULT 1,
  `idECUE` int(11) NOT NULL,
  `estVisible` tinyint(1) DEFAULT 1,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `points` (
  `idpoints` int(11) NOT NULL,
  `coteObtenu` decimal(10,2) DEFAULT NULL,
  `typeEvaluation` int(11) DEFAULT NULL,
  `ECUE_idECUE` int(11) NOT NULL,
  `session_idsession` int(11) NOT NULL,
  `matricule` varchar(255) NOT NULL,
  `annee_acad_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ponderation_ecue` (
  `idponderation` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `coefficient` decimal(5,2) NOT NULL DEFAULT 1.00,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `prevision_production` (
  `idPrevision_production` int(11) NOT NULL,
  `dateDebut` date DEFAULT NULL,
  `dateFin` date DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Laboratoire_production_idLaboratoire_production` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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

CREATE TABLE `promotion` (
  `idpromotion` int(11) NOT NULL,
  `designationPromotion` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `cycle` enum('Premier','Deuxieme','Troisieme','') NOT NULL,
  `orientation_idorientation` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `est_terminale` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `recours_historique` (
  `id_historique` int(11) NOT NULL,
  `id_recours` int(11) NOT NULL COMMENT 'ID du recours concerné',
  `action` varchar(50) NOT NULL COMMENT 'Type d''action effectuée',
  `details` text DEFAULT NULL COMMENT 'Détails supplémentaires sur l''action',
  `date_action` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date et heure de l''action',
  `id_utilisateur` int(11) NOT NULL COMMENT 'ID de l''utilisateur ayant effectué l''action'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historique des actions effectuées sur les recours';

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

CREATE TABLE `remboursements_avances` (
  `id` int(11) NOT NULL,
  `avance_id` int(11) NOT NULL,
  `date_remboursement` date NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `mode_paiement` enum('Prélèvement salaire','Paiement direct') NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `research_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `unite_recherche` varchar(255) DEFAULT NULL,
  `projet_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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

CREATE TABLE `responsable_section` (
  `idresponsable_section` int(11) NOT NULL,
  `noms` varchar(245) DEFAULT NULL,
  `fonction` varchar(145) DEFAULT NULL,
  `signature` varchar(145) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `section_idsection` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `salle` (
  `idSalle` int(11) NOT NULL,
  `designationSalle` varchar(255) NOT NULL,
  `dateCreation` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `section` (
  `idsection` int(11) NOT NULL,
  `designationSection` varchar(245) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL,
  `idAnnee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `semestre` (
  `idsemestre` int(11) NOT NULL,
  `numeroSemestre` varchar(45) DEFAULT NULL,
  `dateEnregistrement` datetime DEFAULT NULL,
  `promotion_idpromotion` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `service` (
  `idService` int(11) NOT NULL,
  `designation` varchar(145) DEFAULT NULL,
  `Responsable` varchar(145) DEFAULT NULL,
  `Structure_idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `session` (
  `idsession` int(11) NOT NULL,
  `designSession` varchar(45) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `specialisation` (
  `idSpecialisation` int(11) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `dateCreation` datetime NOT NULL,
  `idUnite_recherche` int(11) NOT NULL,
  `idorientation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `statut_devoir_etudiant` (
  `id` int(11) NOT NULL,
  `iddevoir` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `statut` varchar(50) NOT NULL COMMENT 'Statut: Non commencé, Vu, Soumis, Noté, etc.',
  `date_modification` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `suivi_enseignement_ecue` (
  `id_suivi` int(11) NOT NULL,
  `idECUE` int(11) NOT NULL,
  `date_seance` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `matiere_vue` text NOT NULL,
  `nombre_heures_reelles` decimal(4,2) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `chef_promotion_id` int(11) NOT NULL COMMENT 'ID de l''étudiant chef de promotion',
  `date_encodage` datetime NOT NULL DEFAULT current_timestamp(),
  `statut_validation` enum('En attente','Validé','Rejeté') NOT NULL DEFAULT 'En attente',
  `date_validation` datetime DEFAULT NULL,
  `appariteur_id` int(11) DEFAULT NULL COMMENT 'ID de l''appariteur qui valide',
  `commentaire_validation` text DEFAULT NULL,
  `idUser_creation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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

CREATE TABLE `sujet_validation_history` (
  `id` int(11) NOT NULL,
  `idsujets` int(11) NOT NULL,
  `status` enum('En attente','Validé','Rejeté','Modifié') NOT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `teacher_info` (
  `id` int(11) NOT NULL,
  `idAgent` int(11) NOT NULL,
  `specialisation` varchar(255) DEFAULT NULL,
  `domaine_recherche` varchar(255) DEFAULT NULL,
  `idUser` int(11) NOT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tentatives_fraude_presence` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `idseance` int(11) NOT NULL,
  `type_seance` enum('cours','labo') NOT NULL,
  `matricule_tente` varchar(50) DEFAULT NULL,
  `date_tentative` datetime NOT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `typeevaluation` (
  `idType` int(11) NOT NULL,
  `designationT` varchar(155) NOT NULL,
  `categorie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `type_agent` (
  `id` int(11) NOT NULL,
  `libelle` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `type_conge` (
  `idtype_conge` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duree_standard` int(11) DEFAULT NULL COMMENT 'Durée standard en jours ouvrables',
  `est_cumulable` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `type_rendez_vous` (
  `idType_rendez_vous` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duree_defaut` int(11) DEFAULT 60,
  `couleur` varchar(7) DEFAULT '#007bff',
  `actif` tinyint(1) DEFAULT 1,
  `Service_idService` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `t_modules` (
  `idMod` int(255) NOT NULL,
  `nomMod` varchar(255) NOT NULL,
  `package` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `t_user_permissions` (
  `idUP` int(255) NOT NULL,
  `idRole` int(255) NOT NULL,
  `idPerm` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ue` (
  `idUE` int(11) NOT NULL,
  `codeUE` varchar(45) DEFAULT NULL,
  `designationUE` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `semestre_idsemestre` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `unite_recherche` (
  `idunite_recherche` int(11) NOT NULL,
  `designation_UR` varchar(245) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateCreation` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `unite_recherche_orientation` (
  `idur_orientation` int(11) NOT NULL,
  `idunite_recherche` int(11) NOT NULL,
  `idorientation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `unite_recherche_section` (
  `idur_section` int(11) NOT NULL,
  `idunite_recherche` int(11) NOT NULL,
  `idsection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_activite_projet` (
  `iduser_activite_projet` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Activite_projet_idActivite_projet` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `user_projet` (
  `iduser_projet` int(11) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `Projet_idProjet` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `user_structure` (
  `id_user_structure` int(11) NOT NULL,
  `toutvoir` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `idStructure` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `valeur_indicateur` (
  `idValeur` int(11) NOT NULL,
  `idStructure` int(11) NOT NULL,
  `idIndicateur` int(11) NOT NULL,
  `dateEnregistrement` date NOT NULL,
  `valeur` float DEFAULT NULL,
  `observation` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `validations_etats_besoin` (
  `id` int(11) NOT NULL,
  `etat_besoin_id` int(11) NOT NULL,
  `etape` varchar(100) NOT NULL,
  `decision` enum('Approuvé','Rejeté','En attente information','Modification demandée') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_validation` datetime DEFAULT current_timestamp(),
  `validateur_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `validation_notes_soutenance` (
  `id` int(11) NOT NULL,
  `idsoutenance` int(11) NOT NULL,
  `est_valide` tinyint(1) DEFAULT 0,
  `date_validation` datetime DEFAULT NULL,
  `id_validateur` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `est_visible` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- Table pour le suivi des enseignements par les chefs de promotion (version simplifiée)
CREATE TABLE IF NOT EXISTS `suivi_enseignements` (
  `id_suivi` int(11) NOT NULL AUTO_INCREMENT,
  `idECUE` int(11) NOT NULL COMMENT 'ID de la matière/ECUE',
  `date_cours` date NOT NULL COMMENT 'Date de la séance de cours',
  `heure_debut` time NOT NULL COMMENT 'Heure de début du cours',
  `heure_fin` time NOT NULL COMMENT 'Heure de fin du cours',
  `type_cours` enum('CM','TD','TP','Evaluation') NOT NULL DEFAULT 'CM' COMMENT 'Type de cours',
  `enseignant_id` int(11) DEFAULT NULL COMMENT 'ID de l\'enseignant (optionnel)',
  `salle` varchar(100) DEFAULT NULL COMMENT 'Salle de cours',
  `commentaire` text DEFAULT NULL COMMENT 'Commentaires ou observations',
  `annee_acad_idannee_acad` int(11) NOT NULL COMMENT 'Année académique',
  `date_encodage` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur ayant créé l\'enregistrement',
  PRIMARY KEY (`id_suivi`),
  KEY `idx_ecue` (`idECUE`),
  KEY `idx_enseignant` (`enseignant_id`),
  KEY `idx_annee_acad` (`annee_acad_idannee_acad`),
  KEY `idx_date_cours` (`date_cours`),
  KEY `idx_user` (`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Suivi des enseignements';

-- Table pour les liens d'inscription externe
CREATE TABLE `liens_inscription_externe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL UNIQUE,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `promotion_id` int(11) NOT NULL,
  `annee_acad_id` int(11) NOT NULL,
  `token_unique` varchar(255) NOT NULL UNIQUE,
  `url_complete` varchar(500) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `max_inscriptions` int(11) DEFAULT NULL COMMENT 'Nombre maximum d\'inscriptions autorisées',
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
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_promotion` (`promotion_id`),
  KEY `idx_annee_acad` (`annee_acad_id`),
  KEY `idx_token` (`token_unique`),
  KEY `idx_actif` (`est_actif`),
  FOREIGN KEY (`promotion_id`) REFERENCES `promotion`(`idpromotion`) ON DELETE CASCADE,
  FOREIGN KEY (`annee_acad_id`) REFERENCES `annee_acad`(`idannee_acad`) ON DELETE CASCADE,
  FOREIGN KEY (`idUser`) REFERENCES `t_users`(`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les documents requis spécifiques à un lien d'inscription
CREATE TABLE `lien_inscription_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lien_inscription_id` int(11) NOT NULL,
  `document_obligatoire_id` int(11) DEFAULT NULL COMMENT 'Référence au document standard si utilisé',
  `designation` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `est_obligatoire` tinyint(1) DEFAULT 1,
  `delai_jours` int(11) DEFAULT NULL,
  `ordre_affichage` int(11) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lien_inscription` (`lien_inscription_id`),
  KEY `idx_document_obligatoire` (`document_obligatoire_id`),
  FOREIGN KEY (`lien_inscription_id`) REFERENCES `liens_inscription_externe`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`document_obligatoire_id`) REFERENCES `documents_obligatoires`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les inscriptions via lien externe
CREATE TABLE `inscriptions_externes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lien_inscription_id` int(11) NOT NULL,
  `reference_inscription` varchar(50) NOT NULL UNIQUE,
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
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lien_inscription` (`lien_inscription_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_email` (`email`),
  FOREIGN KEY (`lien_inscription_id`) REFERENCES `liens_inscription_externe`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_validateur`) REFERENCES `t_users`(`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les documents soumis via inscription externe
CREATE TABLE `documents_inscription_externe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `id_validateur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inscription_externe` (`inscription_externe_id`),
  KEY `idx_lien_document` (`lien_document_id`),
  KEY `idx_statut_validation` (`statut_validation`),
  FOREIGN KEY (`inscription_externe_id`) REFERENCES `inscriptions_externes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lien_document_id`) REFERENCES `lien_inscription_documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_validateur`) REFERENCES `t_users`(`idUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;