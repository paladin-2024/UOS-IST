-- Script pour insérer des données de test pour les chefs de promotion
-- Assurez-vous d'adapter les IDs selon votre base de données

-- Exemple d'insertion d'un chef de promotion
-- Remplacez les valeurs par les IDs réels de votre base de données

-- INSERT INTO chef_promotion (idetudiant, promotion_idpromotion, annee_acad_idannee_acad, date_nomination, est_actif, idUser)
-- VALUES 
-- (1, 1, 1, CURDATE(), 1, 1);

-- Pour vérifier les étudiants existants :
-- SELECT e.idetudiant, e.matricule, e.noms, p.designationPromotion, aa.designation as annee_acad
-- FROM etudiant e
-- INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
-- INNER JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
-- WHERE e.est_actif = 1
-- ORDER BY p.designationPromotion, e.noms;

-- Pour vérifier les chefs de promotion existants :
-- SELECT cp.*, e.matricule, e.noms, p.designationPromotion
-- FROM chef_promotion cp
-- INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant
-- INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
-- WHERE cp.est_actif = 1;