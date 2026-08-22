<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Retrieve the detail ID from the form
    $detailId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $userId = $_SESSION['id'];

    // Validate the detail ID
    if ($detailId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de détail invalide.'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        if ($structure->deleteDetailSortie($detailId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Détail supprimé avec succès.'
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
                text: 'Erreur lors de la suppression du détail: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/depot.sortie.add");
    exit();
}