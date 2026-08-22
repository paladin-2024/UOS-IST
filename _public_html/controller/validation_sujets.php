<?php
// Démarrer la session pour accéder aux variables de session
session_start();

// Inclure le fichier de connexion à la base de données
require_once dirname(__DIR__) . "/config/Connexion.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php?error=not_connected');
    exit;
}

// Vérifier si la requête est une méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?view=recherche/choix_etudiant&error=invalid_request');
    exit;
}

// Récupérer l'action à effectuer
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Vérifier que l'ID du sujet est fourni
if (!isset($_POST['sujet_id']) || empty($_POST['sujet_id']) || !is_numeric($_POST['sujet_id'])) {
    header('Location: ../index.php?view=recherche/choix_etudiant&error=invalid_subject');
    exit;
}

$sujetId = intval($_POST['sujet_id']);
$userId = $_SESSION['id'];
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

try {
    // Établir la connexion à la base de données
    $pdo = Connexion::getInstance()->getPDO();
    
    // Vérifier que le sujet existe
    $queryCheck = "SELECT * FROM sujets WHERE idsujets = ?";
    $stmtCheck = $pdo->prepare($queryCheck);
    $stmtCheck->execute([$sujetId]);
    $sujet = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$sujet) {
        header('Location: ../index.php?view=recherche/choix_etudiant&error=subject_not_found');
        exit;
    }
    
    // Traiter l'action demandée
    switch ($action) {
        case 'validate':
            // Mettre à jour le statut du sujet à "Validé"
            $query = "UPDATE sujets SET 
                      statut_validation = 'Validé',
                      commentaire_commission = ?,
                      date_validation = NOW(),
                      idValidateur = ?
                      WHERE idsujets = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$commentaire, $userId, $sujetId]);
            
            // Enregistrer l'action dans l'historique des validations
            $queryHistory = "INSERT INTO sujet_validation_history 
                           (idsujets, status, date_action, commentaire, idUser) 
                           VALUES (?, 'Validé', NOW(), ?, ?)";
            $stmtHistory = $pdo->prepare($queryHistory);
            $stmtHistory->execute([$sujetId, $commentaire, $userId]);
            
            // Rediriger avec un message de succès
            header('Location: ../index.php?view=recherche/choix_etudiant&success=subject_validated');
            break;
            
        case 'reject':
            // Vérifier que le commentaire est fourni pour un rejet
            if (empty($commentaire)) {
                header('Location: ../index.php?view=recherche/choix_etudiant&error=comment_required');
                exit;
            }
            
            // Mettre à jour le statut du sujet à "Rejeté"
            $query = "UPDATE sujets SET 
                      statut_validation = 'A reformulé',
                      commentaire_commission = ?,
                      date_validation = NOW(),
                      idValidateur = ?
                      WHERE idsujets = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$commentaire, $userId, $sujetId]);
            
            // Enregistrer l'action dans l'historique des validations
            $queryHistory = "INSERT INTO sujet_validation_history 
                           (idsujets, status, date_action, commentaire, idUser) 
                           VALUES (?, 'A reformulé', NOW(), ?, ?)";
            $stmtHistory = $pdo->prepare($queryHistory);
            $stmtHistory->execute([$sujetId, $commentaire, $userId]);
            
            // Rediriger avec un message de succès
            header('Location: ../index.php?view=recherche/choix_etudiant&success=subject_rejected');
            break;
            
        default:
            // Action non reconnue
            header('Location: ../index.php?view=recherche/choix_etudiant&error=invalid_action');
            break;
    }
} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    header('Location: ../index.php?view=recherche/choix_etudiant&error=database_error&message=' . urlencode($e->getMessage()));
}
?>