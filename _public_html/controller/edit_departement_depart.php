<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departementId = $_POST['editDepartementId'] ?? '';
    $designationDepartement = $_POST['editDepartementDesignation'] ?? '';
    $sectionId = $_POST['editSectionId'] ?? '';

    // Validate inputs
    if (empty($departementId) || empty($designationDepartement) || empty($sectionId)) {
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

    // Update the department
    $result = $universite->updateDepartement($departementId, $designationDepartement, $sectionId);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Département mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du département.'
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