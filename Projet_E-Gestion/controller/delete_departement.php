<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['iddepartement'])) {
    $departementId = intval($_GET['iddepartement']);

    // Validate input
    if ($departementId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de département invalide.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
        exit();
    }

    // Delete the department
    $result = $universite->deleteDepartement($departementId);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Département supprimé avec succès.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du département.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../configuration/departement");
    exit();
}
?>