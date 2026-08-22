<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    $promotionId = filter_input(INPUT_GET, 'promotion_id', FILTER_VALIDATE_INT);
    
    if (!$promotionId) {
        echo json_encode(['success' => false, 'message' => 'ID de promotion invalide']);
        exit;
    }
    
    // Récupérer les étudiants de la promotion qui ne sont pas déjà chefs
    $query = "SELECT e.idetudiant, e.matricule, e.noms 
              FROM etudiant e
              WHERE e.promotion_idpromotion = :promotion_id 
              AND e.est_actif = 1
              AND e.idetudiant NOT IN (
                  SELECT cp.idetudiant 
                  FROM chef_promotion cp 
                  WHERE cp.est_actif = 1 
                  AND cp.annee_acad_idannee_acad = e.annee_acad_idannee_acad
              )
              ORDER BY e.noms";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'etudiants' => $etudiants
    ]);
    
} catch (Exception $e) {
    error_log("Erreur getEtudiantsPromotion: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système']);
}
?>