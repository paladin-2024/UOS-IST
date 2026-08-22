<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../index');
    exit;
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de la formation manquant.";
    header('Location: ../grh/agent.liste');
    exit;
}

$formationId = intval($_GET['id']);
$agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
$returnTab = isset($_GET['returnTab']) ? $_GET['returnTab'] : 'formations';

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
    
    // Vérifier si la formation existe et appartient à l'agent
    $queryCheck = "SELECT * FROM formation_agent WHERE idformation = :id AND idAgent = :idAgent";
    $stmtCheck = $pdo->prepare($queryCheck);
    $stmtCheck->bindParam(':id', $formationId, PDO::PARAM_INT);
    $stmtCheck->bindParam(':idAgent', $agentId, PDO::PARAM_INT);
    $stmtCheck->execute();
    
    $formation = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$formation) {
        throw new Exception("Formation non trouvée ou n'appartient pas à cet agent.");
    }
    
    // Supprimer le fichier de diplôme associé s'il existe
    if (!empty($formation['diplome_fichier']) && file_exists(dirname(__DIR__) . '/' . $formation['diplome_fichier'])) {
        unlink(dirname(__DIR__) . '/' . $formation['diplome_fichier']);
    }
    
    // Supprimer la formation
    $queryDelete = "DELETE FROM formation_agent WHERE idformation = :id";
    $stmtDelete = $pdo->prepare($queryDelete);
    $stmtDelete->bindParam(':id', $formationId, PDO::PARAM_INT);
    $stmtDelete->execute();
    
    // Valider la transaction
    $pdo->commit();
    
    // Préparer le message de succès pour SweetAlert
    $_SESSION['swal_success'] = [
        'title' => 'Succès!',
        'text' => 'La formation a été supprimée avec succès.',
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
