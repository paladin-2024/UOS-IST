<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$universite = new Universite();
$currentYear = $universite->getCurrentAcademicYear();


if ($currentYear) {
    echo json_encode([
        'success' => true,
        'anneeAcad' => $currentYear
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "Impossible de déterminer l'année académique en cours"
    ]);
}
?>