<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/User.php';

// Créer une instance de la classe User
$user = new User();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $nomUser = $_POST['nomUser'];
    $loginUser = $_POST['loginUser'];
    $pw = password_hash($_POST['pw'], PASSWORD_DEFAULT);
    $idRole = $_POST['idRole'];
    $etatUser = isset($_POST['etatUser']) ? $_POST['etatUser'] : 1;
    $dernier_connexion = date('Y-m-d H:i:s');

    // Vérifier et traiter le fichier image
    $imageFile = isset($_FILES['imageUser']) ? $_FILES['imageUser'] : null;

    // Vérifier les doublons pour le nom et le login
    if ($user->checkDuplicateUser($nomUser, $loginUser, $idRole)) {
        // Message d'erreur pour le doublon
        echo "<!DOCTYPE html><body><script src=\"../assets/js/sweetalert.min.js\"></script><script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un utilisateur avec ce nom ou ce login existe déjà.'
            }).then(() => {
                window.location.href = '../configuration/users';
            });
        </script>";
    } else {
        // Appeler la fonction addUser si aucun doublon n'est trouvé
        if ($user->addUser($idRole, $nomUser, $loginUser, $pw, $imageFile, $etatUser, $dernier_connexion)) {
            // Redirection avec succès et message Swal
            echo "<!DOCTYPE html><body><script src=\"../assets/js/sweetalert.min.js\"></script><script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur ajouté avec succès'
                }).then(() => {
                    window.location.href = '../configuration/users';
                });
            </script>";
        } else {
            // Message d'erreur avec Swal
            echo "<!DOCTYPE html><body><script src=\"../assets/js/sweetalert.min.js\"></script><script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de l\'ajout de l\'utilisateur.'
                }).then(() => {
                    window.location.href = '../configuration/users';
                });
            </script>";
        }
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/users");
    exit();
}
