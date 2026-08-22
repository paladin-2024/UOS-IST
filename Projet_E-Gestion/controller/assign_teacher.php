<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
    $teacherId = isset($_POST['teacherId']) ? intval($_POST['teacherId']) : 0;

    // Validate form data
    if ($idSpecialisation <= 0 || $teacherId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../ur/affecation_ur';
            });
        </script>";
        exit();
    }

    // Check if the teacher is already assigned to the specialization
    $existingAssignments = $universite->getTeachersBySpecialisation($idSpecialisation);
    foreach ($existingAssignments as $assignment) {
        if ($assignment['idenseignant'] == $teacherId) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Cet enseignant est déjà assigné à cette spécialisation.'
                }).then(() => {
                    window.location.href = '../ur/affecation_ur';
                });
            </script>";
            exit();
        }
    }

    // Assign the teacher to the specialization
    $result = $universite->assignTeacherToSpecialisation($teacherId, $idSpecialisation);

    // Redirect based on the result of the assignment
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Enseignant assigné avec succès.'
            }).then(() => {
                window.location.href = '../ur/affecation_ur';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'assignation de l\'enseignant.'
            }).then(() => {
                window.location.href = '../ur/affecation_ur';
            });
        </script>";
    }
} else {
    header("Location: ../ur/affecation_ur");
    exit();
}
?>