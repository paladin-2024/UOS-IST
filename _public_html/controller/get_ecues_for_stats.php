<?php
// Contrôleur AJAX pour récupérer les ECUEs filtrés par sections responsables
session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';
require_once dirname(dirname(__FILE__)) . '/config/chargement.php';
charger();

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if ($anneeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Année académique non spécifiée']);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $userId = intval($_SESSION['id']);
    $role = intval($_SESSION['idRole']);

    // Requête de base pour récupérer les ECUEs avec leurs informations
    $sql = "SELECT DISTINCT e.idECUE, e.designationECUE, 
                   p.designationPromotion, s.numeroSemestre, 
                   sec.designationSection
            FROM ecue e
            INNER JOIN ue u ON e.UE_idUE = u.idUE
            INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
            INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
            INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
            INNER JOIN section sec ON o.section_idsection = sec.idsection
            WHERE p.annee_acad_idannee_acad = :anneeId";

    $params = [':anneeId' => $anneeId];

    // Si l'utilisateur n'est pas admin, filtrer par ses sections responsables
    if ($role != 1) {
        $querySections = "SELECT section_idsection 
                          FROM responsable_section 
                          WHERE idUser = :userId 
                          AND annee_acad_idannee_acad = :anneeId";
        $stmtSections = $pdo->prepare($querySections);
        $stmtSections->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmtSections->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmtSections->execute();
        $sections = $stmtSections->fetchAll(PDO::FETCH_COLUMN);

        if (empty($sections)) {
            echo json_encode(['success' => true, 'ecues' => []]);
            exit;
        }

        // Ajouter le filtre par sections
        $placeholders = [];
        foreach ($sections as $i => $sectionId) {
            $key = ':section' . $i;
            $placeholders[] = $key;
            $params[$key] = $sectionId;
        }
        $sql .= " AND sec.idsection IN (" . implode(', ', $placeholders) . ")";
    }

    $sql .= " ORDER BY sec.designationSection, p.designationPromotion, s.numeroSemestre, e.designationECUE";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'ecues' => $ecues]);

} catch (Exception $e) {
    error_log("Erreur dans get_ecues_for_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des ECUEs']);
}
?>
