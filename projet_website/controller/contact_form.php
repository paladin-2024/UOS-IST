<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données du formulaire
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        
        // Valider les données
        if (empty($name)) {
            throw new Exception("Le nom est obligatoire");
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Veuillez fournir une adresse email valide");
        }
        
        if (empty($message)) {
            throw new Exception("Le message est obligatoire");
        }
        
        // Limiter la longueur des champs pour éviter les dépassements de capacité
        if (strlen($name) > 100) {
            $name = substr($name, 0, 100);
        }
        
        if (strlen($email) > 100) {
            $email = substr($email, 0, 100);
        }
        
        if (strlen($subject) > 255) {
            $subject = substr($subject, 0, 255);
        }
        
        if (strlen($phone) > 50) {
            $phone = substr($phone, 0, 50);
        }
        
        // Récupérer l'adresse IP
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Connexion à la base de données
        $db = Connexion::getInstance()->getPDO();
        
        // Insérer les données dans la base de données
        $query = "INSERT INTO contact_submissions (name, email, subject, message, phone, ip_address) 
                  VALUES (:name, :email, :subject, :message, :phone, :ip_address)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':ip_address', $ip_address);
        
        $stmt->execute();
        
        // Envoyer une copie du message par email (optionnel)
        $send_email = false; // Mettre à true pour activer l'envoi d'email
        
        if ($send_email) {
            // Récupérer l'email de contact depuis les paramètres du site
            $settingsQuery = "SELECT setting_value FROM site_settings WHERE setting_key = 'contact_email'";
            $settingsStmt = $db->prepare($settingsQuery);
            $settingsStmt->execute();
            $contactEmail = $settingsStmt->fetchColumn();
            
            if (!$contactEmail) {
                $contactEmail = 'contact@istmbeni.ac.cd'; // Email par défaut
            }
            
            // Préparer et envoyer l'email
            $emailSubject = "[Contact ISTM BENI] " . $subject;
            $emailBody = "Nouveau message de contact reçu :\n\n";
            $emailBody .= "Nom : " . $name . "\n";
            $emailBody .= "Email : " . $email . "\n";
            if (!empty($phone)) {
                $emailBody .= "Téléphone : " . $phone . "\n";
            }
            $emailBody .= "Sujet : " . $subject . "\n\n";
            $emailBody .= "Message :\n" . $message . "\n\n";
            $emailBody .= "Date : " . date('d/m/Y H:i:s') . "\n";
            $emailBody .= "IP : " . $ip_address;
            
            $headers = "From: " . $email . "\r\n";
            $headers .= "Reply-To: " . $email . "\r\n";
            
            mail($contactEmail, $emailSubject, $emailBody, $headers);
        }
        
        // Message de succès
        $_SESSION['success_message'] = "Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.";
        
        // Redirection vers la page de contact ou la page précédente
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';
        header('Location: ' . $referer);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        
        // Conserver les données du formulaire en cas d'erreur
        $_SESSION['form_data'] = [
            'name' => $name ?? '',
            'email' => $email ?? '',
            'subject' => $subject ?? '',
            'message' => $message ?? '',
            'phone' => $phone ?? ''
        ];
        
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