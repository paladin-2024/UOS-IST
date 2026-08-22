<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Vérifier si composer est installé
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'PhpSpreadsheet non installé. Exécutez: composer install'
    ]);
    exit;
}

require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

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
        throw new Exception('Le fichier doit contenir au moins 3 lignes.');
    }
    
    if ($highestColumnIndex < 3) {
        throw new Exception('Le fichier doit contenir au moins 3 colonnes.');
    }
    
    // Limiter la lecture pour la performance (max 50 lignes x 50 colonnes pour l'aperçu)
    $maxPreviewRows = min($highestRow, 50);
    $maxPreviewCols = min($highestColumnIndex, 50);
    
    // Extraire toutes les données pour l'aperçu
    $donnees = [];
    
    for ($row = 1; $row <= $maxPreviewRows; $row++) {
        $ligne = [];
        
        for ($col = 1; $col <= $maxPreviewCols; $col++) {
            try {
                $cellule = $worksheet->getCell([$col, $row]);
                $valeur = '';
                
                // Gestion des différents types de données
                if ($cellule->getValue() !== null) {
                    if (Date::isDateTime($cellule)) {
                        // Date
                        $valeur = Date::excelToDateTimeObject($cellule->getValue())->format('d/m/Y');
                    } else {
                        // Texte ou nombre
                        $valeur = $cellule->getCalculatedValue();
                    }
                    
                    $valeur = trim(strval($valeur));
                }
                
                $ligne[] = $valeur;
            } catch (Exception $e) {
                // En cas d'erreur, ajouter une cellule vide
                $ligne[] = '';
            }
        }
        
        $donnees[] = $ligne;
    }
    
    // Analyser la structure pour donner des suggestions
    $suggestions = analyzeStructure($donnees);
    
    // Créer le dossier temp s'il n'existe pas
    $tempDir = dirname(__DIR__) . '/temp';
    if (!is_dir($tempDir)) {
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception('Impossible de créer le dossier temporaire');
        }
    }
    
    // Sauvegarder temporairement les données complètes
    $tempId = uniqid('excel_visuel_', true);
    $tempFile = $tempDir . '/excel_visuel_' . $tempId . '.json';
    
    // Lire toutes les données pour le traitement final (limité pour éviter les problèmes de mémoire)
    $donneesCompletes = [];
    $maxCompleteRows = min($highestRow, 1000); // Limiter à 1000 lignes
    
    for ($row = 1; $row <= $maxCompleteRows; $row++) {
        $ligne = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            try {
                $cellule = $worksheet->getCell([$col, $row]);
                $valeur = '';
                
                if ($cellule->getValue() !== null) {
                    if (Date::isDateTime($cellule)) {
                        $valeur = Date::excelToDateTimeObject($cellule->getValue())->format('d/m/Y');
                    } else {
                        $valeur = $cellule->getCalculatedValue();
                    }
                    $valeur = trim(strval($valeur));
                }
                
                $ligne[] = $valeur;
            } catch (Exception $e) {
                $ligne[] = '';
            }
        }
        $donneesCompletes[] = $ligne;
    }
    
    $dataToStore = [
        'donnees_preview' => $donnees,
        'donnees_completes' => $donneesCompletes,
        'fichier_original' => $fichier['name'],
        'lignes_totales' => $highestRow,
        'colonnes_totales' => $highestColumnIndex,
        'suggestions' => $suggestions,
        'timestamp' => time()
    ];
    
    if (file_put_contents($tempFile, json_encode($dataToStore, JSON_UNESCAPED_UNICODE)) === false) {
        throw new Exception('Erreur lors de la sauvegarde temporaire');
    }
    
    echo json_encode([
        'success' => true,
        'donnees' => $donnees, // Aperçu limité
        'lignes' => $highestRow,
        'colonnes' => $highestColumnIndex,
        'suggestions' => $suggestions,
        'tempId' => $tempId,
        'message' => 'Fichier analysé avec succès'
    ]);
    
} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log("Erreur analyse Excel: " . json_encode($errorDetails));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'analyse: ' . $e->getMessage(),
        'debug' => $errorDetails // Temporaire pour diagnostic
    ]);
}

