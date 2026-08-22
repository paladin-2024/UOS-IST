<?php
// Return promotions for a given orientation (and optional year)
// Params: orientation_id (required), annee_id (optional)
// JSON response: { success: true, promotions: [ { idpromotion, designationPromotion } ] }

error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['idRole'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$orientationId = isset($_GET['orientation_id']) ? intval($_GET['orientation_id']) : 0;
$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if ($orientationId <= 0) {
    echo json_encode(['success' => true, 'promotions' => []]);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();

    $sql = "SELECT p.idpromotion, p.designationPromotion
            FROM promotion p
            WHERE p.orientation_idorientation = :orientationId";

    if ($anneeId > 0) {
        $sql .= " AND p.annee_acad_idannee_acad = :anneeId";
    }

    $sql .= " ORDER BY p.designationPromotion ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
    if ($anneeId > 0) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'promotions' => $promotions
    ]);
} catch (Exception $e) {
    error_log('Erreur ajax_get_promotions_by_orientation: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>

