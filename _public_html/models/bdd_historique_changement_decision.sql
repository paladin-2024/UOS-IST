-- Table pour enregistrer l'historique des changements de décision
CREATE TABLE IF NOT EXISTS `historique_changement_decision` (
    `id_historique` INT AUTO_INCREMENT PRIMARY KEY,
    `matricule` VARCHAR(255) NOT NULL,
    `ancienne_decision` VARCHAR(50),
    `nouvelle_decision` VARCHAR(50),
    `promotion_id` INT,
    `session_id` INT,
    `annee_acad_id` INT,
    `nb_dettes_supprimees` INT DEFAULT 0,
    `date_changement` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT,
    INDEX `idx_matricule` (`matricule`),
    INDEX `idx_annee` (`annee_acad_id`),
    INDEX `idx_date` (`date_changement`),
    FOREIGN KEY (`promotion_id`) REFERENCES `promotion`(`idpromotion`) ON DELETE SET NULL,
    FOREIGN KEY (`session_id`) REFERENCES `session`(`idsession`) ON DELETE SET NULL,
    FOREIGN KEY (`annee_acad_id`) REFERENCES `annee_acad`(`idannee_acad`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `t_users`(`idUser`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vue pour faciliter la consultation de l'historique
CREATE OR REPLACE VIEW `v_historique_changement_decision` AS
SELECT 
    h.id_historique,
    h.matricule,
    e.noms AS nom_etudiant,
    h.ancienne_decision,
    h.nouvelle_decision,
    h.nb_dettes_supprimees,
    p.designationPromotion,
    s.designSession,
    a.designation AS annee_academique,
    h.date_changement,
    u.nomUser AS modifie_par
FROM historique_changement_decision h
LEFT JOIN etudiant e ON h.matricule = e.matricule
LEFT JOIN promotion p ON h.promotion_id = p.idpromotion
LEFT JOIN session s ON h.session_id = s.idsession
LEFT JOIN annee_acad a ON h.annee_acad_id = a.idannee_acad
LEFT JOIN t_users u ON h.created_by = u.idUser
ORDER BY h.date_changement DESC;