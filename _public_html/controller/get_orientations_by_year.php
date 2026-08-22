<?php
/**
 * API endpoint pour charger les orientations d'une année académique donnée
 * Utilisé dans les modals de création/édition de promotion
 */

header('Content-Type: application/json');

// Vérifier que annee_id est fourni
if (!isset($_GET['annee_id']) || empty($_GET['annee_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID année académique requis'
    ]);
    exit;
}

require_once "../config/Connexion.php";
require_once "../models/Universite.php";

try {
    $universite = new Universite();
    $anneeId = (int) $_GET['annee_id'];
    
    // Récupérer les orientations pour cette année académique
    $orientations = $universite->getOrientations('', $anneeId);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'orientations' => $orientations
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
