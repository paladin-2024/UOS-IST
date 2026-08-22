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
    // Vérifier qu'un fichier a été uploadé
    if (!isset($_FILES['fichier_excel']) || $_FILES['fichier_excel']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Aucun fichier reçu ou erreur d\'upload.');
    }

    // Vérifier les données requises
    $requiredFields = ['annee_academique', 'session', 'semestre', 'promotion'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est requis.");
        }
    }

    $fichier = $_FILES['fichier_excel'];
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ['xlsx', 'xls'])) {
        throw new Exception('Format de fichier non supporté. Utilisez .xlsx ou .xls');
    }

    require_once '../vendor/autoload.php';
    require_once '../models/GrilleAncienne.php';
    
    use PhpOffice\PhpSpreadsheet\IOFactory;
    use PhpOffice\PhpSpreadsheet\Shared\Date;

    $grilleAncienne = new GrilleAncienne();
    
    // Créer les tables si nécessaire
    $grilleAncienne->createTablesIfNotExists();

    // Vérifier si un import avec ces paramètres existe déjà
    $existingImport = checkExistingImport($_POST);
    if ($existingImport) {
        throw new Exception("Un import existe déjà pour cette combinaison: {$_POST['annee_academique']} - {$_POST['session']} - {$_POST['semestre']} - {$_POST['promotion']}");
    }

    // Lire le fichier Excel
    $reader = IOFactory::createReaderForFile($fichier['tmp_name']);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($fichier['tmp_name']);
    
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    // Vérifications de base
    if ($highestRow < 3) {
        throw new Exception('Le fichier doit contenir au moins 3 lignes (en-têtes + 2 lignes de données minimum).');
    }

    // Analyser la structure du fichier Excel automatiquement
    $structure = analyzeExcelStructure($worksheet, $highestRow, $highestColumnIndex);
    
    if (!$structure['valid']) {
        throw new Exception($structure['error']);
    }

    // Créer l'import
    $importData = [
        'annee_academique' => $_POST['annee_academique'],
        'session' => $_POST['session'],
        'semestre' => $_POST['semestre'],
        'promotion' => $_POST['promotion'],
        'fichier_origine' => $fichier['name'],
        'mapping_config' => $structure['mapping']
    ];

    $importId = $grilleAncienne->createImport($importData);

    // Traiter les données
    $stats = processExcelData($grilleAncienne, $worksheet, $structure, $importId, $highestRow);

    // Calculer les moyennes
    $grilleAncienne->calculerMoyennes($importId);

    echo json_encode([
        'success' => true,
        'importId' => $importId,
        'statistiques' => $stats,
        'message' => 'Import réalisé avec succès'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Vérifier si un import existe déjà
 */
function checkExistingImport($data) {
    try {
        $grilleAncienne = new GrilleAncienne();
        $existing = $grilleAncienne->searchImports([
            'annee_academique' => $data['annee_academique'],
            'session' => $data['session'],
            'promotion' => $data['promotion']
        ]);
        
        foreach ($existing as $import) {
            if ($import['semestre'] === $data['semestre']) {
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Analyser automatiquement la structure du fichier Excel
 */
function analyzeExcelStructure($worksheet, $highestRow, $highestColumnIndex) {
    $result = [
        'valid' => false,
        'error' => '',
        'mapping' => []
    ];

    try {
        // Analyser les 5 premières lignes pour détecter la structure
        $headers = [];
        $dataStartRow = 2;
        
        // Trouver la ligne d'en-têtes (celle qui contient "Matricule" ou des noms d'UE)
        for ($row = 1; $row <= min(5, $highestRow); $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = trim(strval($worksheet->getCellByColumnAndRow($col, $row)->getCalculatedValue()));
                $rowData[] = $value;
            }
            
            // Vérifier si cette ligne contient des en-têtes typiques
            $hasMatricule = false;
            $hasNom = false;
            $ueColumns = [];
            
            foreach ($rowData as $index => $value) {
                $valueLower = strtolower($value);
                
                if (strpos($valueLower, 'matricule') !== false || strpos($valueLower, 'numero') !== false) {
                    $hasMatricule = true;
                    $result['mapping']['matricule_col'] = $index + 1;
                } elseif (strpos($valueLower, 'nom') !== false && strpos($valueLower, 'prenom') === false) {
                    $hasNom = true;
                    $result['mapping']['nom_col'] = $index + 1;
                } elseif (!empty($value) && !in_array($valueLower, ['total', 'moyenne', 'mention', 'credits'])) {
                    // Potentielle colonne UE/ECUE
                    $ueColumns[] = [
                        'col' => $index + 1,
                        'name' => $value
                    ];
                }
            }
            
            if ($hasMatricule && $hasNom && !empty($ueColumns)) {
                $headers = $rowData;
                $dataStartRow = $row + 1;
                $result['mapping']['ue_columns'] = $ueColumns;
                break;
            }
        }
        
        if (empty($headers)) {
            $result['error'] = 'Impossible de détecter la structure du fichier. Assurez-vous qu\'il contient une ligne avec "Matricule", "Nom" et les noms des matières.';
            return $result;
        }

        $result['mapping']['headers'] = $headers;
        $result['mapping']['data_start_row'] = $dataStartRow;
        $result['valid'] = true;
        
        return $result;

    } catch (Exception $e) {
        $result['error'] = 'Erreur lors de l\'analyse: ' . $e->getMessage();
        return $result;
    }
}

/**
 * Traiter les données Excel et les insérer en base
 */
function processExcelData($grilleAncienne, $worksheet, $structure, $importId, $highestRow) {
    $stats = [
        'etudiants_importes' => 0,
        'ues_importees' => 0,
        'ecues_importees' => 0,
        'notes_importees' => 0
    ];

    $mapping = $structure['mapping'];
    $dataStartRow = $mapping['data_start_row'];
    
    // Créer les UE et ECUE à partir des colonnes détectées
    $ueIds = [];
    $ecueIds = [];
    
    foreach ($mapping['ue_columns'] as $index => $ueInfo) {
        // Créer l'UE
        $ueData = [
            'code_ue' => 'UE' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
            'designation_ue' => $ueInfo['name'],
            'credits' => 3, // Valeur par défaut
            'ordre_affichage' => $index
        ];
        
        $ueId = $grilleAncienne->insertUE($importId, $ueData);
        $ueIds[$ueInfo['col']] = $ueId;
        $stats['ues_importees']++;
        
        // Créer une ECUE pour chaque UE (structure simple)
        $ecueData = [
            'code_ecue' => $ueData['code_ue'] . '_01',
            'designation_ecue' => $ueInfo['name'],
            'coefficient' => 1,
            'ordre_affichage' => 0
        ];
        
        $ecueId = $grilleAncienne->insertECUE($ueId, $ecueData);
        $ecueIds[$ueInfo['col']] = $ecueId;
        $stats['ecues_importees']++;
    }

    // Traiter les étudiants et leurs notes
    for ($row = $dataStartRow; $row <= $highestRow; $row++) {
        $matricule = trim(strval($worksheet->getCellByColumnAndRow($mapping['matricule_col'], $row)->getCalculatedValue()));
        $nom = trim(strval($worksheet->getCellByColumnAndRow($mapping['nom_col'], $row)->getCalculatedValue()));
        
        if (empty($matricule) || empty($nom)) {
            continue; // Ignorer les lignes vides
        }

        // Insérer l'étudiant
        $etudiantData = [
            'matricule' => $matricule,
            'noms' => $nom,
            'ordre_affichage' => $row - $dataStartRow
        ];
        
        $etudiantId = $grilleAncienne->insertEtudiant($importId, $etudiantData);
        $stats['etudiants_importes']++;

        // Traiter les notes pour chaque UE/ECUE
        foreach ($mapping['ue_columns'] as $ueInfo) {
            $col = $ueInfo['col'];
            $noteValue = $worksheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
            
            if ($noteValue !== null && $noteValue !== '') {
                $note = convertToFloat($noteValue);
                
                if ($note !== null && $note >= 0 && $note <= 20) {
                    $noteData = [
                        'note_cc' => null,
                        'note_examen' => null,
                        'note_finale' => $note
                    ];
                    
                    $grilleAncienne->insertNote($etudiantId, $ecueIds[$col], $noteData);
                    $stats['notes_importees']++;
                }
            }
        }
    }

    return $stats;
}

/**
 * Convertir une valeur en nombre décimal
 */
function convertToFloat($value) {
    if ($value === null || $value === '') {
        return null;
    }
    
    // Remplacer virgule par point
    $value = str_replace(',', '.', strval($value));
    
    // Extraire le nombre
    if (is_numeric($value)) {
        return floatval($value);
    }
    
    return null;
}
?>
