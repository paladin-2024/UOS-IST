<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['idSalle']) && isset($_POST['designationSalle'])) {
    $id = intval($_POST['idSalle']);
    $designation = trim($_POST['designationSalle']);

    if (empty($designation)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation est obligatoire.'
            }).then(() => {
                window.location.href = '../configuration/salle';
            });
        </script>";
        exit();
    }

    // Mise à jour de la salle
    if ($universite->updateSalle($id, $designation)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Salle mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/salle';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la salle.'
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
