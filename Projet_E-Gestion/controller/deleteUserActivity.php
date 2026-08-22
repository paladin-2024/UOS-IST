<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $userActivityId = intval($_GET['id']);

    // Validation de l'ID
    if ($userActivityId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID invalide.'
            }).then(() => {
                window.location.href = '../projet/activite.add';
            });
        </script>";
        exit();
    }

    // Suppression de l'utilisateur de l'activité
    try {
        if ($projet->deleteUserFromActivity($userActivityId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur supprimé de l\'activité avec succès.'
                }).then(() => {
                    window.location.href = '../projet/activite.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la suppression de l\'utilisateur de l\'activité');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'utilisateur de l\'activité: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../projet/activite.add';
            });
        </script>";
    }
} else {
    header("Location: ../projet/activite.add");
    exit();
}
?>