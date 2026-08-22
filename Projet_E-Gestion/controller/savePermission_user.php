<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/User.php';

// Créer une instance de la classe User
$userModel = new User();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $idRole = isset($_POST['idRole']) ? intval($_POST['idRole']) : 0;
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    // Appeler la fonction saveUserPermissions
    if ($userModel->saveUserPermissions($idRole, $permissions)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Permissions enregistrées avec succès!'
            }).then(() => {
                window.location.href = '../configuration/userPermissions&r=" . $idRole . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'enregistrement des permissions.'
            }).then(() => {
                window.location.href = '../configuration/userPermissions&r=" . $idRole . "';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/roles");
    exit();
}
?>