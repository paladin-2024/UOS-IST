<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérification de la connexion
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Vérification de l'ID de délibération
if (!isset($_GET['deliberation_id']) || !is_numeric($_GET['deliberation_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de délibération invalide']);
    exit;
}

$deliberationId = intval($_GET['deliberation_id']);
$universite = new Universite();

try {
    // Récupérer les informations de la délibération
    $info = $universite->getDeliberationById($deliberationId);
    
    if (!$info) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Délibération non trouvée']);
        exit;
    }
    
    // Renvoyer les données
    header('Content-Type: application/json');
    echo json_encode([
        'promotion_id' => $info['idpromotion'],
        'success' => true
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
