<?php
// Returns grades for a given agent type
// Params: type (string)
// JSON: [ { idgrade, designation } ]

error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Grade.php';

header('Content-Type: application/json');

try {
    $type = isset($_GET['type']) ? trim($_GET['type']) : '';
    if ($type === '') {
        echo json_encode([]);
        exit;
    }

    $model = new Grade();
    $grades = $model->getGradesByType($type);
    echo json_encode($grades);
} catch (Exception $e) {
    error_log('Erreur get_grades_by_type: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
?>

