<?php
header('Content-Type: application/json');
include_once '../config/Connexion.php';

try {
    if (!isset($_GET['promotion_id']) || empty($_GET['promotion_id'])) {
        throw new Exception('ID de promotion manquant');
    }

    $promotionId = intval($_GET['promotion_id']);
    $db = Connexion::getInstance()->getPDO();

    // Récupérer l'année académique active
    $anneeAcadQuery = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmtAnneeAcad = $db->prepare($anneeAcadQuery);
    $stmtAnneeAcad->execute();
    $anneeAcad = $stmtAnneeAcad->fetch(PDO::FETCH_ASSOC);

    if (!$anneeAcad) {
        throw new Exception('Aucune année académique active trouvée');
    }

    // Requête pour récupérer les ECUE avec leurs heures totales et réalisées
    $query = "SELECT 
                e.\"idECUE\",
                e.\"designationECUE\",
                ue.\"designationUE\",
                s.\"numeroSemestre\",
                (IFNULL(e.CMI, 0) + IFNULL(e.TD, 0) + IFNULL(e.TP, 0)) as heures_total,
                IFNULL(SUM(see.nombre_heures_reelles), 0) as heures_realisees
              FROM ecue e
              JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
              JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
              LEFT JOIN suivi_enseignement_ecue see ON e.\"idECUE\" = see.\"idECUE\" 
                  AND see.promotion_idpromotion = :promotionId 
                  AND see.annee_acad_idannee_acad = :anneeAcadId
                  AND see.statut_validation IN ('Validé', 'En attente')
              WHERE e.\"estVisible\" = 1 
                AND s.promotion_idpromotion = :promotionId
                AND (e.CMI + e.TD + e.TP) > 0
              GROUP BY e.\"idECUE\", e.\"designationECUE\", ue.\"designationUE\", s.\"numeroSemestre\"
              HAVING heures_realisees < heures_total
              ORDER BY s.\"numeroSemestre\", e.\"designationECUE\"";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcad['idannee_acad'], PDO::PARAM_INT);
    $stmt->execute();
    
    $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'ecues' => $ecues,
        'message' => count($ecues) . ' ECUE(s) disponible(s) trouvé(s)'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'ecues' => []
    ]);
}
?>