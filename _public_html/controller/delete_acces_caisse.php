<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

// Vérifier si l'ID est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID manquant pour la suppression.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_acces_caisses');
    exit;
}

$id = intval($_GET['id']);
$connexion = Connexion::getInstance()->getPDO();

try {
    // Vérifier si le droit d'accès existe
    $stmt = $connexion->prepare("SELECT id FROM droits_acces_finances WHERE id = :id AND type = 'Caisse'");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['message'] = "Le droit d'accès demandé n'existe pas.";
        $_SESSION['messageType'] = "danger";
        header('Location: ../?view=finance/config_acces_caisses');
        exit;
    }
    
    // Supprimer le droit d'accès
    $stmt = $connexion->prepare("DELETE FROM droits_acces_finances WHERE id = :id AND type = 'Caisse'");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $_SESSION['message'] = "Le droit d'accès a été supprimé avec succès.";
    $_SESSION['messageType'] = "success";
    
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de la suppression: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des accès caisses
header('Location: ../?view=finance/config_acces_caisses');
exit;