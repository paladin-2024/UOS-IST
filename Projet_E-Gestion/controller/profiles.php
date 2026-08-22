<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/User.php';

// Créer une instance de la classe User
$user = new User();

// Fonction pour afficher les alertes SweetAlert2
function showAlert($success, $message) {
    echo "<script>
        Swal.fire({
            icon: '" . ($success ? 'success' : 'error') . "',
            title: '" . ($success ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../accueil';
        });
    </script>";
}

// Modification du login
if (isset($_POST['modifLog'])) {
    $result = $user->updateLogin(
        $_POST['login'] ?? "",
        $_POST['newlogin'] ?? "",
        $_POST['renewlogin'] ?? "",
        $_SESSION['id']
    );
    
    showAlert($result['success'], $result['message']);
}

// Modification du nom
if (isset($_POST['modifNom'])) {
    $result = $user->updateName(
        $_POST['newNom'],
        $_SESSION['id']
    );
    
    showAlert($result['success'], $result['message']);
}

// Modification du mot de passe
if (isset($_POST['modifPass'])) {
    $currentPassword = $_POST['password'] ?? "";
    $newPassword = $_POST['newpassword'] ?? "";
    $confirmPassword = $_POST['renewpassword'] ?? "";
    
    if (strlen($newPassword) < 8) {
        showAlert(false, "Le nouveau mot de passe doit contenir au moins 8 caractères");
        exit;
    }
    
    $result = $user->updatePassword(
        $currentPassword,
        $newPassword,
        $confirmPassword,
        $_SESSION['id'],
        $_SESSION['pw']
    );
    
    showAlert($result['success'], $result['message']);
}

// Modification de l'image de profil
if (isset($_POST['modifImage'])) {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === 4) {
        showAlert(false, "Aucune image n'a été sélectionnée");
        exit;
    }
    
    $result = $user->updateProfileImage(
        $_FILES['photo'],
        $_SESSION['id']
    );
    
    showAlert($result['success'], $result['message']);
}
?>