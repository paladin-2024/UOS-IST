<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

$anneeId = $_GET['annee'] ?? null;
$cycle = $_GET['cycle'] ?? null;

if (!$anneeId) {
    echo json_encode(['success' => false, 'message' => 'Année académique non spécifiée']);
    exit;
}

try {
    $universite = new Universite();
    
    // Si le cycle est spécifié, filtrer par cycle également
    if ($cycle) {
        $etudiants = $universite->getEtudiantsByAnneeAcadAndCycle($anneeId, $cycle);
    } else {
        $etudiants = $universite->getEtudiantsByAnneeAcad($anneeId);
    }

    echo json_encode($etudiants);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
