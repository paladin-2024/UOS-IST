<?php
header('Content-Type: application/json');
require_once '../../config/Connexion.php';
require_once '../../models/Universite.php';

$connexion = Connexion::getInstance()->getPDO();

$promotion_id = isset($_GET['promotion_id']) ? intval($_GET['promotion_id']) : 0;
$annee_id = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if (!$promotion_id || !$annee_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres invalides'
    ]);
    exit;
}

try {
    // Get the source promotion
    $querySource = "SELECT p.*, o.designationOrientation 
                    FROM promotion p 
                    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                    WHERE p.idpromotion = :promotionId";
    $stmtSource = $connexion->prepare($querySource);
    $stmtSource->execute(['promotionId' => $promotion_id]);
    $sourcePromotion = $stmtSource->fetch(PDO::FETCH_ASSOC);

    if (!$sourcePromotion) {
        echo json_encode([
            'success' => false,
            'message' => 'Promotion source non trouvée'
        ]);
        exit;
    }

    // Get promotions that can be chosen (same academic year, different from source)
    $query = "SELECT p.idpromotion, p.designationPromotion, p.cycle, o.designationOrientation
              FROM promotion p
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE p.idpromotion != :promotionId
              AND p.annee_acad_idannee_acad = :anneeId
              ORDER BY p.designationPromotion";

    $stmt = $connexion->prepare($query);
    $stmt->execute([
        'promotionId' => $promotion_id,
        'anneeId' => $annee_id
    ]);
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'promotions' => $promotions
    ]);
} catch (Exception $e) {
    error_log("Erreur get_promotions_for_choice: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement des promotions: ' . $e->getMessage()
    ]);
}
