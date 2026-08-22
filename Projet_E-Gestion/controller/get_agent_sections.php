<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de l'agent est fourni
if (!isset($_GET['idAgent']) || empty($_GET['idAgent'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID agent requis']);
    exit;
}

$idAgent = intval($_GET['idAgent']);
$universite = new Universite();

try {
    // Récupérer les sections de l'agent
    $sections = $universite->getAgentSections($idAgent);
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($sections);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
