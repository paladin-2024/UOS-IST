<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non authentifié']);
    exit();
}

// Récupérer l'ID de l'affectation
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID d\'affectation invalide']);
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails de l'affectation
    $stmt = $connexion->prepare("
        SELECT a.*, 
               f.designation AS frais_designation, 
               f.montant AS frais_montant,
               f.devise,
               f.est_echelonnable,
               cf.designation AS categorie_nom,
               aa.designation AS annee_academique,
               p.designationPromotion AS promotion_nom,
               CONCAT(s.designationSection, ' - ', o.designationOrientation) AS faculte_nom,
               e.noms AS etudiant_nom,
               e.promotion_idpromotion,
               ep.designationPromotion AS etudiant_promotion
        FROM affectation_frais a
        INNER JOIN frais f ON a.frais_id = f.id
        LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
        LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        LEFT JOIN promotion p ON a.promotion_id = p.idpromotion
        LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                LEFT JOIN section s ON o.section_idsection = s.idsection
        LEFT JOIN etudiant e ON a.matricule_etudiant = e.matricule
        LEFT JOIN promotion ep ON e.promotion_idpromotion = ep.idpromotion
        WHERE a.id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$affectation) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Affectation non trouvée']);
        exit();
    }
    
    // Préparer les données de l'étudiant si applicable
    if ($affectation['matricule_etudiant']) {
        $affectation['etudiant_nom_complet'] = $affectation['etudiant_nom'] . ' ' . $affectation['etudiant_postnom'] . ' ' . $affectation['etudiant_prenom'];
    }
    
    // Récupérer les paiements associés
    $stmt = $connexion->prepare("
    SELECT pf.*, u.nomUser AS agent_nom
    FROM paiements_frais pf
    LEFT JOIN t_users u ON pf.idConfirmateur = u.idUser
    WHERE pf.affectation_id = :affectation_id
    ORDER BY pf.date_confirmation DESC
    ");

    $stmt->bindParam(':affectation_id', $id);
    $stmt->execute();
    
    $affectation['paiements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si le frais est échelonnable, récupérer les tranches
    if ($affectation['est_echelonnable'] === '1') {
        $stmt = $connexion->prepare("
            SELECT t.*, 
       COALESCE(SUM(pf.montant), 0) AS montant_paye,
       CASE 
         WHEN COALESCE(SUM(pf.montant), 0) = 0 THEN 'Non payé'
         WHEN COALESCE(SUM(pf.montant), 0) < t.montant_fixe THEN 'Partiel'
         ELSE 'Complet'
       END AS statut_paiement
FROM tranches_paiement_config t
LEFT JOIN echelonnement_paiement e ON t.frais_id = e.affectation_id
LEFT JOIN paiements_frais pf ON e.id = pf.echelonnement_id
WHERE t.frais_id = :frais_id
GROUP BY t.id
ORDER BY t.numero_tranche

        ");
        $stmt->bindParam(':frais_id', $affectation['frais_id']);
        $stmt->execute();
        
        $affectation['tranches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($affectation);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit();
}
