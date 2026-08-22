<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';

// Vérification que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Récupération des paramètres
$promotionId = isset($_GET['promotionId']) ? intval($_GET['promotionId']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'academique';
$anneeAcadId = isset($_GET['anneeAcadId']) ? intval($_GET['anneeAcadId']) : 0;

if ($promotionId <= 0 || $anneeAcadId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit();
}

$fraisModel = new Frais();

// Récupérer les frais selon le type
$result = [];

if ($type == 'academique') {
    $fraisList = $fraisModel->getFraisByPromotion($promotionId, $anneeAcadId);
    
    foreach ($fraisList as $frais) {
        $result[] = [
            'id' => $frais['idfrais'],
            'designation' => $frais['designation'],
            'montant' => $frais['montant'],
            'devise' => $frais['devise']
        ];
    }
} else {
    // Pour les frais de soutenance, vous devez adapter cette partie selon votre logique d'affaires
    // Normalement les frais de soutenance ne sont pas liés à une promotion spécifique
    $fraisList = $fraisModel->getAllFraisSoutenance($anneeAcadId);
    
    foreach ($fraisList as $frais) {
        $result[] = [
            'id' => $frais['idfrais_soutenance'],
            'designation' => $frais['designation'],
            'montant' => $frais['montant'],
            'devise' => $frais['devise']
        ];
    }
}

// Retourner le résultat au format JSON
header('Content-Type: application/json');
echo json_encode($result);
exit();
