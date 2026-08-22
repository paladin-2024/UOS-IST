<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $designationPromotion = $_POST['designationPromotion'] ?? '';
    $cycle = $_POST['cycle'] ?? '';
    $orientationId = $_POST['orientationId'] ?? '';
    $anneeAcadId = $_POST['idAnnee'] ?? '';
    $estTerminale = isset($_POST['estTerminale']) ? 1 : 0;

    // Validate inputs
    if (empty($designationPromotion) || empty($cycle) || empty($orientationId) || empty($anneeAcadId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/promotion';
            });
        </script>";
        exit();
    }

    // Create the promotion
    $result = $universite->createPromotion($designationPromotion, $cycle, $orientationId, $anneeAcadId, $estTerminale);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Promotion créée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/promotion';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la création de la promotion.'
            }).then(() => {
                window.location.href = '../configuration/promotion';
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../configuration/promotion");
    exit();
}
?>
