<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (isset($_GET['idsession'])) {
    $idsession = $_GET['idsession'];
    
    $universite = new Universite();
    
    // Tentative de suppression
    if ($universite->deleteSession($idsession)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La session a été supprimée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/session';
            });
        </script>";
        exit();
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite lors de la suppression de la session.'
            }).then(() => {
                window.location.href = '../configuration/session';
            });
        </script>";
        exit();
    }
    
} else {
    // Accès direct au script sans paramètre
    header("Location: ../configuration/session");
    exit();
}
?>