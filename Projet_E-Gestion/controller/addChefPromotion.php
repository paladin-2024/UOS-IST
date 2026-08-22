<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $userId = $_SESSION['id'];
    
    // Récupération et validation des données
    $promotionId = filter_input(INPUT_POST, 'promotion_id', FILTER_VALIDATE_INT);
    $etudiantId = filter_input(INPUT_POST, 'etudiant_id', FILTER_VALIDATE_INT);
    $dateNomination = filter_input(INPUT_POST, 'date_nomination', FILTER_SANITIZE_STRING);
    $anneeAcadId = filter_input(INPUT_POST, 'annee_acad_id', FILTER_VALIDATE_INT);
    
    // Validation des champs requis
    if (!$promotionId || !$etudiantId || !$dateNomination || !$anneeAcadId) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
        exit;
    }
    
    // Validation de la date
    $date = DateTime::createFromFormat('Y-m-d', $dateNomination);
    if (!$date || $date->format('Y-m-d') !== $dateNomination) {
        echo json_encode(['success' => false, 'message' => 'Format de date invalide']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Vérifier qu'il n'y a pas déjà un chef actif pour cette promotion/année
    $checkQuery = "SELECT id_chef FROM chef_promotion 
                   WHERE promotion_idpromotion = :promotion_id 
                   AND annee_acad_idannee_acad = :annee_acad_id 
                   ";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
    $checkStmt->bindParam(':annee_acad_id', $anneeAcadId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cette promotion a déjà un chef actif pour cette année académique']);
        exit;
    }
    
    /*
    // Vérifier que l'étudiant appartient bien à cette promotion
    $checkEtudiantQuery = "SELECT idetudiant FROM etudiant 
                          WHERE idetudiant = :etudiant_id 
                          AND promotion_idpromotion = :promotion_id 
                          AND annee_acad_idannee_acad = :annee_acad_id
                          ";
    $checkEtudiantStmt = $db->prepare($checkEtudiantQuery);
    $checkEtudiantStmt->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);
    $checkEtudiantStmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
    $checkEtudiantStmt->bindParam(':annee_acad_id', $anneeAcadId, PDO::PARAM_INT);
    $checkEtudiantStmt->execute();
    
    if ($checkEtudiantStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Cet étudiant n\'appartient pas à cette promotion']);
        exit;
    }
    */
    
    // Vérifier que l'étudiant n'est pas déjà chef d'une autre promotion
    $checkAutreChefQuery = "SELECT cp.id_chef, p.\"designationPromotion\" 
                           FROM chef_promotion cp
                           JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                           WHERE cp.idetudiant = :etudiant_id 
                           AND cp.annee_acad_idannee_acad = :annee_acad_id 
                           AND cp.est_actif = 1";
    $checkAutreChefStmt = $db->prepare($checkAutreChefQuery);
    $checkAutreChefStmt->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);
    $checkAutreChefStmt->bindParam(':annee_acad_id', $anneeAcadId, PDO::PARAM_INT);
    $checkAutreChefStmt->execute();
    
    if ($checkAutreChefStmt->rowCount() > 0) {
        $autrePromotion = $checkAutreChefStmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => false, 'message' => "Cet étudiant est déjà chef de la promotion : {$autrePromotion['designationPromotion']}"]);
        exit;
    }
    
    // Insérer le nouveau chef de promotion
    $insertQuery = "INSERT INTO chef_promotion 
                    (idetudiant, promotion_idpromotion, annee_acad_idannee_acad, date_nomination, est_actif, \"idUser\") 
                    VALUES (:etudiant_id, :promotion_id, :annee_acad_id, :date_nomination, 1, :user_id)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);
    $insertStmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
    $insertStmt->bindParam(':annee_acad_id', $anneeAcadId, PDO::PARAM_INT);
    $insertStmt->bindParam(':date_nomination', $dateNomination, PDO::PARAM_STR);
    $insertStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if ($insertStmt->execute()) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Chef de promotion affecté avec succès']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'affectation du chef']);
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erreur addChefPromotion: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système : ' . $e->getMessage()]);
}
?>