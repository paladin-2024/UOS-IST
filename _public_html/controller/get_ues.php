<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

$semestreId = $_GET['semestre'] ?? null;

if (!$semestreId) {
    echo json_encode(['success' => false, 'message' => 'ID de semestre non spécifié']);
    exit;
}

try {
    $universite = new Universite();
    $ues = $universite->getUEsBySemestre($semestreId);
    echo json_encode($ues);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
