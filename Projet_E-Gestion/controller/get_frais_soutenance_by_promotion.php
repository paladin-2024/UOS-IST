<?php
// Configuration des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérification des paramètres
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

if ($promotionId <= 0 || $anneeId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

// Récupération des frais de soutenance pour la promotion
$fraisModel = new Frais();
$fraisSoutenance = $fraisModel->getFraisSoutenanceByPromotion($promotionId, $anneeId);

// Retour des frais au format JSON
header('Content-Type: application/json');
echo json_encode($fraisSoutenance);
exit;
