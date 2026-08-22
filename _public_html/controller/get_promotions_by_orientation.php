<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if (!isset($_GET['orientationId']) || empty($_GET['orientationId'])) {
    echo json_encode([]);
    exit;
}

$orientationId = intval($_GET['orientationId']);
$universite = new Universite();

// Récupérer les promotions pour cette orientation
$promotions = $universite->getPromotionsByOrientation($orientationId);

echo json_encode($promotions);
exit;
?>
