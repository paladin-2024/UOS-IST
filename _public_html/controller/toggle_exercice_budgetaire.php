<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID et l'action
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$id || !in_array($action, ['activer', 'desactiver'])) {
    $_SESSION['message'] = "Paramètres invalides.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_exercices_budgetaires');
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Vérifier si l'exercice existe et n'est pas clôturé
    $stmt = $connexion->prepare("SELECT est_cloture FROM exercices_budgetaires WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exercice) {
        throw new Exception("Exercice budgétaire non trouvé");
    }
    
    if ($exercice['est_cloture']) {
        throw new Exception("Impossible de modifier le statut d'un exercice clôturé");
    }
    
    // Traiter l'action
    if ($action === 'activer') {
        // Désactiver tous les autres exercices d'abord
        $stmt = $connexion->prepare("UPDATE exercices_budgetaires SET est_actif = 0");
        $stmt->execute();
        
        // Activer l'exercice demandé
        $stmt = $connexion->prepare("UPDATE exercices_budgetaires SET est_actif = 1 WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $_SESSION['message'] = "L'exercice budgétaire a été activé avec succès.";
    } else {
        // Désactiver l'exercice demandé
        $stmt = $connexion->prepare("UPDATE exercices_budgetaires SET est_actif = 0 WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $_SESSION['message'] = "L'exercice budgétaire a été désactivé avec succès.";
    }
    
    $_SESSION['messageType'] = "success";
    
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des exercices
header('Location: ../?view=finance/config_exercices_budgetaires');
exit;