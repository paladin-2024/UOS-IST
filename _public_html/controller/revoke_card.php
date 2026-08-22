<?php
session_start();
require_once "../models/Connexion.php";
require_once "../utils/SecurityUtils.php";

// Vérification des droits d'accès
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Non autorisé'
    ]);
    exit();
}

try {
    // Récupérer et décoder les données JSON
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($inputData['cardId']) || !isset($inputData['reason']) || !isset($inputData['details'])) {
        throw new Exception("Données incomplètes");
    }
    
    $cardId = $inputData['cardId'];
    $reason = $inputData['reason'] . ': ' . $inputData['details'];
    $userId = $_SESSION['user']['id'] ?? 0;
    
    // Instancier l'utilitaire de sécurité
    $securityUtils = new SecurityUtils();
    
    // Révoquer la carte
    $success = $securityUtils->revokeCard($cardId, $reason, $userId);
    
    if (!$success) {
        throw new Exception("Impossible de révoquer la carte");
    }
    
    // Réponse positive
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Carte révoquée avec succès'
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
