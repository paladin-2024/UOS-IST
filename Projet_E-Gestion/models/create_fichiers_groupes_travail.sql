-- Table pour stocker les fichiers PDF/Word par groupe pour les travaux pratiques
-- Utilisée quand l'option "Un fichier par groupe" est activée dans un TP de groupe

CREATE TABLE IF NOT EXISTS `fichiers_groupes_travail` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_devoir` INT NOT NULL COMMENT 'Référence au TP dans la table devoirs',
    `numero_groupe` INT NOT NULL COMMENT 'Numéro du groupe (1, 2, 3, ...)',
    `fichier` VARCHAR(255) NOT NULL COMMENT 'Nom du fichier uploadé dans uploads/travaux_cours/',
    `date_upload` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'upload du fichier',
    UNIQUE KEY `uk_devoir_groupe` (`id_devoir`, `numero_groupe`),
    CONSTRAINT `fk_fgt_devoir` FOREIGN KEY (`id_devoir`) REFERENCES `devoirs`(`iddevoir`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fichiers spécifiques par groupe pour les travaux pratiques avec fichier_par_groupe=1';
