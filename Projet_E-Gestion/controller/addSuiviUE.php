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

$idsemestre    = filter_input(INPUT_POST, 'idsemestre',    FILTER_VALIDATE_INT);
$codeUE        = trim(filter_input(INPUT_POST, 'codeUE',        FILTER_SANITIZE_STRING) ?? '');
$designationUE = trim(filter_input(INPUT_POST, 'designationUE', FILTER_SANITIZE_STRING) ?? '');
$description   = trim(filter_input(INPUT_POST, 'description',   FILTER_SANITIZE_STRING) ?? '');

if (!$idsemestre || !$codeUE || !$designationUE) {
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
    exit;
}

try {
    $model = new SuiviCours();
    $idUE  = $model->addUE($codeUE, $designationUE, $description, $idsemestre);

    echo json_encode([
        'success' => true,
        'message' => 'Unité d\'enseignement créée',
        'idUE'    => $idUE,
        'codeUE'  => $codeUE,
        'designationUE' => $designationUE,
    ]);
} catch (Exception $e) {
    error_log('addSuiviUE: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système : ' . $e->getMessage()]);
}
