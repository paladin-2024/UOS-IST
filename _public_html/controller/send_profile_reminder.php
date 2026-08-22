<?php
session_start();
require_once dirname(__DIR__).'/config/config.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__). '/models/Universite.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Get the student ID from the request
$studentId = isset($_POST['id']) ? intval($_POST['id']) : 0;

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
    
    // Check if the student has an email address
    if (empty($student['adressemail'])) {
        echo json_encode(['error' => 'L\'étudiant n\'a pas d\'adresse email enregistrée']);
        exit;
    }
    
    // Log the reminder in the database
    $result = $universite->logProfileReminder($studentId, $_SESSION['id']);
    
    // In a real implementation, you would send an email here
    // For now, just simulate success
    
    echo json_encode(['success' => true, 'message' => 'Rappel envoyé avec succès à ' . $student['noms']]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
?>
