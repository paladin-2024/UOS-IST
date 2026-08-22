<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $designationOrientation = $_POST['designationOrientation'] ?? '';
    $sectionId = $_POST['sectionId'] ?? '';

    // Validate inputs
    if (empty($designationOrientation) || empty($sectionId)) {
        $_SESSION['error'] = 'Tous les champs obligatoires doivent être remplis.';
        header("Location: ../index.php?view=configuration/orientation");
        exit();
    }

    // Create the orientation
    $result = $universite->createOrientation($designationOrientation, $sectionId);

    if ($result) {
        $_SESSION['success'] = 'Orientation créée avec succès.';
    } else {
        $_SESSION['error'] = 'Erreur lors de la création de l\'orientation.';
    }
    
    header("Location: ../index.php?view=configuration/orientation");
    exit();
} else {
    header("Location: ../index.php?view=configuration/orientation");
    exit();
}
?>
