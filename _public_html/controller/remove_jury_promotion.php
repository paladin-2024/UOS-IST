<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$associationId = isset($_POST['association_id']) ? intval($_POST['association_id']) : 0;

if (!$associationId) {
    echo json_encode(['success' => false, 'message' => 'ID de l\'association non spécifié']);
    exit;
}

$universite = new Universite();
$result = $universite->removePromotionFromJury($associationId);

echo json_encode(['success' => $result]);
?>
