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
        $id_unite = intval($_POST['id_unite']);
        $code_unite = trim($_POST['code_unite']);
        $libelle_unite = trim($_POST['libelle_unite']);
        $symbole_unite = trim($_POST['symbole_unite']);
        
        // Validation des données
        if ($id_unite <= 0 || empty($code_unite) || empty($libelle_unite)) {
            throw new Exception("Le code et le libellé sont obligatoires.");
        }
        
        // Vérifier si l'unité existe
        $stmt = $db->prepare("SELECT id_unite FROM unite_mesure WHERE id_unite = :id_unite");
        $stmt->bindParam(':id_unite', $id_unite, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            throw new Exception("Cette unité de mesure n'existe pas.");
        }
        
        // Vérifier si le code existe déjà pour une autre unité
        $stmt = $db->prepare("SELECT id_unite FROM unite_mesure WHERE code_unite = :code_unite AND id_unite != :id_unite");
        $stmt->bindParam(':code_unite', $code_unite);
        $stmt->bindParam(':id_unite', $id_unite, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Ce code d'unité existe déjà pour une autre unité.");
        }
        
        // Mettre à jour l'unité
        $stmt = $db->prepare("UPDATE unite_mesure 
                              SET code_unite = :code_unite, 
                                  libelle_unite = :libelle_unite, 
                                  symbole_unite = :symbole_unite 
                              WHERE id_unite = :id_unite");
        
        $stmt->bindParam(':code_unite', $code_unite);
        $stmt->bindParam(':libelle_unite', $libelle_unite);
        $stmt->bindParam(':symbole_unite', $symbole_unite);
        $stmt->bindParam(':id_unite', $id_unite, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Rediriger avec un message de succès
        $_SESSION['success_message'] = "Unité de mesure mise à jour avec succès.";
        header('Location: ../configuration/unite.list');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../configuration/unite.list');
        exit();
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../index');
    exit();
}
