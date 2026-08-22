<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$universite = new Universite();

// Récupérer l'ID du bureau de jury
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;

if (!$bureauId) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Récupérer les promotions associées au jury
$promotions = $universite->getPromotionsByJury($bureauId);

// Envoyer la réponse
header('Content-Type: application/json');
echo json_encode($promotions);
