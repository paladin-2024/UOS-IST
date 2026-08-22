<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si l'ID du chapitre est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID du chapitre non valide']);
    exit;
}

$idPartie = intval($_GET['id']);
$ecueModel = new Ecue();

// Récupérer les détails du chapitre
$chapitre = $ecueModel->getChapterById($idPartie);

if (!$chapitre) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Chapitre non trouvé']);
    exit;
}

// Renvoyer les détails du chapitre au format JSON
header('Content-Type: application/json');
echo json_encode($chapitre);
?>
