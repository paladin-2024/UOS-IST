<?php
require_once '../config/Connexion.php';
require_once '../models/Structure.php';

session_start();
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

if (!isset($_GET['manifestId']) || !is_numeric($_GET['manifestId'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de manifeste invalide']);
    exit();
}

try {
    $structure = new Structure();
    $manifestId = intval($_GET['manifestId']);
    
    $details = $structure->getDetailsEntreeByManifest($manifestId);
    
    header('Content-Type: application/json');
    echo json_encode($details);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Une erreur est survenue lors de la récupération des détails',
        'message' => $e->getMessage()
    ]);
}