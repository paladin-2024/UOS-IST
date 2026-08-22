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
    
    // Vérifier si l'exercice existe, n'est pas actif et n'est pas clôturé
    $stmt = $connexion->prepare("
        SELECT designation, est_actif, est_cloture 
        FROM exercices_budgetaires 
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exercice) {
        throw new Exception("Exercice budgétaire non trouvé");
    }
    
    if ($exercice['est_actif']) {
        throw new Exception("Impossible de supprimer un exercice actif");
    }
    
    if ($exercice['est_cloture']) {
        throw new Exception("Impossible de supprimer un exercice clôturé");
    }
    
    // Vérifier s'il y a des opérations liées à cet exercice
    $stmt = $connexion->prepare("SELECT COUNT(*) FROM budget WHERE exercice_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        throw new Exception("Impossible de supprimer cet exercice car il contient des données budgétaires");
    }
    
    // Supprimer l'exercice
    $stmt = $connexion->prepare("DELETE FROM exercices_budgetaires WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $_SESSION['message'] = "L'exercice budgétaire '{$exercice['designation']}' a été supprimé avec succès.";
    $_SESSION['messageType'] = "success";
    
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur lors de la suppression: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des exercices
header('Location: ../?view=finance/config_exercices_budgetaires');
exit;