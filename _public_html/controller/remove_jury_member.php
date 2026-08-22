<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$memberId = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;

if (!$memberId) {
    echo json_encode(['success' => false, 'message' => 'ID du membre non spécifié']);
    exit;
}

$universite = new Universite();
$result = $universite->removeJuryMember($memberId);

echo json_encode(['success' => $result]);
?>
