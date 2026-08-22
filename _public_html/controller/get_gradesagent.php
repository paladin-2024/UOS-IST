<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Grade.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si le type d'agent est fourni
if (!isset($_GET['type']) || empty($_GET['type'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Type d\'agent non fourni']);
    exit;
}

$type = $_GET['type'];

// Récupérer les grades pour le type d'agent spécifié
$gradeModel = new Grade();
$grades = $gradeModel->getGradesByType($type);

// Retourner les résultats au format JSON
header('Content-Type: application/json');
echo json_encode($grades);
exit;
