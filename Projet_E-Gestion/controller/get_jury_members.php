<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if (!isset($_GET['jury_id'])) {
    echo json_encode(['error' => 'ID du jury non spécifié']);
    exit;
}

$juryId = intval($_GET['jury_id']);
$universite = new Universite();
$members = $universite->getJuryMembers($juryId);

echo json_encode($members);
?>
