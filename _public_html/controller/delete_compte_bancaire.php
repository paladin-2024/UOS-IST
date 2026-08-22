<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID du compte non spécifié.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_comptes_bancaires');
    exit();
}

$id = intval($_GET['id']);

try {
    // Initialiser la connexion
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier si le compte existe
    $stmt = $db->prepare("SELECT id, intitule_compte FROM comptes_bancaires WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compte) {
        $_SESSION['message'] = "Compte bancaire non trouvé.";
        $_SESSION['messageType'] = "danger";
        header('Location: ../?view=finance/config_comptes_bancaires');
        exit();
    }
    
    // Vérifier s'il y a des transactions liées à ce compte
    $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE source = 'Banque' AND source_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['message'] = "Impossible de supprimer ce compte car il est associé à {$count} transaction(s).";
        $_SESSION['messageType'] = "warning";
        header('Location: ../?view=finance/config_comptes_bancaires');
        exit();
    }
    
    // Supprimer le compte
    $stmt = $db->prepare("DELETE FROM comptes_bancaires WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Journalisation de l'action
    $logStmt = $db->prepare("INSERT INTO journal_activites 
        (user_type, user_id, type_activite, id_element, description, date_activite) 
        VALUES 
        ('admin', :user_id, 'compte_bancaire', :id_element, :description, NOW())");
    
    $description = "Suppression du compte bancaire #{$id} ({$compte['intitule_compte']})";
    
    $logStmt->bindParam(':user_id', $_SESSION['id'], PDO::PARAM_INT);
    $logStmt->bindParam(':id_element', $id, PDO::PARAM_INT);
    $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
    
    $logStmt->execute();
    
    $_SESSION['message'] = "Le compte bancaire a été supprimé avec succès.";
    $_SESSION['messageType'] = "success";
    
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

header('Location: ../?view=finance/config_comptes_bancaires');
exit();