<?php
session_start();

require_once dirname(__DIR__).'/config/config.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__). '/models/Universite.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Get the student ID from the request
$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($studentId <= 0) {
    echo json_encode(['error' => 'ID étudiant invalide']);
    exit;
}

try {
    $universite = new Universite();
    $student = $universite->getStudentById($studentId);
    
    if (!$student) {
        echo json_encode(['error' => 'Étudiant non trouvé']);
        exit;
    }
    
    // Return the student data as JSON
    echo json_encode($student);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
?>
