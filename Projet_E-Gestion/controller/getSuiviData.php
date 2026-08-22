<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SuiviCours.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

$action   = htmlspecialchars(strip_tags($_GET['action'] ?? ''));
$idsection = filter_input(INPUT_GET, 'idsection', FILTER_VALIDATE_INT);
$idannee   = filter_input(INPUT_GET, 'idannee',   FILTER_VALIDATE_INT);

try {
    $model = new SuiviCours();

    if ($action === 'semestres') {
        $idpromotion = filter_input(INPUT_GET, 'idpromotion', FILTER_VALIDATE_INT);
        if (!$idpromotion) {
            echo json_encode(['success' => false, 'message' => 'Promotion manquante']);
            exit;
        }
        $data = $model->getSemestresByPromotion($idpromotion);
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'ues') {
        $idsemestre = filter_input(INPUT_GET, 'idsemestre', FILTER_VALIDATE_INT);
        if (!$idsemestre) {
            echo json_encode(['success' => false, 'message' => 'Semestre manquant']);
            exit;
        }
        $data = $model->getUEsBySemestre($idsemestre);
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'orientations') {
        if (!$idsection) {
            echo json_encode(['success' => false, 'message' => 'Section manquante']);
            exit;
        }
        $data = $model->getOrientationsBySection($idsection);
        echo json_encode(['success' => true, 'data' => $data]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
} catch (Exception $e) {
    error_log('getSuiviData: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système']);
}
