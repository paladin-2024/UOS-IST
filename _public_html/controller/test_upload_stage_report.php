<?php
session_start();

// Log de débogage
error_log("=== TEST UPLOAD STAGE REPORT ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));
error_log("SESSION data: " . print_r($_SESSION, true));

// Créer une réponse de test
$response = [
    'status' => 'debug',
    'method' => $_SERVER['REQUEST_METHOD'],
    'post' => $_POST,
    'files' => $_FILES,
    'session_student_id' => isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'NOT SET'
];

// Si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    
    // Vérifier si le bouton a été cliqué
    if (isset($_POST['uploadReportBtn'])) {
        error_log("uploadReportBtn is set");
        $response['button_clicked'] = true;
    } else {
        error_log("uploadReportBtn is NOT set");
        $response['button_clicked'] = false;
    }
    
    // Vérifier le stage_id
    if (isset($_POST['stage_id'])) {
        error_log("stage_id: " . $_POST['stage_id']);
        $response['stage_id'] = $_POST['stage_id'];
    }
    
    // Vérifier le fichier
    if (isset($_FILES['report_file'])) {
        error_log("File uploaded: " . $_FILES['report_file']['name']);
        $response['file_name'] = $_FILES['report_file']['name'];
        $response['file_size'] = $_FILES['report_file']['size'];
        $response['file_error'] = $_FILES['report_file']['error'];
    }
}

// Retourner une réponse JSON pour le débogage
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
exit;
?>