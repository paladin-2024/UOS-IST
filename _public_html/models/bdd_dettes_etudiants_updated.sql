-- ========================================
-- SYSTÈME DE GESTION DES DETTES ÉTUDIANTS (MISE À JOUR)
-- ========================================

-- Modification de la procédure pour enregistrer les dettes
-- Un ECUE n'est une dette que si l'UE à laquelle il appartient a une moyenne < 10
DROP PROCEDURE IF EXISTS `enregistrer_dettes_passage`;

DELIMITER $$
CREATE PROCEDURE `enregistrer_dettes_passage`(
    IN p_matricule VARCHAR(255),
    IN p_promotion_id INT,
    IN p_session_id INT,
    IN p_annee_id INT,
    IN p_user_id INT
)
BEGIN
    -- Enregistrer seulement les ECUE non validées dont l'UE a une moyenne < 10
    INSERT INTO dette_etudiant (
        matricule, ECUE_idECUE, UE_idUE, semestre_idsemestre, 
        promotion_idpromotion, session_idsession, annee_acad_idannee_acad,
        note_obtenue, credits_ecue, statut, idUser
    )
    SELECT DISTINCT
        cg.matricule,
        cg.ECUE_idECUE,
        ecue.UE_idUE,
        ue.semestre_idsemestre,
        p_promotion_id,
        p_session_id,
        p_annee_id,
        cg.MF,
        ROUND((ecue.CMI + ecue.TD + ecue.TP) / 25, 2) as credits,
        'En cours',
        p_user_id
    FROM cotes_grille cg
    JOIN ecue ON cg.ECUE_idECUE = ecue.idECUE
    JOIN ue ON ecue.UE_idUE = ue.idUE
    JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
    WHERE cg.matricule = p_matricule
    AND s.promotion_idpromotion = p_promotion_id
    AND cg.session_idsession = p_session_id
    AND cg.annee_acad_id = p_annee_id
    AND cg.MF < 10  -- L'ECUE est en échec
    -- Vérifier que l'UE a une moyenne < 10
    AND EXISTS (
        SELECT 1
        FROM (
            SELECT 
                ue2.idUE,
                SUM(cg2.MF * ROUND((e2.CMI + e2.TD + e2.TP) / 25, 2)) / 
                SUM(ROUND((e2.CMI + e2.TD + e2.TP) / 25, 2)) AS moyenne_ue
            FROM cotes_grille cg2
            JOIN ecue e2 ON cg2.ECUE_idECUE = e2.idECUE
            JOIN ue ue2 ON e2.UE_idUE = ue2.idUE
            WHERE cg2.matricule = p_matricule
            AND cg2.session_idsession = p_session_id
            AND cg2.annee_acad_id = p_annee_id
            AND ue2.idUE = ecue.UE_idUE
            GROUP BY ue2.idUE
            HAVING moyenne_ue < 10
        ) AS ue_echec
    )
    -- Éviter les doublons
    AND NOT EXISTS (
        SELECT 1 FROM dette_etudiant de 
        WHERE de.matricule = cg.matricule 
        AND de.ECUE_idECUE = cg.ECUE_idECUE
        AND de.statut = 'En cours'
    );
END$$
DELIMITER ;

