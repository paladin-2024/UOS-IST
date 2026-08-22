<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Service.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de structure est fourni
if (!isset($_GET['idStructure']) || empty($_GET['idStructure'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de structure manquant']);
    exit;
}

$idStructure = intval($_GET['idStructure']);

try {
    $service = new Service();
    $services = $service->getService($idStructure);
    
    header('Content-Type: application/json');
    echo json_encode($services);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur lors de la récupération des services: ' . $e->getMessage()]);
}
?>
