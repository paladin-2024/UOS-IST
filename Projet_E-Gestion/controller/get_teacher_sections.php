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

// Vérifier si l'ID de l'enseignant est fourni
if (!isset($_GET['idenseignant']) || empty($_GET['idenseignant'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID enseignant requis']);
    exit;
}

$idEnseignant = intval($_GET['idenseignant']);
$universite = new Universite();

try {
    // Récupérer les sections de l'enseignant
    $sections = $universite->getTeacherSections($idEnseignant);
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($sections);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
