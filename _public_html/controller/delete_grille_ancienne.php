<?php
session_start();
header('Content-Type: application/json');

// Vérifier les droits d'accès
if (!isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

try {
    if (!isset($_GET['import_id']) || !is_numeric($_GET['import_id'])) {
        throw new Exception('ID d\'import non fourni ou invalide.');
    }

    require_once '../models/GrilleAncienne.php';
    $grilleAncienne = new GrilleAncienne();
    
    $importId = intval($_GET['import_id']);
    
    // Vérifier que l'import existe
    $import = $grilleAncienne->getImportById($importId);
    if (!$import) {
        throw new Exception('Import non trouvé.');
    }

    // Supprimer l'import (les contraintes de clés étrangères supprimeront automatiquement les données liées)
    $success = $grilleAncienne->deleteImport($importId);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'La grille ancienne a été supprimée avec succès.'
        ]);
    } else {
        throw new Exception('Erreur lors de la suppression de la grille.');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
