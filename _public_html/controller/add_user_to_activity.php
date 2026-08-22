<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $activityId = isset($_POST['idActivite_projet']) ? intval($_POST['idActivite_projet']) : 0;

    // Validation des champs requis
    if ($userId <= 0 || $activityId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../projet/activite.add';
            });
        </script>";
        exit();
    }

    // Vérification si l'utilisateur est déjà associé à l'activité
    if ($projet->isUserInActivity($userId, $activityId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Cet utilisateur est déjà associé à cette activité.'
            }).then(() => {
                window.location.href = '../projet/activite.add';
            });
        </script>";
        exit();
    }

    // Ajout de l'utilisateur à l'activité
    try {
        if ($projet->addUserToActivity($userId, $activityId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur ajouté à l\'activité avec succès.'
                }).then(() => {
                    window.location.href = '../projet/activite.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de l\'ajout de l\'utilisateur à l\'activité');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'utilisateur à l\'activité: " . addslashes($e->getMessage()) . "'
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