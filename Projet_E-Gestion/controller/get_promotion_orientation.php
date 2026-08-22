<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if (!isset($_GET['promotionId']) || empty($_GET['promotionId'])) {
    echo json_encode(['orientationId' => null]);
    exit;
}

$promotionId = intval($_GET['promotionId']);
$universite = new Universite();

// Récupérer l'orientation de cette promotion
$orientation = $universite->getPromotionOrientation($promotionId);

echo json_encode(['orientationId' => $orientation]);
exit;
?>
