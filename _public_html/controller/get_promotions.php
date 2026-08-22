<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérification que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Vérification des paramètres
if (!isset($_GET['section']) || !isset($_GET['annee'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$sectionId = intval($_GET['section']);
$anneeId = intval($_GET['annee']);

if ($sectionId <= 0 || $anneeId <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$universite = new Universite();

try {
    $promotions = $universite->getPromotionsBySection($sectionId, $anneeId);
    
    header('Content-Type: application/json');
    echo json_encode($promotions);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>
