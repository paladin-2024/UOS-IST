<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (isset($_POST['editSessionBtn'])) {
    $idsession = $_POST['idsession'];
    $designSession = trim($_POST['designSession']);
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($designSession)) {
        $_SESSION['error'] = "La désignation de la session est requise.";
        header("Location: ../configuration/session");
        exit();
    }
    
    $universite = new Universite();
    
    // Tentative de mise à jour
    if ($universite->updateSession($idsession, $designSession,$description)) {
        echo "<!DOCTYPE html><body><script src=\"../assets/js/sweetalert.min.js\"></script><script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La session a été modifiée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/session';
            });
        </script>";
        exit();
    } else {
        echo "<!DOCTYPE html><body><script src=\"../assets/js/sweetalert.min.js\"></script><script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite lors de la modification de la session.'
            }).then(() => {
                window.location.href = '../configuration/session';
            });
        </script>";
        exit();
    }
    
} else {
    // Accès direct au script sans passer par le formulaire
    header("Location: ../configuration/session");
    exit();
}
?>