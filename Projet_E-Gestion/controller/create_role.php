<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Role.php';

// Créer une instance de la classe Roles
$roles = new Role();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $nomRole = isset($_POST['nomRole']) ? trim($_POST['nomRole']) : '';

    // Validation du champ nomRole
    if (empty($nomRole)) {
        // Message d'erreur si le nom est vide
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le nom du module est requis.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour le nom du module
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
        exit();
    }

    // Appeler la fonction addModule si aucun doublon n'est trouvé
    if ($roles->addRole($nomRole)) {
        // Redirection avec succès et message Swal
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Rôle ajouté avec succès.'
            }).then(() => {
                window.location.href = '../configuration/roles';
            });
        </script>";
    } else {
        // Message d'erreur avec Swal
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du rôle.'
            }).then(() => {
                window.location.href = '../configuration/roles';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/roles");
    exit();
}
