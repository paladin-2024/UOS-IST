<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Récupérer les données du formulaire
        $numero_compte = trim($_POST['numero_compte']);
        $intitule_compte = trim($_POST['intitule_compte']);
        $classe_compte = intval($_POST['classe_compte']);
        $compte_parent = !empty($_POST['compte_parent']) ? intval($_POST['compte_parent']) : null;
        $type_compte = trim($_POST['type_compte']);
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if (empty($numero_compte) || empty($intitule_compte) || $classe_compte <= 0 || empty($type_compte)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier si le numéro de compte existe déjà
        $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = :numero_compte");
        $stmt->bindParam(':numero_compte', $numero_compte);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Ce numéro de compte existe déjà.");
        }
        
        // Insérer le nouveau compte
        $stmt = $db->prepare("INSERT INTO compte_comptable (numero_compte, intitule_compte, classe_compte, compte_parent, type_compte, id_user_creation, date_creation) 
                             VALUES (:numero_compte, :intitule_compte, :classe_compte, :compte_parent, :type_compte, :id_user, NOW())");
        
        $stmt->bindParam(':numero_compte', $numero_compte);
        $stmt->bindParam(':intitule_compte', $intitule_compte);
        $stmt->bindParam(':classe_compte', $classe_compte, PDO::PARAM_INT);
        $stmt->bindParam(':compte_parent', $compte_parent, PDO::PARAM_INT);
        $stmt->bindParam(':type_compte', $type_compte);
        $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Rediriger avec un message de succès
        $_SESSION['success_message'] = "Compte comptable créé avec succès.";
        header('Location: ../comptabilite/compte.list');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../comptabilite/compte.list');
        exit();
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../index');
    exit();
}
