-- Ajouter les contraintes de clés étrangères manquantes à la table dette_etudiant
-- Note: Exécutez ce script après avoir créé la table dette_etudiant

-- Désactiver temporairement les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 0;

-- Ajouter les contraintes de clés étrangères
ALTER TABLE `dette_etudiant`
ADD CONSTRAINT `fk_dette_etudiant_matricule` 
    FOREIGN KEY (`matricule`) REFERENCES `etudiant`(`matricule`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_dette_ecue_id` 
    FOREIGN KEY (`ECUE_idECUE`) REFERENCES `ecue`(`idECUE`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_dette_ue_id` 
    FOREIGN KEY (`UE_idUE`) REFERENCES `ue`(`idUE`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_dette_semestre_id` 
    FOREIGN KEY (`semestre_idsemestre`) REFERENCES `semestre`(`idsemestre`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_dette_promotion_id` 
    FOREIGN KEY (`promotion_idpromotion`) REFERENCES `promotion`(`idpromotion`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_dette_session_id` 
    FOREIGN KEY (`session_idsession`) REFERENCES `session`(`idsession`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_dette_annee_id` 
    FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad`(`idannee_acad`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_dette_user_id` 
    FOREIGN KEY (`idUser`) REFERENCES `user`(`id`) 
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- Réactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 1;

-- Vérifier que les contraintes ont été ajoutées
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE 
    TABLE_NAME = 'dette_etudiant'
    AND CONSTRAINT_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL;