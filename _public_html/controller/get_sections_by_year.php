<?php
/**
 * API endpoint pour charger les sections d'une année académique donnée
 * Utilisé dans les modals de création/édition d'orientation
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
    
    // Récupérer les sections pour cette année académique
    $sections = $universite->getSections('', $anneeId);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'sections' => $sections
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
