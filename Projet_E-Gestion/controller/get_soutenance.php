<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Soutenance.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de la soutenance est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de soutenance manquant']);
    exit;
}

$idSoutenance = intval($_GET['id']);

// Initialiser le modèle
$soutenanceModel = new Soutenance();

// Récupérer les données de la soutenance
$soutenance = $soutenanceModel->getSoutenanceById($idSoutenance);

if (!$soutenance) {
    echo json_encode(['success' => false, 'message' => 'Soutenance non trouvée']);
    exit;
}

// Récupérer les lecteurs associés à la soutenance
$lecteurs = $soutenanceModel->getLecteursParSoutenance($idSoutenance);

// Ajouter les lecteurs à la réponse
$soutenance['lecteurs'] = $lecteurs;

echo json_encode(['success' => true, 'soutenance' => $soutenance]);