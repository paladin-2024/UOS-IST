<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier si les paramètres nécessaires sont présents
if (!isset($_POST['id_compte_bancaire']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit();
}

$id_compte_bancaire = intval($_POST['id_compte_bancaire']);
$status = intval($_POST['status']) ? 1 : 0;

try {
    // Initialiser la connexion
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier si le compte existe
    $stmt = $db->prepare("SELECT id, intitule_compte, est_actif FROM comptes_bancaires WHERE id = :id");
    $stmt->bindParam(':id', $id_compte_bancaire, PDO::PARAM_INT);
    $stmt->execute();
    
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compte) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Compte bancaire non trouvé']);
        exit();
    }
    
        // Si le statut est déjà celui demandé
        if ($compte['est_actif'] == $status) {
            $message = $status ? 'Ce compte est déjà actif' : 'Ce compte est déjà inactif';
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit();
        }
        
        // Mettre à jour le statut du compte
        $stmt = $db->prepare("UPDATE comptes_bancaires SET est_actif = :status WHERE id = :id");
        $stmt->bindParam(':status', $status, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id_compte_bancaire, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        if (!$result) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du compte']);
            exit();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO journal_activites 
            (user_type, user_id, type_activite, id_element, description, date_activite) 
            VALUES 
            ('admin', :user_id, 'compte_bancaire', :id_element, :description, NOW())");
        
        $actionType = $status ? "Activation" : "Désactivation";
        $description = "$actionType du compte bancaire #{$compte['id']} ({$compte['intitule_compte']})";
        
        $logStmt->bindParam(':user_id', $_SESSION['id'], PDO::PARAM_INT);
        $logStmt->bindParam(':id_element', $id_compte_bancaire, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Retourner un message de succès
        $message = $status ? 'Le compte bancaire a été activé avec succès' : 'Le compte bancaire a été désactivé avec succès';
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        exit();
    }
    