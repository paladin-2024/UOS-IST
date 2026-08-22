<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $designationDepartement = $_POST['designationDepartement'] ?? '';
    $sectionId = $_POST['sectionId'] ?? '';

    // Validate inputs
    if (empty($designationDepartement) || empty($sectionId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
        exit();
    }

    // Create the department
    $result = $universite->createDepartement($designationDepartement, $sectionId);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Département créé avec succès.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la création du département.'
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