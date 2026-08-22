-- Script pour copier les unités de recherche, spécialisations et enseignants affectés
-- Ce script peut être exécuté indépendamment du script de création d'une nouvelle année académique

-- Définir les variables
SET @annee_source_id = 1; -- ID de l'année académique source
SET @nouvelle_annee_id = 2; -- ID de la nouvelle année académique cible

-- Créer une table temporaire pour mapper les anciennes et nouvelles sections
CREATE TEMPORARY TABLE section_mapping (
    old_id INT,
    new_id INT
);

-- Remplir la table de mapping des sections
INSERT INTO section_mapping (old_id, new_id)
SELECT s_old.idsection, s_new.idsection
FROM section s_old
JOIN section s_new ON s_new.designationSection = s_old.designationSection 
                   AND s_new.idAnnee = @nouvelle_annee_id
WHERE s_old.idAnnee = @annee_source_id;

-- Créer un mapping pour les unités de recherche
CREATE TEMPORARY TABLE unite_recherche_mapping (
    old_id INT,
    new_id INT
);

-- Copier les unités de recherche
INSERT INTO unite_recherche (designation_UR, description, idUser, dateCreation)
SELECT designation_UR, description, idUser, NOW()
FROM unite_recherche;

-- Remplir la table de mapping des unités de recherche
INSERT INTO unite_recherche_mapping (old_id, new_id)
SELECT ur_old.idunite_recherche, ur_new.idunite_recherche
FROM unite_recherche ur_old
JOIN unite_recherche ur_new ON ur_new.designation_UR = ur_old.designation_UR
WHERE ur_new.dateCreation > ur_old.dateCreation;

-- Copier les relations entre unités de recherche et sections
INSERT INTO unite_recherche_section (idunite_recherche, idsection)
SELECT urm.new_id, sm.new_id
FROM unite_recherche_section urs
JOIN unite_recherche_mapping urm ON urs.idunite_recherche = urm.old_id
JOIN section_mapping sm ON urs.idsection = sm.old_id;

-- Copier les spécialisations
INSERT INTO specialisation (designation, dateCreation, idUnite_recherche, idsection)
SELECT s.designation, NOW(), urm.new_id, sm.new_id
FROM specialisation s
JOIN unite_recherche_mapping urm ON s.idUnite_recherche = urm.old_id
JOIN section_mapping sm ON s.idsection = sm.old_id;

-- Créer un mapping pour les spécialisations
CREATE TEMPORARY TABLE specialisation_mapping (
    old_id INT,
    new_id INT
);

-- Remplir la table de mapping des spécialisations
INSERT INTO specialisation_mapping (old_id, new_id)
SELECT s_old.idSpecialisation, s_new.idSpecialisation
FROM specialisation s_old
JOIN unite_recherche_mapping urm ON s_old.idUnite_recherche = urm.old_id
JOIN section_mapping sm ON s_old.idsection = sm.old_id
JOIN specialisation s_new ON s_new.designation = s_old.designation
                          AND s_new.idUnite_recherche = urm.new_id
                          AND s_new.idsection = sm.new_id;

-- Copier les affectations des enseignants aux spécialisations
INSERT INTO enseignant_specialisation (idAgent, idSpecialisation, dateAffectation, idUser)
SELECT es.idAgent, sm.new_id, NOW(), es.idUser
FROM enseignant_specialisation es
JOIN specialisation_mapping sm ON es.idSpecialisation = sm.old_id;

-- Afficher un message de confirmation
SELECT 'Copie des unités de recherche, spécialisations et affectations d\'enseignants terminée avec succès.' AS message;

-- Supprimer les tables temporaires
DROP TEMPORARY TABLE IF EXISTS section_mapping;
DROP TEMPORARY TABLE IF EXISTS unite_recherche_mapping;
DROP TEMPORARY TABLE IF EXISTS specialisation_mapping;
