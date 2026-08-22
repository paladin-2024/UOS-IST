<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$universite = new Universite();

if (isset($_GET['ids'])) {
    $ids = json_decode($_GET['ids'], true);
    
    if (is_array($ids)) {
        $ues = $universite->getUEsByIds($ids);
        header('Content-Type: application/json');
        echo json_encode($ues);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Format invalide']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètre manquant']);
}
?>
