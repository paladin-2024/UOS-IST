<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si le matricule est fourni
    if (!isset($_POST['matricule']) || empty($_POST['matricule'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Matricule non fourni'
        ]);
        exit;
    }
    
    $matricule = trim($_POST['matricule']);
    
    // Instancier la classe Universite
    $universite = new Universite();
    
    // Vérifier si le matricule existe dans la table etudiant_tempon
    $student = $universite->getStudentTemponByMatricule($matricule);
    
    if ($student) {
        echo json_encode([
            'success' => true,
            'student' => $student
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Matricule non trouvé'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
}
