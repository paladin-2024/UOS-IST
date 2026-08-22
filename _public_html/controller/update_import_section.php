<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/GrilleAncienne.php';

session_start();

// Vérification d'authentification et des droits admin
if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

header('Content-Type: application/json');

try {
    // Vérifier les paramètres requis
    if (!isset($_POST['import_id']) || !isset($_POST['section_id'])) {
        throw new Exception('Paramètres manquants');
    }
    
    $importId = intval($_POST['import_id']);
    $sectionId = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
    
    // Créer une instance du modèle
    $grilleAncienne = new GrilleAncienne();
    
    // Mettre à jour la section
    $result = $grilleAncienne->updateImportSection($importId, $sectionId);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Section mise à jour avec succès'
        ]);
    } else {
        throw new Exception('Erreur lors de la mise à jour de la section');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>