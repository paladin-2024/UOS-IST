<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    $_SESSION['message'] = "ID de la catégorie manquant.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_categories_budget');
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Vérifier si la catégorie a des sous-catégories
    $stmt = $connexion->prepare("SELECT COUNT(*) as count FROM categories_budget WHERE parent_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $hasSubcategories = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($hasSubcategories) {
        $_SESSION['message'] = "Impossible de supprimer cette catégorie car elle a des sous-catégories. Veuillez d'abord supprimer ou réaffecter les sous-catégories.";
        $_SESSION['messageType'] = "warning";
        header('Location: ../?view=finance/config_categories_budget');
        exit;
    }
    
    // Vérifier s'il existe des transactions ou des budgets liés à cette catégorie
    $stmt = $connexion->prepare("
        SELECT COUNT(*) as count 
        FROM transactions 
        WHERE categorie_id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $hasTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    $stmt = $connexion->prepare("
        SELECT COUNT(*) as count 
        FROM budget 
        WHERE categorie_id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $hasBudgets = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($hasTransactions || $hasBudgets) {
        $_SESSION['message'] = "Impossible de supprimer cette catégorie car elle est utilisée dans des transactions ou des budgets. Vous pouvez la désactiver à la place.";
        $_SESSION['messageType'] = "warning";
    } else {
        // Supprimer la catégorie
        $stmt = $connexion->prepare("DELETE FROM categories_budget WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $_SESSION['message'] = "La catégorie budgétaire a été supprimée avec succès.";
        $_SESSION['messageType'] = "success";
    }
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de la suppression: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

header('Location: ../?view=finance/config_categories_budget');
exit;