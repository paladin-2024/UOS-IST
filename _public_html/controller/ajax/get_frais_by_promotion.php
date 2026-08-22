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

    if ($promotionId <= 0) {
        throw new Exception('Promotion invalide');
    }

    $universite = new Universite();
    $frais = $universite->getFeesForPromotion($promotionId);
    
    echo json_encode([
        'success' => true,
        'frais' => $frais
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log('get_frais_by_promotion.php: ' . $e->getMessage());
}
