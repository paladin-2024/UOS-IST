<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Récupération et validation de l'ID
    $userDepotId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Validation du champ requis
    if ($userDepotId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID utilisateur-dépôt invalide.'
            }).then(() => {
                window.location.href = '../logistique/depot.add';
            });
        </script>";
        exit();
    }

    try {
        // Tentative de suppression de l'utilisateur du dépôt
        if ($structure->deleteUserFromDepot($userDepotId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur retiré du dépôt avec succès.'
                }).then(() => {
                    window.location.href = '../logistique/depot.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la suppression de l\'utilisateur du dépôt');
        }
    } catch (Exception $e) {
        // Gestion des erreurs
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.add';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../logistique/depot.add");
    exit();
}
?>