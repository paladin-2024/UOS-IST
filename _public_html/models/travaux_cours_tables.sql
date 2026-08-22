-- Script de création des tables pour les travaux de cours
-- Ce script ajoute les champs nécessaires à la table devoirs existante
-- et crée les tables pour les groupes de travail

-- =====================================================
-- 1. Modification de la table devoirs pour ajouter les champs pour les travaux de groupe
-- =====================================================
-- Note: Les champs est_payant et idfrais existent déjà

ALTER TABLE `devoirs` 
ADD COLUMN IF NOT EXISTS `type_travail` ENUM('individuel', 'groupe') DEFAULT 'individuel' AFTER `est_payant`,
ADD COLUMN IF NOT EXISTS `max_etudiants_groupe` INT DEFAULT 0 AFTER `type_travail`,
ADD COLUMN IF NOT EXISTS `nombre_groupes` INT DEFAULT 0 AFTER `max_etudiants_groupe`,
ADD COLUMN IF NOT EXISTS `fichier_par_groupe` TINYINT(1) DEFAULT 0 AFTER `nombre_groupes`,
ADD COLUMN IF NOT EXISTS `prix_par_etudiant` DECIMAL(15,2) DEFAULT 0 AFTER `fichier_par_groupe`,
ADD COLUMN IF NOT EXISTS `prix_forfaitaire` DECIMAL(15,2) DEFAULT 0 AFTER `prix_par_etudiant`,
ADD COLUMN IF NOT EXISTS `type_prix_groupe` ENUM('forfaitaire', 'par_etudiant') DEFAULT 'forfaitaire' AFTER `prix_forfaitaire`,
ADD COLUMN IF NOT EXISTS `devise` ENUM('USD', 'CDF') DEFAULT 'USD' AFTER `type_prix_groupe`;

-- =====================================================
-- Ajout des colonnes nature et devoir_id dans transactions_flexpay
-- pour distinguer les paiements de frais académiques et de travaux pratiques
-- =====================================================
ALTER TABLE `transactions_flexpay`
ADD COLUMN IF NOT EXISTS `nature` ENUM('frais', 'travail', 'travail_groupe') DEFAULT 'frais' AFTER `affectation_frais_id`,
ADD COLUMN IF NOT EXISTS `devoir_id` INT NULL DEFAULT NULL AFTER `nature`,
ADD COLUMN IF NOT EXISTS `groupe_id` INT NULL DEFAULT NULL AFTER `devoir_id`;

-- =====================================================
-- 2. Table pour les groupes de travail
-- =====================================================

CREATE TABLE IF NOT EXISTS `groupes_travail` (
  `id_groupe` INT NOT NULL AUTO_INCREMENT,
  `id_devoir` INT NOT NULL,
  `numero_groupe` INT NOT NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `est_paye` TINYINT(1) DEFAULT 0,
  `montant_paye` DECIMAL(15,2) DEFAULT 0,
  `date_paiement` DATETIME DEFAULT NULL,
  `reference_paiement` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id_groupe`),
  INDEX `idx_devoir` (`id_devoir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 3. Table pour les membres des groupes de travail
-- =====================================================

CREATE TABLE IF NOT EXISTS `membres_groupe_travail` (
  `id_membre` INT NOT NULL AUTO_INCREMENT,
  `id_groupe` INT NOT NULL,
  `id_etudiant` INT NOT NULL,
  `est_createur` TINYINT(1) DEFAULT 0,
  `date_join` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_membre`),
  UNIQUE INDEX `unique_groupe_etudiant` (`id_groupe`, `id_etudiant`),
  INDEX `idx_etudiant` (`id_etudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 4. Table pour les paiements des travaux (distincte des paiements de frais académiques)
-- =====================================================

CREATE TABLE IF NOT EXISTS `paiements_travaux` (
  `id_paiement` INT NOT NULL AUTO_INCREMENT,
  `id_groupe` INT DEFAULT NULL,
  `id_etudiant` INT NOT NULL,
  `id_devoir` INT NOT NULL,
  `montant` DECIMAL(15,2) NOT NULL,
  `mode_paiement` VARCHAR(50) DEFAULT 'mobile_money',
  `reference_transaction` VARCHAR(255) DEFAULT NULL,
  `order_number_flexpay` VARCHAR(255) DEFAULT NULL,
  `statut` ENUM('en_attente', 'reussi', 'echoue', 'annule') DEFAULT 'en_attente',
  `date_paiement` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_confirmation` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_paiement`),
  INDEX `idx_groupe` (`id_groupe`),
  INDEX `idx_etudiant` (`id_etudiant`),
  INDEX `idx_devoir` (`id_devoir`),
  INDEX `idx_order` (`order_number_flexpay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 5. Table pour le suivi des paiements en temps réel pour l'enseignant
-- =====================================================

CREATE TABLE IF NOT EXISTS `suivi_paiements_travaux` (
  `id_suivi` INT NOT NULL AUTO_INCREMENT,
  `id_devoir` INT NOT NULL,
  `id_etudiant` INT DEFAULT NULL,
  `id_groupe` INT DEFAULT NULL,
  `type_paiement` ENUM('individuel', 'groupe') NOT NULL,
  `montant` DECIMAL(15,2) NOT NULL,
  `statut` ENUM('en_attente', 'reussi', 'echoue') DEFAULT 'en_attente',
  `date_suivi` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_suivi`),
  INDEX `idx_devoir` (`id_devoir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
