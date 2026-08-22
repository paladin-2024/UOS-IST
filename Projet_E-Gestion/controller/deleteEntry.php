<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Retrieve the entry ID from the form
    $entryId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $userId = $_SESSION['id'];

    // Validate the entry ID
    if ($entryId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID d\'entrée invalide.'
            }).then(() => {
                window.location.href = '../logistique/depot.entree.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        if ($structure->deleteEntreeDepot($entryId, $userId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Entrée supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../logistique/depot.entree.add';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'entrée: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.entree.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/depot.entree.add");
    exit();
}