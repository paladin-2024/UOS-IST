-- Follow-up to 2026-08-27-restore-admin-permissions.sql: that migration
-- deliberately linked only a curated subset of pages per module. Per an
-- explicit request to check every action wasn't a dead end, this migration
-- adds more real, working admin actions per module that were reachable by
-- URL but not yet in the sidebar (e.g. "add a new student" as a complement
-- to "re-enroll a student"), after fixing several more MySQL->Postgres bugs
-- found along the way.
--
-- Idempotent: guarded by NOT EXISTS on (idMod, nomPerm), safe to re-run.

INSERT INTO t_permissions ("idMod", "codePerm", "nomPerm", "descPerm")
SELECT * FROM (VALUES
    (3, 'ajouter', 'etudiant.inscrit', 'Inscription des nouveaux étudiants'),
    (3, 'consulter', 'documents_etudiants', 'Documents des étudiants'),
    (2, 'consulter', 'faculte', 'Sections / Facultés'),
    (2, 'consulter', 'promotion', 'Promotions'),
    (2, 'consulter', 'semestre', 'Semestres'),
    (2, 'consulter', 'orientation', 'Orientations'),
    (4, 'modifier', 'agent.edit', 'Modifier un agent'),
    (8, 'ajouter', 'courriel.add', 'Ajouter un courrier'),
    (9, 'consulter', 'document.list', 'Documents du projet'),
    (11, 'consulter', 'echeanciers', 'Échéanciers de paiement'),
    (25, 'ajouter', 'depot.travail', 'Dépôt de travaux'),
    (25, 'consulter', 'suivi_depot', 'Suivi des dépôts'),
    (26, 'consulter', 'affectation', 'Affectation des directeurs'),
    (26, 'consulter', 'depot_soutenance', 'Dépôts de soutenance'),
    (28, 'consulter', 'evaluations', 'Évaluations')
) AS new_perms("idMod", "codePerm", "nomPerm", "descPerm")
WHERE NOT EXISTS (
    SELECT 1 FROM t_permissions p
    WHERE p."idMod" = new_perms."idMod" AND p."nomPerm" = new_perms."nomPerm"
);

INSERT INTO t_user_permissions ("idRole", "idPerm")
SELECT 1, p."idPerm"
FROM t_permissions p
WHERE p."idMod" IN (2, 3, 4, 8, 9, 11, 25, 26, 28)
  AND p."nomPerm" IN (
      'etudiant.inscrit', 'documents_etudiants', 'faculte', 'promotion',
      'semestre', 'orientation', 'agent.edit', 'courriel.add',
      'document.list', 'echeanciers', 'depot.travail', 'suivi_depot',
      'affectation', 'depot_soutenance', 'evaluations'
  )
  AND NOT EXISTS (
      SELECT 1 FROM t_user_permissions up
      WHERE up."idRole" = 1 AND up."idPerm" = p."idPerm"
  );
