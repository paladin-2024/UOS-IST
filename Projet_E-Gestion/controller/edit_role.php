<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Role.php';

// Créer une instance de la classe Roles
$roles = new Role();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['idRole'])) {
    // Récupérer les données du formulaire
    $idRole = $_POST['idRole'];
    $nomRole = trim($_POST['nomRole']);

    // Vérifier si les champs requis sont remplis
    if (empty($idRole) || empty($nomRole)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/roles';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour le nom du role
    if ($roles->checkDuplicateRole($nomRole)) {
        // Message d'erreur pour le doublon
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un rôle avec ce nom existe déjà.'
            }).then(() => {
                window.location.href = '../configuration/roles';
            });
        </script>";
    } else {
        // Appeler la méthode updateRole pour mettre à jour le role
        $success = $roles->updateRole($idRole, $nomRole);

        if ($success) {
            // Message de succès
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Rôle mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/roles';
                });
            </script>";
        } else {
            // Message d'erreur en cas d'échec
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur s\'est produite lors de la mise à jour du rôle.'
                }).then(() => {
                    window.location.href = '../configuration/roles';
                });
            </script>";
        }
    }
} else {
    // Redirection en cas d'accès direct au fichier sans soumission du formulaire
    header("Location: ../configuration/roles");
    exit();
}
