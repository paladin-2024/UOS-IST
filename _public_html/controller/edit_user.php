<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/User.php';

// Créer une instance de la classe User en passant la connexion
$user = new User();
 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $id = $_POST['idUser'];
    $nomUser = $_POST['nomUser'];
    $loginUser = $_POST['loginUser'];
    $idRole = $_POST['idRole'];
    $etatUser = isset($_POST['etatUser']) ? $_POST['etatUser'] : 1; // Par défaut à 1 si non défini
    $dernier_connexion = date('Y-m-d H:i:s'); // Date de dernière connexion actuelle

    // Traiter le mot de passe (ne le mettre à jour que s'il est fourni)
    $password = !empty($_POST['pw']) ? password_hash($_POST['pw'], PASSWORD_BCRYPT) : null;

    // Traiter le fichier image (ne le passer que s'il est fourni)
    $imageFile = isset($_FILES['imageUser']) && !empty($_FILES['imageUser']['name']) ? $_FILES['imageUser'] : null;

    // Appeler la méthode updateUser de la classe User
    $success = $user->updateUser($id, $idRole, $nomUser, $loginUser, $password ?? null, $imageFile, $etatUser, $dernier_connexion);

    if ($success) {
        // Redirection après mise à jour avec succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Utilisateur mis à jour avec succès'
            }).then(() => {
                window.location.href = '../configuration/users';
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
                window.location.href = '../configuration/users';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/users");
    exit();
}