/**
 * Analyser la structure pour donner des suggestions automatiques
 */
function analyzeStructure($donnees) {
    $suggestions = [
        'matricule_detecte' => null,
        'nom_detecte' => null,
        'ue_possibles' => [],
        'debut_donnees_suggere' => null,
        'auto_mapping' => null,
    ];

    // Détection prioritaire du format modèle standard
    $autoMapping = detectStandardTemplate($donnees);
    if ($autoMapping) {
        $suggestions['auto_mapping'] = $autoMapping;
        $suggestions['matricule_detecte'] = $autoMapping['matricule'];
        $suggestions['nom_detecte'] = $autoMapping['nom'];
        $suggestions['debut_donnees_suggere'] = [
            'row' => $autoMapping['data_start_row'],
            'col' => $autoMapping['data_start_col'],
        ];
        return $suggestions;
    }
    
    if (empty($donnees) || count($donnees) < 2) {
        return $suggestions;
    }
    
    // Analyser les 10 premières lignes pour détecter la structure
    $maxAnalyze = min(10, count($donnees));
    
    for ($row = 0; $row < $maxAnalyze; $row++) {
        if (!isset($donnees[$row]) || !is_array($donnees[$row])) {
            continue;
        }
        
        for ($col = 0; $col < count($donnees[$row]); $col++) {
            $cellValue = strtolower(trim($donnees[$row][$col]));
            
            // Détecter la colonne matricule
            if (preg_match('/matricule|numero|n°|num|id/', $cellValue) && !$suggestions['matricule_detecte']) {
                $suggestions['matricule_detecte'] = ['row' => $row, 'col' => $col];
            }
            
            // Détecter la colonne nom
            if (preg_match('/^nom$|noms|nom\s+et|nom\s+prenom|étudiant/', $cellValue) && !$suggestions['nom_detecte']) {
                $suggestions['nom_detecte'] = ['row' => $row, 'col' => $col];
            }
            
            // Détecter les UE potentielles (en-têtes qui ne sont pas des mots-clés système)
            if (!empty($cellValue) && 
                !preg_match('/matricule|numero|n°|num|id|nom|prenom|total|moyenne|mention|credit|coeff|note/', $cellValue) &&
                strlen($cellValue) > 2 && strlen($cellValue) < 50) {
                
                // Vérifier si cette cellule pourrait être une UE
                if (preg_match('/[a-zA-Z]/', $cellValue)) { // Contient des lettres
                    $suggestions['ue_possibles'][] = [
                        'row' => $row,
                        'col' => $col,
                        'nom' => $donnees[$row][$col]
                    ];
                }
            }
        }
    }
    
    // Suggérer le début des données (première ligne qui contient des matricules)
    for ($row = 1; $row < $maxAnalyze; $row++) {
        if (!isset($donnees[$row]) || !is_array($donnees[$row])) {
            continue;
        }
        
        for ($col = 0; $col < count($donnees[$row]); $col++) {
            $cellValue = trim($donnees[$row][$col]);
            
            // Si on trouve quelque chose qui ressemble à un matricule étudiant
            if (preg_match('/^[A-Z0-9]{6,15}$|^\d{4,12}$|^[A-Z]{2,4}\d{4,8}$/i', $cellValue)) {
                if (!$suggestions['debut_donnees_suggere']) {
                    $suggestions['debut_donnees_suggere'] = ['row' => $row, 'col' => 0];
                    break 2;
                }
            }
        }
    }
    
    // Limiter les UE possibles pour éviter la surcharge
    if (count($suggestions['ue_possibles']) > 20) {
        $suggestions['ue_possibles'] = array_slice($suggestions['ue_possibles'], 0, 20);
    }
    
    return $suggestions;
}

