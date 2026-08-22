<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    try {
        $db = Connexion::getInstance()->getPDO();
        $id_unite = intval($_GET['id']);
        
        // Vérifier si l'unité est utilisée dans les produits
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM produit 
                             WHERE id_unite_stockage = :id_unite OR id_unite_vente = :id_unite");
        $stmt->bindParam(':id_unite', $id_unite, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            throw new Exception("Impossible de supprimer cette unité de mesure car elle est utilisée par un ou plusieurs produits.");
        }
        
        // Supprimer l'unité
        $stmt = $db->prepare("DELETE FROM unite_mesure WHERE id_unite = :id_unite");
        $stmt->bindParam(':id_unite', $id_unite, PDO::PARAM_INT);
        $stmt->execute();
        
        // Rediriger avec un message de succès
        $_SESSION['success_message'] = "Unité de mesure supprimée avec succès.";
        header('Location: ../configuration/unite.list');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../configuration/unite.list');
        exit();
    }
} else {
    // Redirection si ID invalide
    $_SESSION['error_message'] = "Identifiant d'unité invalide.";
    header('Location: ../configuration/unite.list');
    exit();
}
