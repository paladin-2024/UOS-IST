-- Insertion du menu pour la gestion des dettes étudiantes
-- Ce script ajoute les entrées nécessaires dans les tables t_modules et t_sous_modules

-- Vérifier si le module LMD existe déjà, sinon le créer
INSERT INTO t_modules (idMod, nomMod, iconeMod, ordreMod, etatMod) 
SELECT * FROM (SELECT 20 as idMod, 'LMD' as nomMod, 'bi bi-mortarboard' as iconeMod, 20 as ordreMod, 1 as etatMod) AS tmp
WHERE NOT EXISTS (
    SELECT idMod FROM t_modules WHERE nomMod = 'LMD'
) LIMIT 1;

-- Récupérer l'ID du module LMD
SET @module_id = (SELECT idMod FROM t_modules WHERE nomMod = 'LMD' LIMIT 1);

-- Insérer le sous-module pour la gestion des dettes
INSERT INTO t_sous_modules (idSousMod, nomSousMod, lienSousMod, iconeSousMod, ordreSousMod, etatSousMod, idMod) 
VALUES 
    (NULL, 'Gestion des dettes', 'lmd/gestion_dettes', 'bi bi-credit-card-2-back', 1, 1, @module_id),
    (NULL, 'Bulletins de dettes', 'lmd/bulletins_dettes', 'bi bi-file-earmark-pdf', 2, 1, @module_id),
    (NULL, 'Rapports de dettes', 'lmd/rapports_dettes', 'bi bi-file-earmark-bar-graph', 3, 1, @module_id);

-- Ajouter les permissions pour les rôles 1 et 2 (Admin et Cellule LMD)
-- Récupérer les IDs des sous-modules créés
SET @sous_mod_gestion = (SELECT idSousMod FROM t_sous_modules WHERE lienSousMod = 'lmd/gestion_dettes' LIMIT 1);
SET @sous_mod_bulletins = (SELECT idSousMod FROM t_sous_modules WHERE lienSousMod = 'lmd/bulletins_dettes' LIMIT 1);
SET @sous_mod_rapports = (SELECT idSousMod FROM t_sous_modules WHERE lienSousMod = 'lmd/rapports_dettes' LIMIT 1);

-- Insérer les permissions pour le rôle Admin (idRole = 1)
INSERT INTO t_role_has_sous_module (idRole, idSousMod) 
VALUES 
    (1, @sous_mod_gestion),
    (1, @sous_mod_bulletins),
    (1, @sous_mod_rapports);

-- Insérer les permissions pour le rôle Cellule LMD (idRole = 2)
INSERT INTO t_role_has_sous_module (idRole, idSousMod) 
VALUES 
    (2, @sous_mod_gestion),
    (2, @sous_mod_bulletins),
    (2, @sous_mod_rapports);

-- Message de confirmation
SELECT 'Menu de gestion des dettes ajouté avec succès!' as message;