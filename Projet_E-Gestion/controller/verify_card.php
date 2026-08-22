<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SecurityUtils.php';

// Récupérer et décoder les données JSON
try {
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($inputData['type']) || !isset($inputData['data'])) {
        throw new Exception("Données incomplètes");
    }
    
    $type = $inputData['type'];
    $data = $inputData['data'];
    
    // Instancier l'utilitaire de sécurité
    $securityUtils = new SecurityUtils();
    
    // Informations sur le vérificateur
    $verifierInfo = [
        'user_id' => $_SESSION['id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'location' => null // À implémenter si besoin de géolocalisation
    ];
    
    // Vérifier selon le type de données
    if ($type === 'qr_data') {
        // Vérification directe des données de carte depuis le QR code
        $result = $securityUtils->verifyCard($data, $verifierInfo);
    } elseif ($type === 'card_id') {
        // Vérification par ID de carte (récupérer d'abord les données de la carte)
        $cardData = $securityUtils->getCardDataById($data);
        
        if (!$cardData) {
            $result = [
                'valid' => false,
                'message' => 'Carte inconnue ou non trouvée.',
                'cardId' => $data
            ];
        } else {
            $result = $securityUtils->verifyCard($cardData, $verifierInfo);
        }
    } else {
        throw new Exception("Type de vérification non supporté");
    }
    
    // Réponse
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'valid' => false,
        'message' => 'Erreur de vérification: ' . $e->getMessage()
    ]);
}
