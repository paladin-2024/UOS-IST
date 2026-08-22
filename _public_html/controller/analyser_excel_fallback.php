<?php
header('Content-Type: application/json');

try {
    // Vérifier l'upload du fichier
    if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'Aucun fichier reçu ou erreur d\'upload.';
        if (isset($_FILES['fichier']['error'])) {
            switch ($_FILES['fichier']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error_message = 'Le fichier est trop volumineux (limite PHP ini).';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = 'Le fichier est trop volumineux (limite formulaire).';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message = 'Upload partiel du fichier.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = 'Aucun fichier uploadé.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message = 'Dossier temporaire manquant.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message = 'Échec de l\'écriture sur le disque.';
                    break;
            }
        }
        throw new Exception($error_message);
    }
    
    $fichier = $_FILES['fichier'];
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ['xlsx', 'xls'])) {
        throw new Exception('Format de fichier non supporté. Utilisez .xlsx ou .xls');
    }
    
    // Vérifier la taille du fichier
    if ($fichier['size'] > 50 * 1024 * 1024) { // 50MB
        throw new Exception('Le fichier est trop volumineux (max 50MB).');
    }
    
    // Pour le moment, simuler des données d'exemple
    // En attendant que PhpSpreadsheet soit correctement configuré
    $donnees_exemple = [
        ['Matricule', 'Nom', 'Mathématiques', 'Physique', 'Informatique'],
        ['20IT001', 'ALAIN Paul', '15', '12', '18'],
        ['20IT002', 'BERNARD Marie', '14', '16', '13'],
        ['20IT003', 'CHARLES Jean', '11', '14', '16'],
        ['20IT004', 'DIANE Sophie', '17', '15', '19']
    ];
    
    $suggestions = [
        'matricule_detecte' => ['row' => 0, 'col' => 0],
        'nom_detecte' => ['row' => 0, 'col' => 1],
        'ue_possibles' => [
            ['row' => 0, 'col' => 2, 'nom' => 'Mathématiques'],
            ['row' => 0, 'col' => 3, 'nom' => 'Physique'],
            ['row' => 0, 'col' => 4, 'nom' => 'Informatique']
        ],
        'debut_donnees_suggere' => ['row' => 1, 'col' => 0]
    ];
    
    // Créer le dossier temp s'il n'existe pas
    $tempDir = dirname(__DIR__) . '/temp';
    if (!is_dir($tempDir)) {
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception('Impossible de créer le dossier temporaire');
        }
    }
    
    // Sauvegarder les données d'exemple
    $tempId = uniqid('excel_fallback_', true);
    $tempFile = $tempDir . '/excel_fallback_' . $tempId . '.json';
    
    $dataToStore = [
        'donnees_preview' => $donnees_exemple,
        'donnees_completes' => $donnees_exemple,
        'fichier_original' => $fichier['name'],
        'lignes_totales' => count($donnees_exemple),
        'colonnes_totales' => count($donnees_exemple[0]),
        'suggestions' => $suggestions,
        'timestamp' => time(),
        'mode' => 'fallback'
    ];
    
    if (file_put_contents($tempFile, json_encode($dataToStore, JSON_UNESCAPED_UNICODE)) === false) {
        throw new Exception('Erreur lors de la sauvegarde temporaire');
    }
    
    echo json_encode([
        'success' => true,
        'donnees' => $donnees_exemple,
        'lignes' => count($donnees_exemple),
        'colonnes' => count($donnees_exemple[0]),
        'suggestions' => $suggestions,
        'tempId' => $tempId,
        'message' => 'Fichier analysé avec succès (mode exemple)',
        'note' => 'Mode de démonstration - PhpSpreadsheet requis pour la lecture réelle'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur analyse Excel fallback: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'analyse: ' . $e->getMessage()
    ]);
}
?>
