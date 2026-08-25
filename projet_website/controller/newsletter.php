<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer l'email du formulaire
        if (isset($_POST['email'])) {
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            
            // Valider l'email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Veuillez fournir une adresse email valide");
            }
            
            // Connexion à la base de données
            $db = Connexion::getInstance()->getPDO();
            
            // Vérifier si la table newsletter_subscribers existe, sinon la créer
            $checkTable = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'newsletter_subscribers'");
            if ($checkTable->rowCount() === 0) {
                $createTable = "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
                    id SERIAL PRIMARY KEY,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'unsubscribed')),
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $db->exec($createTable);
            }
            
            // Vérifier si l'email existe déjà
            $stmt = $db->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            if ($subscriber) {
                // L'email existe déjà, mettre à jour si désabonné
                if ($subscriber['status'] === 'unsubscribed') {
                    $updateStmt = $db->prepare("UPDATE newsletter_subscribers SET status = 'active', ip_address = :ip_address, updated_at = NOW() WHERE id = :id");
                    $updateStmt->bindParam(':ip_address', $ip_address);
                    $updateStmt->bindParam(':id', $subscriber['id']);
                    $updateStmt->execute();
                    
                    $_SESSION['success_message'] = "Vous êtes de nouveau abonné à notre newsletter !";
                } else {
                    $_SESSION['info_message'] = "Vous êtes déjà abonné à notre newsletter.";
                }
            } else {
                // Nouvel abonnement
                $insertStmt = $db->prepare("INSERT INTO newsletter_subscribers (email, ip_address) VALUES (:email, :ip_address)");
                $insertStmt->bindParam(':email', $email);
                $insertStmt->bindParam(':ip_address', $ip_address);
                $insertStmt->execute();
                
                $_SESSION['success_message'] = "Merci de vous être abonné à notre newsletter !";
            }
            
            // Redirection vers la page précédente ou l'accueil
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';
            header('Location: ' . $referer);
            exit;
            
        } else {
            throw new Exception("Veuillez fournir une adresse email");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        
        // Redirection vers la page précédente ou l'accueil
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';
        header('Location: ' . $referer);
        exit;
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../accueil');
    exit;
}