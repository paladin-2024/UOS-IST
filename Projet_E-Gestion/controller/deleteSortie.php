<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Retrieve the sortie ID from the form
    $sortieId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $userId = $_SESSION['id'];

    // Validate the sortie ID
    if ($sortieId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de sortie invalide.'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        if ($structure->deleteSortieDepot($sortieId, $userId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Sortie supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../logistique/depot.sortie.add';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la sortie: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/depot.sortie.add");
    exit();
}