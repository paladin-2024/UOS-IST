<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

$promotionId = $_GET['promotion'] ?? null;

if (!$promotionId) {
    echo json_encode(['success' => false, 'message' => 'ID de promotion non spécifié']);
    exit;
}

try {
    $universite = new Universite();
    $semestres = $universite->getSemestresByPromotion($promotionId);
    echo json_encode($semestres);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
