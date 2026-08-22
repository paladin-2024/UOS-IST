<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Vérifier que l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID de caisse non fourni.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_caisses');
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    $id = intval($_GET['id']);
    
    // Vérifier si la caisse a des sessions actives
    $stmt = $connexion->prepare("SELECT id FROM sessions_caisse WHERE caisse_id = :id AND statut = 'Ouverte'");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Impossible de supprimer cette caisse car elle a des sessions actives.";
        $_SESSION['messageType'] = "danger";
        header('Location: ../?view=finance/config_caisses');
        exit;
    }
    
    // Vérifier si la caisse a des transactions
    $stmt = $connexion->prepare("SELECT id FROM transactions WHERE source = 'Caisse' AND source_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Impossible de supprimer cette caisse car elle a des transactions associées.";
        $_SESSION['messageType'] = "danger";
        header('Location: ../?view=finance/config_caisses');
        exit;
    }
    
    // Supprimer la caisse
    $stmt = $connexion->prepare("DELETE FROM caisses WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $_SESSION['message'] = "La caisse a été supprimée avec succès.";
    $_SESSION['messageType'] = "success";
    
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de la suppression: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des caisses
header('Location: ../?view=finance/config_caisses');
exit;