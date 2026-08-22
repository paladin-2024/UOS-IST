-- Insertion du menu pour le parcours étudiant
-- Ce script ajoute l'entrée de menu pour accéder au document de parcours étudiant

-- Vérifier si le module LMD existe, sinon le créer
SET @module_lmd_id = (SELECT idMod FROM t_modules WHERE nomMod = 'LMD' LIMIT 1);

-- Si le module n'existe pas, le créer
INSERT IGNORE INTO t_modules (idMod, nomMod, descriptionMod, couleurMod, iconMod, ordreMod, estActif)
VALUES (NULL, 'LMD', 'Gestion du système LMD (Licence-Master-Doctorat)', '#6f42c1', 'bi bi-mortarboard', 3, 1);

-- Récupérer l'ID du module LMD
SET @module_lmd_id = (SELECT idMod FROM t_modules WHERE nomMod = 'LMD' LIMIT 1);

-- Insérer le sous-module pour le parcours étudiant
INSERT IGNORE INTO t_sous_modules 
(idSousMod, nomSousMod, lienSousMod, iconSousMod, ordreSousMod, estActif, Modules_idMod)
VALUES 
(NULL, 'Parcours Étudiant', 'lmd/parcours_etudiant', 'bi bi-person-lines-fill', 5, 1, @module_lmd_id);

-- Récupérer l'ID du sous-module créé
SET @sous_mod_parcours = (SELECT idSousMod FROM t_sous_modules WHERE lienSousMod = 'lmd/parcours_etudiant' LIMIT 1);

-- Ajouter les permissions pour tous les rôles principaux
INSERT IGNORE INTO t_permissions 
(roles_idroles, SousModules_idSousMod, est_autorise)
VALUES 
-- Administrateur
(1, @sous_mod_parcours, 1),
-- Secrétaire Général
(2, @sous_mod_parcours, 1),
-- Chef de Section
(3, @sous_mod_parcours, 1),
-- Agent Comptable
(4, @sous_mod_parcours, 1),
-- Censeur
(5, @sous_mod_parcours, 1),
-- Responsable Académique
(6, @sous_mod_parcours, 1),
-- Enseignant
(7, @sous_mod_parcours, 1),
-- Étudiant (s'il existe)
(8, @sous_mod_parcours, 1);

SELECT 'Menu Parcours Étudiant ajouté avec succès!' as message;
