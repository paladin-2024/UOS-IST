<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $promotionId = $_POST['editPromotionId'] ?? '';
    $designationPromotion = $_POST['editPromotionDesignation'] ?? '';
    $cycle = $_POST['editCycle'] ?? '';
    $orientationId = $_POST['editOrientationId'] ?? '';
    $anneeAcadId = $_POST['editAnneeId'] ?? '';
    $estTerminale = isset($_POST['editEstTerminale']) ? 1 : 0;

    // Validate inputs
    if (empty($promotionId) || empty($designationPromotion) || empty($cycle) || empty($orientationId) || empty($anneeAcadId)) {
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

    // Update the promotion
    $result = $universite->updatePromotion($promotionId, $designationPromotion, $cycle, $orientationId, $anneeAcadId, $estTerminale);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Promotion mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/promotion';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la promotion.'
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
