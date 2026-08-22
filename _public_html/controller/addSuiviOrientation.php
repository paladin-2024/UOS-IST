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

$idsection            = filter_input(INPUT_POST, 'idsection',            FILTER_VALIDATE_INT);
$designationOrientation = trim(filter_input(INPUT_POST, 'designationOrientation', FILTER_SANITIZE_STRING) ?? '');

if (!$idsection || $designationOrientation === '') {
    echo json_encode(['success' => false, 'message' => 'Nom de l\'orientation requis']);
    exit;
}

try {
    $model = new SuiviCours();
    $idorientation = $model->getOrCreateOrientation($designationOrientation, $idsection);

    echo json_encode([
        'success'                => true,
        'idorientation'          => $idorientation,
        'designationOrientation' => $designationOrientation,
        'message'                => 'Orientation créée avec succès',
    ]);
} catch (Exception $e) {
    error_log('addSuiviOrientation: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système : ' . $e->getMessage()]);
}