/**
 * Détecter si le fichier suit le format du modèle standard e-Gestion.
 *
 * Structure attendue :
 *   - Lignes optionnelles de métadonnées (ANNEE_ACADEMIQUE, SESSION, PROMOTION, SEMESTRE)
 *   - Une ligne avec col[0]="MATRICULE" et col[1]="NOM"  (rowUE)
 *   - La ligne suivante = noms ECUE                       (rowECUE)
 *   - rowUE+2 = crédits, col[0]="CREDITS"                (rowCredits)
 *   - Données à partir de rowUE+3
 */
function detectStandardTemplate($donnees) {
    $total = count($donnees);
    if ($total < 4) return null;

    // 1. Trouver la ligne MATRICULE (scan des 12 premières lignes)
    $rowUE = null;
    for ($r = 0; $r < min(12, $total); $r++) {
        $c0 = strtoupper(trim($donnees[$r][0] ?? ''));
        $c1 = strtoupper(trim($donnees[$r][1] ?? ''));
        if ($c0 === 'MATRICULE' && $c1 === 'NOM') {
            $rowUE = $r;
            break;
        }
    }
    if ($rowUE === null) return null;

    $rowECUE    = $rowUE + 1;
    $rowCredits = $rowUE + 2;
    $rowData    = $rowUE + 3;

    if ($rowCredits >= $total) return null;
    if (strtoupper(trim($donnees[$rowCredits][0] ?? '')) !== 'CREDITS') return null;

    // 2. Extraire les métadonnées depuis les lignes avant MATRICULE
    $metaKeys = [
        'ANNEE_ACADEMIQUE' => 'annee_academique',
        'SESSION'          => 'session',
        'PROMOTION'        => 'promotion',
        'SEMESTRE'         => 'semestre',
    ];
    $metadata = [];
    for ($r = 0; $r < $rowUE; $r++) {
        $key = strtoupper(trim($donnees[$r][0] ?? ''));
        $val = trim($donnees[$r][1] ?? '');
        if (isset($metaKeys[$key]) && $val !== '') {
            $metadata[$metaKeys[$key]] = $val;
        }
    }

    // 3. Construire le mapping UE/ECUE
    $ueRow   = $donnees[$rowUE];
    $ecueRow = $donnees[$rowECUE] ?? [];
    $maxCols = max(count($ueRow), count($ecueRow));

    $mapping = [
        'matricule'         => ['row' => $rowUE, 'col' => 0],
        'nom'               => ['row' => $rowUE, 'col' => 1],
        'ue_columns'        => [],
        'ecue_columns'      => [],
        'credits_ligne_row' => $rowCredits,
        'data_start_row'    => $rowData,
        'data_start_col'    => 0,
        'metadata'          => $metadata,
    ];

    // UE : ligne rowUE, col >= 2 (cellules fusionnées = valeur seulement sur la première)
    $currentUEIndex = -1;
    $ueMap = [];
    for ($col = 2; $col < $maxCols; $col++) {
        $ueVal = trim($ueRow[$col] ?? '');
        if ($ueVal !== '') {
            $mapping['ue_columns'][] = [
                'row' => $rowUE,
                'col' => $col,
                'nom' => $ueVal,
                'id'  => 'ue_auto_' . $col,
            ];
            $currentUEIndex++;
        }
        $ueMap[$col] = $currentUEIndex;
    }

    // ECUE : ligne rowECUE, col >= 2
    for ($col = 2; $col < $maxCols; $col++) {
        $ecueVal = trim($ecueRow[$col] ?? '');
        if ($ecueVal !== '') {
            $ueAssociee = $ueMap[$col] ?? ($currentUEIndex >= 0 ? $currentUEIndex : null);
            $mapping['ecue_columns'][] = [
                'row'         => $rowECUE,
                'col'         => $col,
                'nom'         => $ecueVal,
                'id'          => 'ecue_auto_' . $col,
                'ue_associee' => $ueAssociee,
            ];
        }
    }

    if (empty($mapping['ecue_columns'])) return null;

    return $mapping;
}
?>
