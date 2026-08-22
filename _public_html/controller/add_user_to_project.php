<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $projectId = isset($_POST['idProjet']) ? intval($_POST['idProjet']) : 0;

    // Validation des champs requis
    if ($userId <= 0 || $projectId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../projet/projet.add';
            });
        </script>";
        exit();
    }

    // Vérification si l'utilisateur est déjà associé au projet
    if ($projet->isUserInProject($userId, $projectId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Cet utilisateur est déjà associé à ce projet.'
            }).then(() => {
                window.location.href = '../projet/projet.add';
            });
        </script>";
        exit();
    }

    // Ajout de l'utilisateur au projet
    try {
        if ($projet->addUserToProject($userId, $projectId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur ajouté au projet avec succès.'
                }).then(() => {
                    window.location.href = '../projet/projet.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de l\'ajout de l\'utilisateur au projet');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'utilisateur au projet: " . addslashes($e->getMessage()) . "'
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