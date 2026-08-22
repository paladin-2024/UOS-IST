-- Migration: Create frais_transferred table for tracking fee transfers between promotions
-- Date: 2026-04-06
-- Description: Table to track fees that have been transferred when students change promotion through orientation choice

CREATE TABLE IF NOT EXISTS frais_transferred (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    frais_id INT NOT NULL,
    montant_transferred DECIMAL(12,2) NOT NULL,
    promotion_source_id INT NOT NULL,
    promotion_cible_id INT NOT NULL,
    choix_orientation_id INT,
    date_transfert DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_etudiant (etudiant_id),
    INDEX idx_frais (frais_id),
    INDEX idx_promotion_cible (promotion_cible_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
