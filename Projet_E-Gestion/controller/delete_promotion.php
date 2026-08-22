<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['idpromotion'])) {
    $promotionId = $_GET['idpromotion'];

    // Validate input
    if (empty($promotionId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de promotion manquant.'
            }).then(() => {
                window.location.href = '../configuration/promotion';
            });
        </script>";
        exit();
    }

    // Delete the promotion
    $result = $universite->deletePromotion($promotionId);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Promotion supprimée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/promotion';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la promotion.'
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