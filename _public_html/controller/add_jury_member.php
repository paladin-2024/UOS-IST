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
$memberId = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
$fonction = isset($_POST['fonction']) ? $_POST['fonction'] : '';

if (!$juryId || !$memberId) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$universite = new Universite();
$result = $universite->addJuryMember($juryId, $memberId, $fonction);

echo json_encode(['success' => $result]);
?>
