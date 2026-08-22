<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de l'affectation est fourni
if (!isset($_POST['idenseignant_section']) || empty($_POST['idenseignant_section'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID affectation requis']);
    exit;
}

$idEnseignantSection = intval($_POST['idenseignant_section']);
$universite = new Universite();

try {
    // Supprimer l'affectation
    $result = $universite->deleteTeacherSection($idEnseignantSection);
    
    // Retourner le résultat
    header('Content-Type: application/json');
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
