<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $designationSalle = isset($_POST['designationSalle']) ? trim($_POST['designationSalle']) : '';

    if (empty($designationSalle)) {
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

    // Vérifier les doublons
    $existingSalles = $universite->getSalles();
    foreach ($existingSalles as $salle) {
        if (strcasecmp($salle['designationSalle'], $designationSalle) == 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Cette salle existe déjà.'
                }).then(() => {
                    window.location.href = '../configuration/salle';
                });
            </script>";
            exit();
        }
    }

    // Ajouter la nouvelle salle
    if ($universite->createSalle($designationSalle)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La salle a été ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/salle';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la salle.'
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
