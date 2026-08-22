<?php
header('Content-Type: application/json');

try {
    if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Aucun fichier reçu ou erreur d\'upload.');
    }
    
    $fichier = $_FILES['fichier'];
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ['xlsx', 'xls'])) {
        throw new Exception('Format de fichier non supporté. Utilisez .xlsx ou .xls');
    }
    
    require_once '../vendor/autoload.php';
    
    use PhpOffice\PhpSpreadsheet\IOFactory;
    use PhpOffice\PhpSpreadsheet\Shared\Date;
    
    // Lire le fichier Excel
    $reader = IOFactory::createReaderForFile($fichier['tmp_name']);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($fichier['tmp_name']);
    
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    
    // Vérifications de base
    if ($highestRow < 2) {
        throw new Exception('Le fichier doit contenir au moins 2 lignes (en-têtes + données).');
    }
    
    if ($highestColumnIndex < 2) {
        throw new Exception('Le fichier doit contenir au moins 2 colonnes.');
    }
    
    // Extraire les en-têtes (première ligne)
    $colonnes = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $valeur = $worksheet->getCellByColumnAndRow($col, 1)->getCalculatedValue();
        $colonnes[] = trim(strval($valeur));
    }
    
    // Extraire toutes les données (limiter à 1000 lignes pour l'analyse)
    $maxLignes = min($highestRow, 1001); // 1000 + en-tête
    $donnees = [];
    
    for ($row = 2; $row <= $maxLignes; $row++) {
        $ligne = [];
        $ligneVide = true;
        
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cellule = $worksheet->getCellByColumnAndRow($col, $row);
            $valeur = '';
            
            // Gestion des différents types de données
            if ($cellule->getValue() !== null) {
                if (Date::isDateTime($cellule)) {
                    // Date
                    $valeur = Date::excelToDateTimeObject($cellule->getValue())->format('Y-m-d');
                } else {
                    // Texte ou nombre
                    $valeur = $cellule->getCalculatedValue();
                }
                
                $valeur = trim(strval($valeur));
                if ($valeur !== '') {
                    $ligneVide = false;
                }
            }
            
            $ligne[] = $valeur;
        }
        
        // Ne pas ajouter les lignes complètement vides
        if (!$ligneVide) {
            $donnees[] = $ligne;
        }
    }
    
    if (empty($donnees)) {
        throw new Exception('Aucune donnée trouvée dans le fichier.');
    }
    
    // Analyse des types de colonnes
    $analysesColonnes = [];
    for ($col = 0; $col < count($colonnes); $col++) {
        $echantillon = [];
        
        // Prendre les 10 premières valeurs non vides
        $compte = 0;
        foreach ($donnees as $ligne) {
            if (isset($ligne[$col]) && $ligne[$col] !== '' && $compte < 10) {
                $echantillon[] = $ligne[$col];
                $compte++;
            }
        }
        
        $analyse = analyserTypeColonne($echantillon);
        $analysesColonnes[] = [
            'nom' => $colonnes[$col],
            'type' => $analyse['type'],
            'exemples' => array_slice($echantillon, 0, 3),
            'pourcentageVide' => calculerPourcentageVide($donnees, $col)
        ];
    }
    
    // Créer un fichier temporaire pour stocker les données
    $tempId = uniqid('excel_', true);
    $tempFile = '../temp/excel_' . $tempId . '.json';
    
    // Créer le dossier temp s'il n'existe pas
    if (!is_dir('../temp')) {
        mkdir('../temp', 0755, true);
    }
    
    $dataToStore = [
        'colonnes' => $colonnes,
        'donnees' => $donnees,
        'analyses' => $analysesColonnes,
        'fichierOriginal' => $fichier['name'],
        'timestamp' => time()
    ];
    
    file_put_contents($tempFile, json_encode($dataToStore, JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'colonnes' => $colonnes,
        'donnees' => array_slice($donnees, 0, 10), // Seulement 10 lignes pour la preview
        'analyses' => $analysesColonnes,
        'nombreLignes' => count($donnees),
        'nombreColonnes' => count($colonnes),
        'tempId' => $tempId,
        'message' => 'Fichier analysé avec succès'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Analyser le type d'une colonne basé sur un échantillon
 */
function analyserTypeColonne($echantillon) {
    if (empty($echantillon)) {
        return ['type' => 'vide', 'confiance' => 0];
    }
    
    $types = [
        'numerique' => 0,
        'date' => 0,
        'texte' => 0,
        'matricule' => 0,
        'nom' => 0
    ];
    
    foreach ($echantillon as $valeur) {
        $valeur = trim($valeur);
        
        // Test numérique (notes, etc.)
        if (is_numeric($valeur) || preg_match('/^\d+([.,]\d+)?$/', $valeur)) {
            $types['numerique']++;
            continue;
        }
        
        // Test matricule (formats courants)
        if (preg_match('/^[A-Z0-9]{6,15}$/i', $valeur) || 
            preg_match('/^\d{4,12}$/', $valeur) ||
            preg_match('/^[A-Z]{2,4}\d{4,8}$/i', $valeur)) {
            $types['matricule']++;
            continue;
        }
        
        // Test date
        if (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/', $valeur) ||
            preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $valeur)) {
            $types['date']++;
            continue;
        }
        
        // Test nom (plusieurs mots, commence par majuscule)
        if (preg_match('/^[A-Z][a-z]+(\s+[A-Z][a-z]+)*$/', $valeur) ||
            str_word_count($valeur) > 1) {
            $types['nom']++;
            continue;
        }
        
        // Par défaut: texte
        $types['texte']++;
    }
    
    // Retourner le type avec le plus de correspondances
    $typeMax = array_keys($types, max($types))[0];
    $confiance = round((max($types) / count($echantillon)) * 100, 1);
    
    return [
        'type' => $typeMax,
        'confiance' => $confiance
    ];
}

/**
 * Calculer le pourcentage de cellules vides dans une colonne
 */
function calculerPourcentageVide($donnees, $colonne) {
    $total = count($donnees);
    $vides = 0;
    
    foreach ($donnees as $ligne) {
        if (!isset($ligne[$colonne]) || trim($ligne[$colonne]) === '') {
            $vides++;
        }
    }
    
    return round(($vides / $total) * 100, 1);
}
?>
