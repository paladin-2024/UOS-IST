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

    // Récupérer les chefs de promotion actifs
    $query = "SELECT DISTINCT e.idetudiant, e.noms, e.matricule
              FROM chef_promotion cp
              JOIN etudiant e ON cp.idetudiant = e.idetudiant
              WHERE cp.promotion_idpromotion = :promotionId 
                AND cp.annee_acad_idannee_acad = :anneeAcadId
                AND cp.est_actif = 1
                AND e.est_actif = 1
              ORDER BY e.noms";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcad['idannee_acad'], PDO::PARAM_INT);
    $stmt->execute();
    
    $chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun chef de promotion n'est trouvé, récupérer tous les étudiants de la promotion
    if (empty($chefs)) {
        $queryAlternative = "SELECT e.idetudiant, e.noms, e.matricule
                            FROM etudiant e
                            WHERE e.promotion_idpromotion = :promotionId 
                              AND e.annee_acad_idannee_acad = :anneeAcadId
                              AND e.est_actif = 1
                            ORDER BY e.noms";

        $stmtAlternative = $db->prepare($queryAlternative);
        $stmtAlternative->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmtAlternative->bindParam(':anneeAcadId', $anneeAcad['idannee_acad'], PDO::PARAM_INT);
        $stmtAlternative->execute();
        
        $chefs = $stmtAlternative->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'chefs' => $chefs,
        'message' => count($chefs) . ' chef(s) de promotion trouvé(s)'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'chefs' => []
    ]);
}
?>
