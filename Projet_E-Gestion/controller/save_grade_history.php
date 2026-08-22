<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données du formulaire
$idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
$gradeHistoryId = isset($_POST['grade_history_id']) ? intval($_POST['grade_history_id']) : 0;
$idgrade = isset($_POST['idgrade']) ? intval($_POST['idgrade']) : 0;
$datePromotion = isset($_POST['date_promotion']) ? trim($_POST['date_promotion']) : '';
$referenceDecision = isset($_POST['reference_decision']) ? trim($_POST['reference_decision']) : null;
$referenceNotification = isset($_POST['reference_notification']) ? trim($_POST['reference_notification']) : null;
$idUser = $_SESSION['id']; // ID de l'utilisateur connecté

// Validation des données
if (empty($idAgent)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de l\'agent non spécifié']);
    exit;
}

if (empty($idgrade) || empty($datePromotion)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires']);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();

    // Enregistrer ou mettre à jour l'historique de grade
    if ($gradeHistoryId > 0) {
        // Mise à jour d'un historique existant
        $query = "UPDATE historique_grade 
                  SET idgrade = :idgrade, 
                      date_promotion = :date_promotion, 
                      reference_decision = :reference_decision, 
                      reference_notification = :reference_notification,
                      idUser = :idUser
                  WHERE idhistorique_grade = :idhistorique_grade AND idAgent = :idAgent";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':idgrade', $idgrade, PDO::PARAM_INT);
        $stmt->bindParam(':date_promotion', $datePromotion, PDO::PARAM_STR);
        $stmt->bindParam(':reference_decision', $referenceDecision, PDO::PARAM_STR);
        $stmt->bindParam(':reference_notification', $referenceNotification, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':idhistorique_grade', $gradeHistoryId, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        
        $stmt->execute();
        $message = 'Historique de grade mis à jour avec succès';
    } else {
        // Ajout d'un nouvel historique
        $query = "INSERT INTO historique_grade 
                  (idAgent, idgrade, date_promotion, reference_decision, reference_notification, idUser) 
                  VALUES 
                  (:idAgent, :idgrade, :date_promotion, :reference_decision, :reference_notification, :idUser)";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':idgrade', $idgrade, PDO::PARAM_INT);
        $stmt->bindParam(':date_promotion', $datePromotion, PDO::PARAM_STR);
        $stmt->bindParam(':reference_decision', $referenceDecision, PDO::PARAM_STR);
        $stmt->bindParam(':reference_notification', $referenceNotification, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        
        $stmt->execute();
        $message = 'Historique de grade ajouté avec succès';
    }

    // Mettre à jour le grade actuel de l'agent si c'est la promotion la plus récente
    $queryLatest = "SELECT idhistorique_grade, idgrade FROM historique_grade 
                    WHERE idAgent = :idAgent 
                    ORDER BY date_promotion DESC 
                    LIMIT 1";
    $stmtLatest = $pdo->prepare($queryLatest);
    $stmtLatest->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtLatest->execute();
    $latestGrade = $stmtLatest->fetch(PDO::FETCH_ASSOC);
    
    if ($latestGrade && ($gradeHistoryId == 0 || $gradeHistoryId == $latestGrade['idhistorique_grade'])) {
        $queryUpdateAgent = "UPDATE agent SET grade_id = :grade_id WHERE idAgent = :idAgent";
        $stmtUpdateAgent = $pdo->prepare($queryUpdateAgent);
        $stmtUpdateAgent->bindParam(':grade_id', $latestGrade['idgrade'], PDO::PARAM_INT);
        $stmtUpdateAgent->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmtUpdateAgent->execute();
    }

    // Valider la transaction
    $pdo->commit();
    
    // Répondre avec le résultat
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    exit;
}
