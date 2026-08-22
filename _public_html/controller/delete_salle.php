<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if (isset($_GET['idSalle'])) {
    $idSalle = intval($_GET['idSalle']);
    
    if ($universite->deleteSalle($idSalle)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La salle a été supprimée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/salle';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la salle.'
            }).then(() => {
                window.location.href = '../configuration/salle';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/salle.php");
    exit();
}
?>
