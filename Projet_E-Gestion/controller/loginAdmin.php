<?php

session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SuperUser.php';
require_once dirname(__DIR__) . '/utils/Security.php';

if (!isset($_POST['csrf_token']) || !AppSecurity::verifyCsrfToken($_POST['csrf_token'])) {
    echo "<script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js\"></script>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Token de sécurité invalide'
        }).then(() => {
            window.location.href = '../accueil';
        });
    </script>";
    exit;
}

if (isset($_POST['con'])) {
    $email = $_POST['login'];
    $password = $_POST['pwd'];

    // Créez une instance de SuperUser
    $userManager = new SuperUser();

    // Vérifiez les identifiants
    $user = $userManager->seConnecter($email, $password);

    // Vérifiez si l'utilisateur est trouvé et s'il est actif
    if ($user && $user->etatUser == 1) {
        // Initialisez les variables de session avec les informations de l'utilisateur
        $_SESSION['id'] = $user->idUser;
        $_SESSION['idRole'] = $user->idRole;
        $_SESSION['nom'] = $user->nomUser;
        $_SESSION['role'] = $user->nomRole;
        $_SESSION['image'] = $user->imageUser;
        $_SESSION['login'] = $user->loginUser;
        $_SESSION['pw'] = $user->pw;

        $userManager->logUserActivity(
            $user->idUser, // User ID
            'login', // Action type
            'Connexion réussie avec succès', // Description
            $userManager->getUserIpAddress(), // IP address
            $userManager->getUserAgent() // User agent
        );

        // Redirection de l'utilisateur
        header("Location: ../index");
        exit;
    } else {
        // En cas d'erreur ou si l'utilisateur est inactif, affichez un message d'alerte avec SweetAlert
        echo "<script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js\"></script>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Login ou mot de passe incorrect ou compte inactif'
            }).then(() => {
                window.location.href = '../accueil';
            });
        </script>";
        exit;
    }
}else{
    echo "Erreur";
}
