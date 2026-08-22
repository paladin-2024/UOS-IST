<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $userProjectId = intval($_GET['id']);

    // Validation de l'ID
    if ($userProjectId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID invalide.'
            }).then(() => {
                window.location.href = '../projet/projet.add';
            });
        </script>";
        exit();
    }

    // Suppression de l'utilisateur du projet
    try {
        if ($projet->deleteUserFromProject($userProjectId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur supprimé du projet avec succès.'
                }).then(() => {
                    window.location.href = '../projet/projet.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la suppression de l\'utilisateur du projet');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'utilisateur du projet: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../projet/projet.add';
            });
        </script>";
    }
} else {
    header("Location: ../projet/projet.add");
    exit();
}
?>