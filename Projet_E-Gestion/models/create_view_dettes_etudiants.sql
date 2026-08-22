-- Vue pour faciliter l'accès aux informations des dettes étudiants
CREATE OR REPLACE VIEW `v_dettes_etudiants` AS
SELECT 
    d.id_dette,
    d.matricule,
    e.noms as nom_etudiant,
    e.adressemail,
    d.ECUE_idECUE,
    ec.designationECUE,
    d.UE_idUE,
    ue.designationUE,
    d.semestre_idsemestre,
    s.numeroSemestre,
    d.promotion_idpromotion,
    p.designationPromotion,
    d.session_idsession,
    sess.designSession,
    d.annee_acad_idannee_acad,
    aa.designation as annee_academique,
    d.note_obtenue,
    d.credits_ecue,  -- Utilisation directe du champ credits_ecue de la table dette_etudiant
    d.statut,
    d.date_creation,
    d.date_validation,
    d.note_rachat,
    d.session_rachat,
    d.annee_rachat,
    d.idUser
FROM dette_etudiant d
JOIN etudiant e ON d.matricule = e.matricule
JOIN ecue ec ON d.ECUE_idECUE = ec.idECUE
JOIN ue ue ON d.UE_idUE = ue.idUE
JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
JOIN promotion p ON d.promotion_idpromotion = p.idpromotion
JOIN session sess ON d.session_idsession = sess.idsession
JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad;