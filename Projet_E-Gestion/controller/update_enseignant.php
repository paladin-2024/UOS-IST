<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

// Create instances of necessary classes
$universite = new Universite();
$structure = new Structure();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $grade = isset($_POST['grade']) ? trim($_POST['grade']) : '';
    $idDepartement = isset($_POST['idDepartement']) ? intval($_POST['idDepartement']) : 0;

    // Validate required fields
    if ($id <= 0 || empty($grade) || $idDepartement <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs sont obligatoires.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
        exit();
    }

    

    // Update teacher
    if ($universite->updateTeacher($id, $grade, $idDepartement)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Enseignant mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de l\'enseignant.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
    }
} else {
    // Redirect if accessed directly without form submission
    header("Location: ../cours/enseignant");
    exit();
}
?>