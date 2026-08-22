<?php
session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

// Vérification des droits d'accès (optionnel selon vos besoins)
// if (!isset($_SESSION['idRole'])) {
//     echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
//     exit();
// }

$promotionId = isset($_GET['promotion_id']) ? intval($_GET['promotion_id']) : 0;

if (!$promotionId) {
    echo json_encode(['success' => false, 'message' => 'Promotion non spécifiée']);
    exit();
}

$db = Connexion::getInstance()->getPDO();

try {
    // Vérifier si la colonne estVisible existe
    $checkColumn = "SHOW COLUMNS FROM ecue LIKE 'estVisible'";
    $stmtCheck = $db->prepare($checkColumn);
    $stmtCheck->execute();
    $columnExists = $stmtCheck->fetch();
    
    // Récupérer les ECUE de la promotion sélectionnée
    $sql = "SELECT DISTINCT e.idECUE, e.designationECUE 
            FROM ecue e
            JOIN ue u ON e.UE_idUE = u.idUE
            JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
            WHERE s.promotion_idpromotion = :promotionId";
    
    if ($columnExists) {
        $sql .= " AND e.estVisible = 1";
    }
    
    $sql .= " ORDER BY e.designationECUE";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':promotionId' => $promotionId]);
    $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log pour debug
    error_log("ajax_get_ecues.php - Promotion ID: " . $promotionId);
    error_log("ajax_get_ecues.php - Nombre d'ECUE trouvés: " . count($ecues));
    
    echo json_encode([
        'success' => true,
        'ecues' => $ecues
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur récupération ECUE: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des cours'
    ]);
}
?>