-- Script pour mettre à jour les crédits dans la table dette_etudiant
-- Ce script s'assure que le champ credits_ecue contient les bonnes valeurs

-- D'abord, vérifier s'il y a des enregistrements avec credits_ecue = 0 ou NULL
SELECT 
    COUNT(*) as total_dettes,
    SUM(CASE WHEN credits_ecue IS NULL OR credits_ecue = 0 THEN 1 ELSE 0 END) as dettes_sans_credits
FROM dette_etudiant;

-- Mettre à jour les crédits en utilisant les valeurs de la table ECUE
UPDATE dette_etudiant d
JOIN ecue ec ON d.ECUE_idECUE = ec.idECUE
SET d.credits_ecue = ROUND((ec.CMI + ec.TD + ec.TP) / 15, 2)
WHERE d.credits_ecue IS NULL OR d.credits_ecue = 0;

-- Vérifier le résultat
SELECT 
    d.id_dette,
    d.matricule,
    ec.designationECUE,
    d.credits_ecue,
    ROUND((ec.CMI + ec.TD + ec.TP) / 15, 2) as credits_calcules,
    d.statut
FROM dette_etudiant d
JOIN ecue ec ON d.ECUE_idECUE = ec.idECUE
LIMIT 20;