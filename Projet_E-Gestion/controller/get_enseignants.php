<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';

header('Content-Type: application/json');

try {
    $enseignantModel = new Enseignant();
    $enseignants = $enseignantModel->getAllEnseignants();

    echo json_encode($enseignants);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
