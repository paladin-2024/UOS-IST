<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Récupérer l'ID du frais
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID de frais invalide']);
    exit();
}

$fraisModel = new Frais();
$frais = $fraisModel->getFraisSoutenanceById($id);

if (!$frais) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Frais non trouvé']);
    exit();
}

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($frais);
