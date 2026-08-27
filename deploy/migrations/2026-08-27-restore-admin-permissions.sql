-- Restores admin sidebar navigation for 14 modules that stopped rendering
-- because their t_user_permissions grants for role 1 (Administrateur)
-- pointed at idPerm values that no longer exist in t_permissions (103 of
-- role 1's 125 grants were dangling references -- confirmed pre-existing,
-- present already in the pre-wipe backup, unrelated to this week's work).
--
-- The underlying pages already work fine when visited directly by URL; this
-- only rebuilds the menu entries. Existing (even orphaned) grants are left
-- untouched -- this only adds new, valid permissions.
--
-- Idempotent: guarded by NOT EXISTS on (idMod, nomPerm), safe to re-run.

INSERT INTO t_permissions ("idMod", "codePerm", "nomPerm", "descPerm")
SELECT * FROM (VALUES
    (1, 'consulter', 'dashboard', 'Tableau de bord général'),
    (2, 'consulter', 'annee', 'Années académiques'),
    (2, 'modifier', 'roles', 'Gestion des rôles'),
    (2, 'modifier', 'permissions', 'Gestion des permissions'),
    (2, 'modifier', 'config_universite', 'Configuration de l''établissement'),
    (3, 'consulter', 'liste_etudiants', 'Liste des étudiants'),
    (3, 'consulter', 'reinscription_etudiants', 'Réinscriptions des étudiants'),
    (3, 'modifier', 'documents_obligatoires', 'Documents obligatoires'),
    (3, 'consulter', 'tableau_bord_inscriptions', 'Tableau de bord des inscriptions'),
    (4, 'consulter', 'agent.list', 'Liste du personnel'),
    (4, 'ajouter', 'agent.add', 'Ajouter un agent'),
    (4, 'consulter', 'conges.list', 'Gestion des congés'),
    (4, 'consulter', 'presence.list', 'Présences du personnel'),
    (8, 'consulter', 'rendez_vous.edit', 'Rendez-vous'),
    (8, 'consulter', 'courriel.list', 'Courriers'),
    (9, 'ajouter', 'projet.add', 'Créer un projet'),
    (9, 'consulter', 'projet.view', 'Voir les projets'),
    (9, 'consulter', 'activite.list', 'Liste des activités'),
    (11, 'consulter', 'dashboard', 'Tableau de bord financier'),
    (11, 'consulter', 'paiements_etudiants', 'Paiements des étudiants'),
    (11, 'modifier', 'config_finance', 'Configuration financière'),
    (11, 'consulter', 'rapport_paiements', 'Rapport des paiements'),
    (20, 'consulter', 'enseignant', 'Liste des enseignants'),
    (20, 'consulter', 'seances.list', 'Liste des séances'),
    (20, 'consulter', 'stats.presences', 'Statistiques de présence'),
    (26, 'consulter', 'sujets', 'Sujets de recherche'),
    (26, 'consulter', 'direction', 'Travaux par enseignant'),
    (26, 'consulter', 'soutenances', 'Soutenances'),
    (24, 'consulter', 'plan_directeur', 'Suivi des plans de travail'),
    (24, 'consulter', 'gestion_jurys', 'Gestion des jurys'),
    (24, 'consulter', 'mes_soutenances', 'Mes soutenances'),
    (29, 'consulter', 'pv_deliberation', 'Procès-verbal de délibération'),
    (29, 'consulter', 'resultats', 'Résultats'),
    (29, 'consulter', 'seances', 'Séances de délibération'),
    (28, 'consulter', 'unites_enseignement', 'Unités d''enseignement'),
    (28, 'consulter', 'ecues', 'ECUE'),
    (28, 'consulter', 'horaires', 'Horaires'),
    (28, 'consulter', 'suivi_enseignements', 'Suivi des enseignements'),
    (25, 'consulter', 'recours', 'Recours des dépôts'),
    (25, 'consulter', 'documents_etudiants', 'Documents étudiants'),
    (59, 'consulter', 'gestion_dettes', 'Gestion des dettes'),
    (59, 'consulter', 'parcours_etudiant', 'Parcours étudiant'),
    (59, 'consulter', 'rapports_dettes', 'Rapports des dettes')
) AS new_perms("idMod", "codePerm", "nomPerm", "descPerm")
WHERE NOT EXISTS (
    SELECT 1 FROM t_permissions p
    WHERE p."idMod" = new_perms."idMod" AND p."nomPerm" = new_perms."nomPerm"
);

-- Grant every permission belonging to these 14 modules to role 1
-- (Administrateur) -- covers both the rows just inserted above and any
-- that already existed validly (e.g. on environments where some of these
-- were never orphaned in the first place).
INSERT INTO t_user_permissions ("idRole", "idPerm")
SELECT 1, p."idPerm"
FROM t_permissions p
WHERE p."idMod" IN (1, 2, 3, 4, 8, 9, 11, 20, 26, 24, 29, 28, 25, 59)
  AND NOT EXISTS (
      SELECT 1 FROM t_user_permissions up
      WHERE up."idRole" = 1 AND up."idPerm" = p."idPerm"
  );
