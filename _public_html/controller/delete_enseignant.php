<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Create an instance of the Universite class
$universite = new Universite();

// Check if the ID is provided in the query string
if (isset($_GET['idenseignant'])) {
    $idEnseignant = intval($_GET['idenseignant']);

    // Validate the ID
    if ($idEnseignant <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID enseignant invalide.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
        exit();
    }

    // Delete the teacher
    if ($universite->deleteTeacher($idEnseignant)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Enseignant supprimé avec succès.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'enseignant.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
    }
} else {
    // Redirect if accessed directly without an ID
    header("Location: ../cours/enseignant");
    exit();
}
?>