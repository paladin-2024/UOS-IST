-- Removes the "ECUE" sidebar entry (t_permissions idMod=28, nomPerm='ecues')
-- added by 2026-08-27-restore-admin-permissions.sql on the assumption that
-- "the underlying pages already work fine when visited directly by URL".
-- That assumption was wrong for this one: views/enseignement/ecues.php has
-- always required a specific ?ue=<id> (it shows ECUEs *for one UE*), which
-- a flat sidebar link can never supply. Admins (role 1) clicking "ECUE" in
-- the sidebar landed on an immediate "Unité d'enseignement non spécifiée"
-- error instead of a working page (reported 2026-10-01).
--
-- The real feature (viewing/managing ECUEs for a given UE) is unaffected --
-- it's reached correctly via "Unités d'Enseignement" -> pick a UE -> ECUEs,
-- which already passes ?ue= and keeps working. This only removes the
-- unusable direct link; views/enseignement/ecues.php stays in
-- config/allowed_views.php and now redirects gracefully (no alarming
-- error) to the UE list if it's ever reached without ?ue= again.
--
-- Idempotent: DELETEs are no-ops if already applied.

DELETE FROM t_user_permissions
WHERE "idPerm" IN (SELECT "idPerm" FROM t_permissions WHERE "idMod" = 28 AND "nomPerm" = 'ecues');

DELETE FROM t_permissions
WHERE "idMod" = 28 AND "nomPerm" = 'ecues';
