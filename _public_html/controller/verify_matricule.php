<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['matricule'])) {
    $matricule = $_POST['matricule'];
    
    // Nettoyage et validation
    $matricule = trim(htmlspecialchars($matricule));
    
    if (empty($matricule)) {
        echo json_encode([
            'success' => false,
            'message' => 'Le matricule est requis'
        ]);
        exit;
    }
    
    // Instancier la classe Universite
    $universite = new Universite();
    
    // Rechercher l'étudiant par matricule
    $student = $universite->getStudentByMatricule($matricule);
    
    if ($student) {
        echo json_encode([
            'success' => true,
            'student' => $student
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Matricule non reconnu'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Requête invalide'
    ]);
}
