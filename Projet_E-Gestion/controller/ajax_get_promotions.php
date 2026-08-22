<?php
// Désactiver l'affichage des erreurs pour éviter qu'elles interfèrent avec le JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé', 'message' => 'Session non trouvée']);
    exit;
}

$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if ($anneeId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer les promotions de l'année académique
    $query = "SELECT p.idpromotion, p.designationPromotion, p.cycle,
                     o.designationOrientation, a.designation as anneeDesignation, p.est_terminale
              FROM promotion p
              INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
              INNER JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
              WHERE p.annee_acad_idannee_acad = :annee_id
              ORDER BY p.designationPromotion ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':annee_id', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'promotions' => $promotions
    ]);
    
} catch (Exception $e) {
    error_log("Erreur dans ajax_get_promotions.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>
