<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SuiviCours.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$idUE             = filter_input(INPUT_POST, 'idUE',             FILTER_VALIDATE_INT);
$designationECUE  = trim(filter_input(INPUT_POST, 'designationECUE', FILTER_SANITIZE_STRING) ?? '');
$CMI              = filter_input(INPUT_POST, 'CMI', FILTER_VALIDATE_FLOAT) ?: 0;
$TD               = filter_input(INPUT_POST, 'TD',  FILTER_VALIDATE_FLOAT) ?: 0;
$TP               = filter_input(INPUT_POST, 'TP',  FILTER_VALIDATE_FLOAT) ?: 0;
$idCreateur       = (int) $_SESSION['id'];

if (!$idUE || !$designationECUE) {
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
    exit;
}

try {
    $model  = new SuiviCours();
    $idECUE = $model->addECUE($designationECUE, $CMI, $TD, $TP, $idUE, $idCreateur);

    echo json_encode([
        'success'         => true,
        'message'         => 'Cours (ECUE) ajouté avec succès',
        'idECUE'          => $idECUE,
        'designationECUE' => $designationECUE,
    ]);
} catch (Exception $e) {
    error_log('addSuiviECUE: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système : ' . $e->getMessage()]);
}
