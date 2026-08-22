
<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SuperUser.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../accueil");
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validation des données
    if (empty($newPassword) || empty($confirmPassword)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs sont obligatoires.'
            }).then(() => {
                window.location.href = '../index';
            });
        </script>";
        exit();
    }
    
    // Vérifier que les mots de passe correspondent
    if ($newPassword !== $confirmPassword) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les mots de passe ne correspondent pas.'
            }).then(() => {
                window.location.href = '../index';
            });
        </script>";
        exit();
    }
    
    // Vérifier la complexité du mot de passe
    if (strlen($newPassword) < 8) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le mot de passe doit contenir au moins 8 caractères.'
            }).then(() => {
                window.location.href = '../index';
            });
        </script>";
        exit();
    }
    
    // Mettre à jour le mot de passe
    $superUser = new SuperUser();
    $userId = $_SESSION['id'];
    
    if ($superUser->changePassword($userId, $newPassword)) {
        // Marquer que l'utilisateur a changé son mot de passe
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Mot de passe changé avec succès.'
            }).then(() => {
                window.location.href = '../index';
            });
        </script>";
        $superUser->miseAJourDerniereConnexion($userId);
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur de modification du mot de passe'
            }).then(() => {
                window.location.href = '../index';
            });
        </script>";
    }
    
} else {
    // Si accès direct au script sans passer par le formulaire
    header("Location: ../index");
    exit();
}