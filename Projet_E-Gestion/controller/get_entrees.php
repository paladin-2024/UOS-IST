<?php
require_once '../config/Connexion.php';
require_once '../models/Structure.php';

session_start();
if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

if (!isset($_GET['depotId']) || !is_numeric($_GET['depotId'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID du dépôt non valide']);
    exit;
}

try {
    $structure = new Structure();
    $depotId = intval($_GET['depotId']);
    
    $entrees = $structure->getEntreesByDepot($depotId);

    // Formater les dates pour l'affichage
    foreach ($entrees as &$entree) {
        if (isset($entree['dateOperation'])) {
            $date = new DateTime($entree['dateOperation']);
            $entree['dateOperation'] = $date->format('d/m/Y');
        }
        
        // Assurer que les valeurs null sont converties en chaînes vides
        $entree['reference_document'] = $entree['reference_document'] ?? '';
        $entree['transporteur'] = $entree['transporteur'] ?? '';
        $entree['fournisseur'] = $entree['fournisseur'] ?? 'Non spécifié';
    }

    header('Content-Type: application/json');
    echo json_encode($entrees);

} catch (Exception $e) {
    error_log($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Une erreur est survenue lors de la récupération des entrées']);
}