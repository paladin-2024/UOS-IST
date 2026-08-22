<?php
header('Content-Type: application/json');

try {
    // Test simple sans PhpSpreadsheet
    if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Fichier non reçu. Code erreur: ' . ($_FILES['fichier']['error'] ?? 'inconnu'));
    }
    
    $fichier = $_FILES['fichier'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Test réussi - Fichier reçu',
        'info' => [
            'nom' => $fichier['name'],
            'taille' => $fichier['size'],
            'type' => $fichier['type'],
            'tmp_name' => $fichier['tmp_name']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
