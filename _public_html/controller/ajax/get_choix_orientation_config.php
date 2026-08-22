<?php
session_start();
require_once '../../config/Connexion.php';
require_once '../../models/Universite.php';

header('Content-Type: application/json');

try {
    if (empty($_SESSION['id'])) {
        throw new Exception('Non authentifié');
    }

    $promotionId = intval($_GET['promotion_id'] ?? 0);
    $anneeId = intval($_GET['annee_id'] ?? 0);

    if ($promotionId <= 0 || $anneeId <= 0) {
        throw new Exception('Paramètres invalides');
    }

    $universite = new Universite();
    
    // Get existing configurations
    $configurations = $universite->getConfigurationChoixOrientation($promotionId, $anneeId);
    
    // Get the IDs of selected promotions and fees
    $selectedPromotions = [];
    $selectedFees = [];
    
    foreach ($configurations as $config) {
        $selectedPromotions[] = $config['promotion_cible_id'];
        
        // Get fees for this configuration
        $frais = $universite->getFraisRequisChoixOrientation($config['id']);
        foreach ($frais as $fee) {
            if (!in_array($fee['id'], $selectedFees)) {
                $selectedFees[] = $fee['id'];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'configurations' => $configurations,
        'selected_promotions' => $selectedPromotions,
        'selected_fees' => $selectedFees
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log('get_choix_orientation_config.php: ' . $e->getMessage());
}
