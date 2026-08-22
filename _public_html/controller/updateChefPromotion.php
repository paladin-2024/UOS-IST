<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
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
    $chefId = filter_input(INPUT_POST, 'chef_id', FILTER_VALIDATE_INT);
    $promotionId = filter_input(INPUT_POST, 'promotion_id', FILTER_VALIDATE_INT);
    $etudiantId = filter_input(INPUT_POST, 'etudiant_id', FILTER_VALIDATE_INT);
    $dateNomination = filter_input(INPUT_POST, 'date_nomination', FILTER_SANITIZE_STRING);
    $anneeAcadId = filter_input(INPUT_POST, 'annee_acad_id', FILTER_VALIDATE_INT);
    
    // Validation des champs requis
    if (!$chefId || !$promotionId || !$etudiantId || !$dateNomination || !$anneeAcadId) {
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
    
    // Vérifier que le chef existe et est actif
    $checkChefQuery = "SELECT idetudiant FROM chef_promotion 
                       WHERE id_chef = :chef_id AND est_actif = 1";
    $checkChefStmt = $db->prepare($checkChefQuery);
    $checkChefStmt->bindParam(':chef_id', $chefId, PDO::PARAM_INT);
    $checkChefStmt->execute();
    
    if ($checkChefStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Chef de promotion non trouvé']);
        exit;
    }
    
    $ancienEtudiantId = $checkChefStmt->fetch(PDO::FETCH_ASSOC)['idetudiant'];
    
    // Si l'étudiant change, vérifier qu'il n'est pas déjà chef ailleurs
    if ($etudiantId != $ancienEtudiantId) {
        $checkAutreChefQuery = "SELECT cp.id_chef, p.designationPromotion 
                               FROM chef_promotion cp
                               JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                               WHERE cp.idetudiant = :etudiant_id 
                               AND cp.annee_acad_idannee_acad = :annee_acad_id 
                               AND cp.est_actif = 1
                               AND cp.id_chef != :chef_id";
        $checkAutreChefStmt = $db->prepare($checkAutreChefQuery);
        $checkAutreChefStmt->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);
        $checkAutreChefStmt->bindParam(':annee_acad_id', $anneeAcadId, PDO::PARAM_INT);
        $checkAutreChefStmt->bindParam(':chef_id', $chefId, PDO::PARAM_INT);
        $checkAutreChefStmt->execute();
        
        if ($checkAutreChefStmt->rowCount() > 0) {
            $autrePromotion = $checkAutreChefStmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => false, 'message' => "Cet étudiant est déjà chef de la promotion : {$autrePromotion['designationPromotion']}"]);
            exit;
        }
        
        // Vérifier que le nouvel étudiant appartient à cette promotion
        $checkEtudiantQuery = "SELECT idetudiant FROM etudiant 
                              WHERE idetudiant = :etudiant_id 
                              AND promotion_idpromotion = :promotion_id 
                              AND annee_acad_idannee_acad = :annee_acad_id
                              AND est_actif = 1";
        $checkEtudiantStmt = $db->prepare($checkEtudiantQuery);
        $checkEtudiantStmt->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);
        $checkEtudiantStmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
        $checkEtudiantStmt->bindParam(':annee_acad_id', $anneeAcadId, PDO::PARAM_INT);
        $checkEtudiantStmt->execute();
        
        if ($checkEtudiantStmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Le nouvel étudiant n\'appartient pas à cette promotion']);
            exit;
        }
    }
    
    // Mettre à jour le chef de promotion
    $updateQuery = "UPDATE chef_promotion 
                    SET idetudiant = :etudiant_id, 
                        date_nomination = :date_nomination,
                        date_creation = CURRENT_TIMESTAMP
                    WHERE id_chef = :chef_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);
    $updateStmt->bindParam(':date_nomination', $dateNomination, PDO::PARAM_STR);
    $updateStmt->bindParam(':chef_id', $chefId, PDO::PARAM_INT);
    
    if ($updateStmt->execute()) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Chef de promotion modifié avec succès']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification du chef']);
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erreur updateChefPromotion: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système : ' . $e->getMessage()]);
}
?>