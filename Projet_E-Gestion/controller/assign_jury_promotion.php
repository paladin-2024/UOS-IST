<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$juryId = isset($_POST['jury_id']) ? intval($_POST['jury_id']) : 0;
$promotionId = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
$userId = $_SESSION['id'] ?? 0;

if (!$juryId || !$promotionId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$universite = new Universite();
$result = $universite->assignPromotionToJury($juryId, $promotionId, $userId);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Cette promotion est déjà assignée à ce jury ou une erreur est survenue']);
}
?>
