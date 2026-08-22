<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$section = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $idSection = isset($_GET['idsection']) ? intval($_GET['idsection']) : 0;

    if ($idSection <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Identifiant invalide pour la section.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
        exit();
    }

    // Delete the section
    if ($section->deleteSection($idSection)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Section supprimée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la section.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/faculte");
    exit();
}
?>