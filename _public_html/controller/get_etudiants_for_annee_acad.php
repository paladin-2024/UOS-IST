<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

$anneeAcadId = $_GET['anneeAcadId'] ?? null;

if (!$anneeAcadId) {
    echo json_encode(['success' => false, 'message' => 'Invalid academic year ID']);
    exit;
}

try {
    $universite = new Universite();
    $etudiants = $universite->getEtudiantsByAnneeAcad($anneeAcadId);

    echo json_encode($etudiants);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}