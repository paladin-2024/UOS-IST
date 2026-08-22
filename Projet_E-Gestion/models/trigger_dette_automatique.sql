-- Trigger pour créer automatiquement des dettes après délibération
DELIMITER $$

CREATE TRIGGER `after_deliberation_create_dette` 
AFTER INSERT ON `resultat_deliberation` 
FOR EACH ROW
BEGIN
    -- Si l'étudiant est ajourné ou admis sous condition, créer les dettes
    IF NEW.decision IN ('Ajourné', 'Admis sous condition') THEN
        
        -- Insérer les dettes pour tous les ECUE échoués
        INSERT INTO dette_etudiant (
            matricule, 
            ECUE_idECUE, 
            UE_idUE, 
            semestre_idsemestre,
            promotion_idpromotion, 
            session_idsession, 
            annee_acad_idannee_acad,
            note_obtenue, 
            credits_ecue, 
            statut, 
            date_creation,
            idUser
        )
        SELECT 
            cg.matricule,
            cg.ECUE_idECUE,
            ue.idUE,
            ue.semestre_idsemestre,
            NEW.idpromotion,
            cg.session_idsession,
            cg.annee_acad_id,
            cg.MF,
            (ec.CMI + ec.TD + ec.TP) as credits,
            'En cours',
            NOW(),
            NEW.idUser
        FROM cotes_grille cg
        INNER JOIN ecue ec ON cg.ECUE_idECUE = ec.idECUE
        INNER JOIN ue ON ec.UE_idUE = ue.idUE
        WHERE cg.matricule = NEW.matricule
        AND cg.annee_acad_id = NEW.annee_acad_id
        AND cg.MF < 10  -- Note inférieure à 10
        AND NOT EXISTS (
            -- Vérifier qu'une dette n'existe pas déjà
            SELECT 1 FROM dette_etudiant de
            WHERE de.matricule = cg.matricule
            AND de.ECUE_idECUE = cg.ECUE_idECUE
            AND de.annee_acad_idannee_acad = cg.annee_acad_id
        );
        
    END IF;
END$$

DELIMITER ;

-- Procédure pour identifier et enregistrer les dettes après délibération
DELIMITER $$

CREATE PROCEDURE `identifier_et_enregistrer_dettes`(
    IN p_promotion_id INT,
    IN p_session_id INT,
    IN p_annee_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_finished INT DEFAULT 0;
    DECLARE v_matricule VARCHAR(255);
    DECLARE v_ecue_id INT;
    DECLARE v_ue_id INT;
    DECLARE v_semestre_id INT;
    DECLARE v_note DECIMAL(5,2);
    DECLARE v_credits INT;
    
    -- Curseur pour parcourir les échecs
    DECLARE cur_echecs CURSOR FOR
        SELECT DISTINCT
            cg.matricule,
            cg.ECUE_idECUE,
            ec.UE_idUE,
            ue.semestre_idsemestre,
            cg.MF as note,
            (ec.CMI + ec.TD + ec.TP) as credits
        FROM cotes_grille cg
        INNER JOIN ecue ec ON cg.ECUE_idECUE = ec.idECUE
        INNER JOIN ue ON ec.UE_idUE = ue.idUE
        INNER JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
        INNER JOIN etudiant e ON cg.matricule = e.matricule
        WHERE e.promotion_idpromotion = p_promotion_id
        AND cg.session_idsession = p_session_id
        AND cg.annee_acad_id = p_annee_id
        AND cg.MF < 10  -- Note d'échec
        AND NOT EXISTS (
            -- Pas déjà en dette
            SELECT 1 FROM dette_etudiant de
            WHERE de.matricule = cg.matricule
            AND de.ECUE_idECUE = cg.ECUE_idECUE
            AND de.annee_acad_idannee_acad = p_annee_id
        );
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_finished = 1;
    
    OPEN cur_echecs;
    
    read_loop: LOOP
        FETCH cur_echecs INTO v_matricule, v_ecue_id, v_ue_id, v_semestre_id, v_note, v_credits;
        
        IF v_finished = 1 THEN
            LEAVE read_loop;
        END IF;
        
        -- Insérer la dette
        INSERT INTO dette_etudiant (
            matricule, ECUE_idECUE, UE_idUE, semestre_idsemestre,
            promotion_idpromotion, session_idsession, annee_acad_idannee_acad,
            note_obtenue, credits_ecue, statut, date_creation, idUser
        ) VALUES (
            v_matricule, v_ecue_id, v_ue_id, v_semestre_id,
            p_promotion_id, p_session_id, p_annee_id,
            v_note, v_credits, 'En cours', NOW(), p_user_id
        );
        
        -- Ajouter à l'historique
        INSERT INTO dette_historique (
            id_dette, action, details, date_action, idUser
        ) VALUES (
            LAST_INSERT_ID(), 
            'Creation', 
            CONCAT('Dette créée automatiquement après délibération - Note obtenue: ', v_note),
            NOW(), 
            p_user_id
        );
        
    END LOOP;
    
    CLOSE cur_echecs;
    
    -- Retourner le nombre de dettes créées
    SELECT ROW_COUNT() as dettes_creees;
    
END$$

DELIMITER ;