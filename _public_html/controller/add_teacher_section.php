<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$userId = $_SESSION['id'];

// Create instance of Universite class
$universite = new Universite();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $idEnseignant = isset($_POST['idenseignant']) ? intval($_POST['idenseignant']) : 0;
    $idSection = isset($_POST['idsection']) ? intval($_POST['idsection']) : 0;
    $estPrincipal = isset($_POST['estPrincipal']) ? 1 : 0;

    // Validate required fields
    if ($idEnseignant <= 0 || $idSection <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Enseignant et section sont obligatoires.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
        exit();
    }

    // Check for duplicate section assignment
    if ($universite->checkDuplicateTeacherSection($idEnseignant, $idSection)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Cet enseignant est déjà affecté à cette section.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
        exit();
    }

    // Call the function to add a section to a teacher
    if ($universite->addTeacherSection($idEnseignant, $idSection, $estPrincipal)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Section ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la section.'
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
