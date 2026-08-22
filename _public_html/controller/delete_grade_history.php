<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../index.php');
    exit;
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de l'historique de grade manquant.";
    header('Location: ../grh/agent.liste');
    exit;
}

$gradeHistoryId = intval($_GET['id']);
$agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
$returnTab = isset($_GET['returnTab']) ? $_GET['returnTab'] : 'grades';

if (empty($agentId)) {
    $_SESSION['error'] = "ID de l'agent manquant.";
    header('Location: ../grh/agent.liste');
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();
    
    // Récupérer les informations de l'agent pour la redirection
    $queryAgent = "SELECT codeAgent FROM agent WHERE idAgent = :idAgent";
    $stmtAgent = $pdo->prepare($queryAgent);
    $stmtAgent->bindParam(':idAgent', $agentId, PDO::PARAM_INT);
    $stmtAgent->execute();
    $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        throw new Exception("Agent non trouvé.");
    }
    
    // Vérifier si l'historique de grade existe et appartient à l'agent
    $queryCheck = "SELECT * FROM historique_grade WHERE idhistorique_grade = :id AND idAgent = :idAgent";
    $stmtCheck = $pdo->prepare($queryCheck);
    $stmtCheck->bindParam(':id', $gradeHistoryId, PDO::PARAM_INT);
    $stmtCheck->bindParam(':idAgent', $agentId, PDO::PARAM_INT);
    $stmtCheck->execute();
    
    $gradeHistory = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$gradeHistory) {
        throw new Exception("Historique de grade non trouvé ou n'appartient pas à cet agent.");
    }
    
    // Supprimer l'historique de grade
    $queryDelete = "DELETE FROM historique_grade WHERE idhistorique_grade = :id";
    $stmtDelete = $pdo->prepare($queryDelete);
    $stmtDelete->bindParam(':id', $gradeHistoryId, PDO::PARAM_INT);
    $stmtDelete->execute();
    
    // Vérifier si c'était le grade actuel de l'agent
    $wasCurrentGrade = ($gradeHistory['idgrade'] == $agent['grade_id']);
    
    // Si c'était le grade actuel, mettre à jour avec le grade le plus récent restant
    if ($wasCurrentGrade) {
        $queryLatest = "SELECT idgrade FROM historique_grade 
                        WHERE idAgent = :idAgent 
                        ORDER BY date_promotion DESC 
                        LIMIT 1";
        $stmtLatest = $pdo->prepare($queryLatest);
        $stmtLatest->bindParam(':idAgent', $agentId, PDO::PARAM_INT);
        $stmtLatest->execute();
        
        $latestGrade = $stmtLatest->fetch(PDO::FETCH_ASSOC);
        
        if ($latestGrade) {
            // Mettre à jour avec le grade le plus récent
            $queryUpdateAgent = "UPDATE agent SET grade_id = :grade_id WHERE idAgent = :idAgent";
            $stmtUpdateAgent = $pdo->prepare($queryUpdateAgent);
            $stmtUpdateAgent->bindParam(':grade_id', $latestGrade['idgrade'], PDO::PARAM_INT);
            $stmtUpdateAgent->bindParam(':idAgent', $agentId, PDO::PARAM_INT);
            $stmtUpdateAgent->execute();
        } else {
            // Aucun grade restant, mettre à NULL
            $queryUpdateAgent = "UPDATE agent SET grade_id = NULL WHERE idAgent = :idAgent";
            $stmtUpdateAgent = $pdo->prepare($queryUpdateAgent);
            $stmtUpdateAgent->bindParam(':idAgent', $agentId, PDO::PARAM_INT);
            $stmtUpdateAgent->execute();
        }
    }
    
    // Valider la transaction
    $pdo->commit();
    
    // Préparer le message de succès pour SweetAlert
    $_SESSION['swal_success'] = [
        'title' => 'Succès!',
        'text' => 'L\'historique de grade a été supprimé avec succès.',
        'icon' => 'success'
    ];
    
    header('Location: ../grh/agent.edition&searchType=code&search=' . $agent['codeAgent'] . '&tab=' . $returnTab);
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Préparer le message d'erreur pour SweetAlert
    $_SESSION['swal_error'] = [
        'title' => 'Erreur!',
        'text' => 'Erreur de base de données: ' . $e->getMessage(),
        'icon' => 'error'
    ];
    
    header('Location: ../grh/agent.edition&searchType=code&search=' . ($agent['codeAgent'] ?? '') . '&tab=' . $returnTab);
    exit;
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Préparer le message d'erreur pour SweetAlert
    $_SESSION['swal_error'] = [
        'title' => 'Erreur!',
        'text' => 'Erreur serveur: ' . $e->getMessage(),
        'icon' => 'error'
    ];
    
    header('Location: ../grh/agent.edition&searchType=code&search=' . ($agent['codeAgent'] ?? '') . '&tab=' . $returnTab);
    exit;
}
