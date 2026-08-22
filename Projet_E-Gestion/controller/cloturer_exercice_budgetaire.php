<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de l'exercice
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    $_SESSION['message'] = "ID de l'exercice non fourni.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_exercices_budgetaires');
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Vérifier si l'exercice existe et n'est pas déjà clôturé
    $stmt = $connexion->prepare("SELECT designation, est_cloture FROM exercices_budgetaires WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exercice) {
        throw new Exception("Exercice budgétaire non trouvé");
    }
    
    if ($exercice['est_cloture']) {
        throw new Exception("Cet exercice est déjà clôturé");
    }
    
    // Clôturer l'exercice
    $stmt = $connexion->prepare("
        UPDATE exercices_budgetaires 
        SET est_cloture = 1, 
            date_cloture = NOW(), 
            est_actif = 0 
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $_SESSION['message'] = "L'exercice budgétaire '{$exercice['designation']}' a été clôturé avec succès.";
    $_SESSION['messageType'] = "success";
    
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur lors de la clôture de l'exercice: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des exercices
header('Location: ../?view=finance/config_exercices_budgetaires');
exit;