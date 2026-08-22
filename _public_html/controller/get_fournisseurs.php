<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/Structure.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['id'])) {
        throw new Exception('Utilisateur non connecté');
    }

    $userId = $_SESSION['id'];
    $structure = new Structure();
    
    // Récupérer les fournisseurs accessibles à l'utilisateur connecté
    $fournisseurs = $structure->getFournisseursByUserAccess($userId,'');
    
    // Retourner les données au format JSON
    echo json_encode([
        'status' => 'success',
        'data' => $fournisseurs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}