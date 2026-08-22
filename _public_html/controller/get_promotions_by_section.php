<?php
session_start();
include_once "../config/Connexion.php";

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_GET['section_id']) || !isset($_GET['annee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$sectionId = (int)$_GET['section_id'];
$anneeId = (int)$_GET['annee_id'];
$connexion = Connexion::getInstance()->getPDO();

// Vérifier les droits d'accès
$currentUserId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1;

try {
    // Récupérer les promotions de cette section pour cette année académique
    $query = "SELECT DISTINCT p.idpromotion, p.\"designationPromotion\", p.cycle
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE o.section_idsection = :section_id
              AND (:annee_id = 0 OR p.annee_acad_idannee_acad = :annee_id)
              ORDER BY p.\"designationPromotion\"";

    $stmt = $connexion->prepare($query);
    $stmt->execute([
        ':section_id' => $sectionId,
        ':annee_id' => $anneeId
    ]);
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'promotions' => $promotions]);

} catch (Exception $e) {
    error_log("Erreur get_promotions_by_section: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
