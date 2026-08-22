<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../index.php');
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: ../index.php');
    exit;
}

// Récupérer les données du formulaire
$idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
$returnTab = isset($_POST['returnTab']) ? $_POST['returnTab'] : 'grades';

// Validation des données
if (empty($idAgent)) {
    $_SESSION['error'] = "ID de l'agent non spécifié.";
    header('Location: ../index.php?page=grh/agent.edition');
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer le code de l'agent pour la redirection
    $queryAgent = "SELECT \"codeAgent\" FROM agent WHERE \"idAgent\" = :idAgent";
    $stmtAgent = $pdo->prepare($queryAgent);
    $stmtAgent->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtAgent->execute();
    $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        throw new Exception("Agent non trouvé.");
    }
    
    // Vérifier si des modifications ont été apportées aux grades
    // Note: Les modifications individuelles sont gérées par save_grade_history.php via AJAX
    // Ce contrôleur est principalement utilisé pour la soumission du formulaire complet
    
    // Préparer le message de succès pour SweetAlert
    $_SESSION['swal_success'] = [
        'title' => 'Succès!',
        'text' => 'Les informations de grades ont été mises à jour avec succès.',
        'icon' => 'success'
    ];
    
    header('Location: ../grh/agent.edition&searchType=code&search=' . $agent['codeAgent'] . '&tab=' . $returnTab);
    
} catch (PDOException $e) {
    // Préparer le message d'erreur pour SweetAlert
    $_SESSION['swal_error'] = [
        'title' => 'Erreur!',
        'text' => 'Erreur de base de données: ' . $e->getMessage(),
        'icon' => 'error'
    ];
    
    header('Location: ../grh/agent.edition&tab=' . $returnTab);
    exit;
} catch (Exception $e) {
    // Préparer le message d'erreur pour SweetAlert
    $_SESSION['swal_error'] = [
        'title' => 'Erreur!',
        'text' => 'Erreur serveur: ' . $e->getMessage(),
        'icon' => 'error'
    ];
    
    header('Location: ../grh/agent.edition&tab=' . $returnTab);
    exit;
}
