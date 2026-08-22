<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/Agent.php';

// Vérification de la connexion et des droits
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$agent = new Agent();
$stat = isset($_GET['stat']) ? $_GET['stat'] : '';
$result = [];

switch ($stat) {
    case 'type':
        // Statistiques par type d'agent
        $result = $agent->getAgentCountsByType();
        break;
    
    case 'structure':
        // Statistiques par structure
        $result = $agent->getAgentCountsByStructure();
        break;
    
    default:
        $result = ['error' => 'Statistique non reconnue'];
}

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($result);
exit;
