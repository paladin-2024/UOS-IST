<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/User.php';

// Créer une instance de la classe User en passant la connexion
$user = new User();

if (isset($_POST['btnUserPass'])) {
    // Récupérer les données du formulaire
    $id = $_POST['id'];
    $password = !empty($_POST['newPassword']) ? password_hash($_POST['newPassword'], PASSWORD_BCRYPT) : null;
    $success = $user->updateUserPassWord($id, $password ?? null);


    if ($success) {
        // Redirection après mise à jour avec succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Information mis à jour avec succès'
            }).then(() => {
                // window.location.href = '../accueil';
                window.history.back();
            });
        </script>";
    } else {
        // Message d'erreur en cas d'échec de mise à jour
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de l\'utilisateur.'
            }).then(() => {
                // window.location.href = '../accueil';
                window.history.back();
            });
        </script>";
    }
}
