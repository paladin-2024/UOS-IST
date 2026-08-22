<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données du formulaire
        if (isset($_POST['username']) && isset($_POST['password'])) {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            // Valider les données
            if (empty($username) || empty($password)) {
                throw new Exception("Veuillez remplir tous les champs");
            }
            
            // Connexion à la base de données
            $db = Connexion::getInstance()->getPDO();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $user['password'])) {
                    // Connexion réussie
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $user['id'];
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_fullname'] = $user['full_name'];
                    $_SESSION['admin_role'] = $user['role'];
                    
                    // Gérer l'option "Se souvenir de moi"
                    if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
                        // Pour cet exemple, nous définissons simplement un cookie qui expire dans 30 jours
                        // Dans une application réelle, vous devriez utiliser un token sécurisé stocké en base de données
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 jours
                    }
                    
                    $_SESSION['success_message'] = "Connexion réussie. Bienvenue " . $user['full_name'] . "!";
                    header('Location: ../admin/dashboard');
                    exit;
                } else {
                    throw new Exception("Mot de passe incorrect");
                }
            } else {
                throw new Exception("Nom d'utilisateur non trouvé ou compte désactivé");
            }
        } else {
            throw new Exception("Veuillez remplir tous les champs");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../admin/login');
        exit;
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../index');
    exit;
}