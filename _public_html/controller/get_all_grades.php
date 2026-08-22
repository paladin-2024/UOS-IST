<?php
// Returns all grades
// JSON: [ { idgrade, designation, type_agent, ... } ]

error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Grade.php';

header('Content-Type: application/json');

try {
    $model = new Grade();
    $grades = $model->getAllGrades();
    echo json_encode($grades);
} catch (Exception $e) {
    error_log('Erreur get_all_grades: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
?>

