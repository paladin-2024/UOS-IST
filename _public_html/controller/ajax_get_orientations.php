<?php
// Return orientations for a given academic year (annee_id)
// JSON response: { success: true, orientations: [ { idorientation, designationOrientation } ] }

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

$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if ($anneeId <= 0) {
    echo json_encode(['success' => true, 'orientations' => []]);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();

    // Fetch orientations tied to the selected academic year through sections
    $sql = "SELECT o.idorientation, o.\"designationOrientation\"
            FROM orientation o
            INNER JOIN section s ON o.section_idsection = s.idsection
            WHERE s.\"idAnnee\" = :anneeId
            ORDER BY o.\"designationOrientation\" ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();

    $orientations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orientations' => $orientations
    ]);
} catch (Exception $e) {
    error_log('Erreur ajax_get_orientations: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>