-- Nouvelle fonction pour vérifier si un ECUE est une dette
DELIMITER $$
CREATE FUNCTION `est_dette_ecue`(
    p_matricule VARCHAR(255),
    p_ecue_id INT,
    p_session_id INT,
    p_annee_id INT
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_moyenne_ue DECIMAL(5,2);
    DECLARE v_note_ecue DECIMAL(5,2);
    DECLARE v_ue_id INT;
    
    -- Récupérer l'UE de l'ECUE
    SELECT UE_idUE INTO v_ue_id
    FROM ecue
    WHERE idECUE = p_ecue_id;
    
    -- Récupérer la note de l'ECUE
    SELECT MF INTO v_note_ecue
    FROM cotes_grille
    WHERE matricule = p_matricule
    AND ECUE_idECUE = p_ecue_id
    AND session_idsession = p_session_id
    AND annee_acad_id = p_annee_id;
    
    -- Si l'ECUE n'est pas en échec, ce n'est pas une dette
    IF v_note_ecue >= 10 THEN
        RETURN FALSE;
    END IF;
    
    -- Calculer la moyenne de l'UE
    SELECT 
        SUM(cg.MF * ROUND((e.CMI + e.TD + e.TP) / 25, 2)) / 
        SUM(ROUND((e.CMI + e.TD + e.TP) / 25, 2))
    INTO v_moyenne_ue
    FROM cotes_grille cg
    JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
    WHERE cg.matricule = p_matricule
    AND e.UE_idUE = v_ue_id
    AND cg.session_idsession = p_session_id
    AND cg.annee_acad_id = p_annee_id;
    
    -- L'ECUE est une dette seulement si l'UE a une moyenne < 10
    RETURN v_moyenne_ue < 10;
END$$
DELIMITER ;

-- Vue améliorée pour afficher les dettes avec la moyenne de l'UE
CREATE OR REPLACE VIEW `v_dettes_etudiants_avec_moyenne_ue` AS
SELECT 
    d.id_dette,
    d.matricule,
    e.noms AS nom_etudiant,
    ecue.designationECUE,
    ue.designationUE,
    s.numeroSemestre,
    p.designationPromotion,
    p.cycle,
    p.est_terminale,
    sess.designSession,
    aa.designation AS annee_academique,
    d.note_obtenue,
    d.credits_ecue,
    d.statut,
    d.date_creation,
    d.note_rachat,
    d.date_validation,
    -- Calculer la moyenne de l'UE
    (SELECT 
        SUM(cg2.MF * ROUND((e2.CMI + e2.TD + e2.TP) / 25, 2)) / 
        SUM(ROUND((e2.CMI + e2.TD + e2.TP) / 25, 2))
     FROM cotes_grille cg2
     JOIN ecue e2 ON cg2.ECUE_idECUE = e2.idECUE
     WHERE cg2.matricule = d.matricule
     AND e2.UE_idUE = d.UE_idUE
     AND cg2.session_idsession = d.session_idsession
     AND cg2.annee_acad_id = d.annee_acad_idannee_acad
    ) AS moyenne_ue,
    CASE 
        WHEN d.note_rachat IS NOT NULL AND d.note_rachat >= 10 THEN 'Validée'
        WHEN d.note_rachat IS NOT NULL AND d.note_rachat < 10 THEN 'Non validée'
        ELSE 'En attente'
    END AS etat_rachat
FROM dette_etudiant d
JOIN etudiant e ON d.matricule = e.matricule
JOIN ecue ON d.ECUE_idECUE = ecue.idECUE
JOIN ue ON d.UE_idUE = ue.idUE
JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
JOIN promotion p ON d.promotion_idpromotion = p.idpromotion
JOIN session sess ON d.session_idsession = sess.idsession
JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad;

-- Procédure pour nettoyer les dettes invalides (UE validées)
DELIMITER $$
CREATE PROCEDURE `nettoyer_dettes_invalides`()
BEGIN
    -- Supprimer ou marquer comme annulées les dettes dont l'UE est maintenant validée
    UPDATE dette_etudiant d
    SET d.statut = 'Annulée'
    WHERE d.statut = 'En cours'
    AND EXISTS (
        SELECT 1
        FROM (
            SELECT 
                cg.matricule,
                e.UE_idUE,
                cg.session_idsession,
                cg.annee_acad_id,
                SUM(cg.MF * ROUND((e.CMI + e.TD + e.TP) / 25, 2)) / 
                SUM(ROUND((e.CMI + e.TD + e.TP) / 25, 2)) AS moyenne_ue
            FROM cotes_grille cg
            JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
            GROUP BY cg.matricule, e.UE_idUE, cg.session_idsession, cg.annee_acad_id
            HAVING moyenne_ue >= 10
        ) AS ue_validees
        WHERE ue_validees.matricule = d.matricule
        AND ue_validees.UE_idUE = d.UE_idUE
        AND ue_validees.session_idsession = d.session_idsession
        AND ue_validees.annee_acad_id = d.annee_acad_idannee_acad
    );
END$$
DELIMITER ;