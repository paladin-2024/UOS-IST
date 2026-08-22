-- Script SQL pour adapter la table chef_promotion existante et ajouter l'historique

-- La table chef_promotion existe déjà avec cette structure :
-- CREATE TABLE `chef_promotion` (
--   `id_chef` int(11) NOT NULL,
--   `idetudiant` int(11) NOT NULL,
--   `promotion_idpromotion` int(11) NOT NULL,
--   `annee_acad_idannee_acad` int(11) NOT NULL,
--   `date_nomination` date NOT NULL,
--   `est_actif` tinyint(1) NOT NULL DEFAULT 1,
--   `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
--   `idUser` int(11) NOT NULL
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vérifier si la clé primaire est AUTO_INCREMENT, sinon l'ajouter
ALTER TABLE `chef_promotion` MODIFY `id_chef` int(11) NOT NULL AUTO_INCREMENT;

-- Ajouter des index pour optimiser les performances si ils n'existent pas
ALTER TABLE `chef_promotion` 
ADD INDEX IF NOT EXISTS `idx_unique_chef_promotion_actif` (`promotion_idpromotion`, `annee_acad_idannee_acad`, `est_actif`),
ADD INDEX IF NOT EXISTS `idx_chef_promotion_etudiant` (`idetudiant`),
ADD INDEX IF NOT EXISTS `idx_chef_promotion_annee` (`annee_acad_idannee_acad`),
ADD INDEX IF NOT EXISTS `idx_chef_promotion_user` (`idUser`);

-- Table pour l'historique des nominations/retraits de chefs de promotion (optionnelle)
CREATE TABLE IF NOT EXISTS `chef_promotion_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `promotion_idpromotion` int(11) NOT NULL,
  `idetudiant` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `action` enum('ASSIGN','REMOVE','MODIFY') NOT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL,
  `idUser` int(11) NOT NULL COMMENT 'Utilisateur qui a effectué l''action',
  PRIMARY KEY (`id`),
  KEY `fk_chef_history_promotion` (`promotion_idpromotion`),
  KEY `fk_chef_history_etudiant` (`idetudiant`),
  KEY `fk_chef_history_annee` (`annee_acad_idannee_acad`),
  KEY `fk_chef_history_user` (`idUser`),
  KEY `idx_chef_history_date` (`date_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historique des actions sur les chefs de promotion';

-- Créer une vue pour faciliter les requêtes avec la structure existante
CREATE OR REPLACE VIEW `v_chef_promotion` AS
SELECT 
    cp.id_chef,
    cp.promotion_idpromotion as idpromotion,
    p.designationPromotion,
    cp.idetudiant,
    e.noms as chef_nom,
    e.matricule as chef_matricule,
    cp.annee_acad_idannee_acad,
    aa.designation as annee_designation,
    cp.date_nomination,
    cp.est_actif,
    spec.designation as specialisation,
    o.designationOrientation as orientation,
    s.designationSection as section,
    u.nomUser as nominateur
FROM chef_promotion cp
INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant
INNER JOIN annee_acad aa ON cp.annee_acad_idannee_acad = aa.idannee_acad
INNER JOIN specialisation spec ON p.idSpecialisation = spec.idSpecialisation
INNER JOIN orientation o ON spec.idorientation = o.idorientation
INNER JOIN section s ON o.section_idsection = s.idsection
INNER JOIN t_users u ON cp.idUser = u.idUser
WHERE cp.est_actif = 1;

-- Créer une vue pour l'historique (si la table d'historique est créée)
CREATE OR REPLACE VIEW `v_chef_promotion_history` AS
SELECT 
    cph.id,
    cph.promotion_idpromotion as idpromotion,
    p.designationPromotion,
    cph.idetudiant,
    e.noms as etudiant_nom,
    e.matricule as etudiant_matricule,
    cph.annee_acad_idannee_acad,
    aa.designation as annee_designation,
    cph.action,
    cph.date_action,
    cph.commentaire,
    u.nomUser as utilisateur
FROM chef_promotion_history cph
INNER JOIN promotion p ON cph.promotion_idpromotion = p.idpromotion
INNER JOIN etudiant e ON cph.idetudiant = e.idetudiant
INNER JOIN annee_acad aa ON cph.annee_acad_idannee_acad = aa.idannee_acad
INNER JOIN t_users u ON cph.idUser = u.idUser
ORDER BY cph.date_action DESC;

-- Exemples de requêtes utiles :

-- 1. Récupérer tous les chefs de promotion actifs pour une année donnée
-- SELECT * FROM v_chef_promotion WHERE annee_acad_idannee_acad = 1;

-- 2. Récupérer les promotions sans chef pour une année donnée
-- SELECT p.*, s.designationSection, spec.designation as specialisation
-- FROM promotion p
-- INNER JOIN specialisation spec ON p.idSpecialisation = spec.idSpecialisation
-- INNER JOIN orientation o ON spec.idorientation = o.idorientation
-- INNER JOIN section s ON o.section_idsection = s.idsection
-- LEFT JOIN chef_promotion cp ON p.idpromotion = cp.promotion_idpromotion 
--                              AND cp.annee_acad_idannee_acad = 1 
--                              AND cp.est_actif = 1
-- WHERE cp.id_chef IS NULL;

-- 3. Vérifier si un étudiant est déjà chef d'une promotion
-- SELECT p.designationPromotion 
-- FROM chef_promotion cp
-- INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
-- WHERE cp.idetudiant = ? AND cp.annee_acad_idannee_acad = ? AND cp.est_actif = 1;