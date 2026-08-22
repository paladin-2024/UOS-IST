<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['idEnseignant']) && isset($_GET['idSpecialisation'])) {
    $idEnseignant = intval($_GET['idEnseignant']);
    $idSpecialisation = intval($_GET['idSpecialisation']);

    // Validate the IDs
    if ($idEnseignant <= 0 || $idSpecialisation <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID invalide pour l\'enseignant ou la spécialisation.'
            }).then(() => {
                window.location.href = '../ur/affecation_ur';
            });
        </script>";
        exit();
    }

    // Remove the teacher's assignment from the specialization
    $result = $universite->removeTeacherFromSpecialisation($idEnseignant, $idSpecialisation);

    // Redirect based on the result of the removal
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Enseignant retiré avec succès.'
            }).then(() => {
                window.location.href = '../ur/affecation_ur';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors du retrait de l\'enseignant.'
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