<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';
require_once dirname(__DIR__) . '/models/User.php';

try {
    // Validation du paramètre id
    if (!isset($_GET['id'])) {
        throw new Exception('Le paramètre id est requis');
    }
    
    $sujetId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($sujetId === false || $sujetId <= 0) {
        throw new Exception('ID de sujet invalide');
    }

    // Initialiser le modèle
    $enseignantModel = new Enseignant();
    $userModel = new User();
    
    // Récupérer l'historique du sujet
    $history = $enseignantModel->getSujetValidationHistory($sujetId);
    
    // Si aucun historique n'est trouvé, retourner un tableau vide
    if (empty($history)) {
        echo json_encode(['history' => []]);
        exit;
    }
    
    // Enrichir les données d'historique avec des informations supplémentaires
    $formattedHistory = [];
    foreach ($history as $entry) {
        // Récupérer les informations de l'utilisateur qui a fait l'action
        $userData = $userModel->getUserById($entry['idUser']);
        $userName = $userData ? $userData['nomUser'] : 'Utilisateur inconnu';
        
        $formattedHistory[] = [
            'status' => $entry['status'],
            'date' => $entry['date_action'],
            'comment' => $entry['commentaire'],
            'user' => $userName
        ];
    }
    
    // Renvoyer les données structurées
    echo json_encode([
        'history' => $formattedHistory
    ]);
    
} catch (Exception $e) {
    // Logger l'erreur
    error_log("Erreur dans get_sujet_history.php: " . $e->getMessage());
    
    // Retourner un message d'erreur structuré
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
