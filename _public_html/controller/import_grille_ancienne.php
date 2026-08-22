<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/GrilleAncienne.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

session_start();

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Configuration d'erreur
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

// Fonction de débogage
function debug_log($data, $label = '') {
    $output = date('Y-m-d H:i:s') . " - " . $label . ":\n";
    $output .= print_r($data, true) . "\n\n";
    file_put_contents(dirname(__DIR__) . '/debug_import_grille.log', $output, FILE_APPEND);
}

try {
    $grilleAncienne = new GrilleAncienne();
    
    // Créer les tables si elles n'existent pas
    $grilleAncienne->createTablesIfNotExists();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // ============================================
        // PHASE 1: VALIDATION ET UPLOAD DU FICHIER
        // ============================================
        
        if (!isset($_FILES['fichier_excel']) || $_FILES['fichier_excel']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier Excel');
        }
        
        // Validation des données du formulaire
        $requiredFields = ['annee_academique', 'session', 'semestre', 'promotion'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ $field est requis");
            }
        }
        
        // Récupérer la section si fournie
        $sectionId = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
        
        $uploadDir = dirname(__DIR__) . '/uploads/grilles_anciennes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['fichier_excel']['name']);
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['fichier_excel']['tmp_name'], $filePath)) {
            throw new Exception('Erreur lors de la sauvegarde du fichier');
        }
        
        debug_log(['file_path' => $filePath], 'Fichier sauvegardé');
        
        // ============================================
        // PHASE 2: LECTURE ET ANALYSE DU FICHIER EXCEL
        // ============================================
        
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        
        debug_log([
            'highest_row' => $highestRow,
            'highest_column' => $highestColumn,
            'highest_column_index' => $highestColumnIndex
        ], 'Dimensions du fichier');
        
        // ============================================
        // PHASE 3: DÉTECTION AUTOMATIQUE DE LA STRUCTURE
        // ============================================
        
        $structure = detecterStructureGrille($worksheet, $highestRow, $highestColumnIndex);
        debug_log($structure, 'Structure détectée');
        
        if (!$structure) {
            throw new Exception('Impossible de détecter la structure de la grille. Veuillez vérifier le format du fichier.');
        }
        
        // ============================================
        // PHASE 4: CRÉATION DE L'IMPORT
        // ============================================
        
        $importData = [
            'annee_academique' => $_POST['annee_academique'],
            'session' => $_POST['session'],
            'semestre' => $_POST['semestre'],
            'promotion' => $_POST['promotion'],
            'section_id' => $sectionId,
            'fichier_origine' => $fileName,
            'mapping_config' => $structure
        ];
        
        $importId = $grilleAncienne->createImport($importData);
        debug_log(['import_id' => $importId], 'Import créé');
        
        // ============================================
        // PHASE 5: IMPORTATION DES DONNÉES
        // ============================================
        
        $result = importerDonneesGrille($grilleAncienne, $worksheet, $structure, $importId);
        
        // ============================================
        // PHASE 6: CALCUL DES MOYENNES
        // ============================================
        
        $grilleAncienne->calculerMoyennes($importId);
        
        // Supprimer le fichier temporaire
        unlink($filePath);
        
        // Réponse de succès
        echo json_encode([
            'success' => true,
            'message' => 'Grille importée avec succès',
            'import_id' => $importId,
            'statistiques' => $result
        ]);
        
    } else {
        // Retourner les données pour pré-remplir le formulaire (années, sessions, etc.)
        $data = [
            'annees' => getAnneesAcademiques(),
            'sessions' => getSessions()
        ];
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
} catch (Exception $e) {
    debug_log(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 'Erreur');
    
    // Supprimer le fichier en cas d'erreur
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Détecter automatiquement la structure d'une grille de notes Excel
 */
function detecterStructureGrille($worksheet, $maxRow, $maxCol) {
    $structure = [
        'ligne_entetes' => null,
        'ligne_donnees_debut' => null,
        'colonne_matricule' => null,
        'colonne_noms' => null,
        'colonnes_ues' => [],
        'colonnes_ecues' => [],
        'colonnes_notes' => []
    ];
    
    // Rechercher la ligne d'en-têtes (contient "matricule", "nom", etc.)
    for ($row = 1; $row <= min(10, $maxRow); $row++) {
        $found_matricule = false;
        $found_nom = false;
        
        for ($col = 1; $col <= $maxCol; $col++) {
            $value = strtolower(trim($worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue()));
            
            if (in_array($value, ['matricule', 'matric', 'numero', 'n°'])) {
                $structure['colonne_matricule'] = $col;
                $found_matricule = true;
            }
            
            if (in_array($value, ['nom', 'noms', 'nom et prenom', 'nom et prénom', 'etudiant', 'étudiant'])) {
                $structure['colonne_noms'] = $col;
                $found_nom = true;
            }
        }
        
        if ($found_matricule && $found_nom) {
            $structure['ligne_entetes'] = $row;
            $structure['ligne_donnees_debut'] = $row + 1;
            break;
        }
    }
    
    if (!$structure['ligne_entetes']) {
        return false;
    }
    
    // Analyser les colonnes UE/ECUE
    $ligne_entetes = $structure['ligne_entetes'];
    
    // Rechercher les patterns de colonnes après les colonnes de base
    $col_start = max($structure['colonne_matricule'], $structure['colonne_noms']) + 1;
    
    for ($col = $col_start; $col <= $maxCol; $col++) {
        $cellValue = trim($worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $ligne_entetes)->getCalculatedValue());
        
        if (empty($cellValue)) continue;
        
        // Détecter si c'est une UE (commence souvent par "UE", ou contient "crédit")
        if (preg_match('/^UE\s*\d+|unité.*enseignement|crédit/i', $cellValue)) {
            $structure['colonnes_ues'][$col] = [
                'designation' => $cellValue,
                'type' => 'ue_moyenne'
            ];
        }
        // Détecter si c'est une note (CC, EX, MF, ou nombre décimal)
        elseif (preg_match('/CC|controle|examen|EX|MF|moyenne|note/i', $cellValue)) {
            $structure['colonnes_notes'][$col] = [
                'designation' => $cellValue,
                'type' => detecterTypeNote($cellValue)
            ];
        }
        // Sinon, c'est probablement une ECUE
        else {
            $structure['colonnes_ecues'][$col] = [
                'designation' => $cellValue,
                'type' => 'ecue'
            ];
        }
    }
    
    return $structure;
}

/**
 * Détecter le type de note (CC, EX, MF)
 */
function detecterTypeNote($cellValue) {
    $value = strtolower($cellValue);
    
    if (preg_match('/cc|controle|continu/i', $value)) {
        return 'CC';
    } elseif (preg_match('/ex|examen/i', $value)) {
        return 'EX';
    } elseif (preg_match('/mf|moyenne|finale/i', $value)) {
        return 'MF';
    }
    
    return 'MF'; // Par défaut
}

/**
 * Importer les données de la grille
 */
function importerDonneesGrille($grilleAncienne, $worksheet, $structure, $importId) {
    $stats = [
        'etudiants_importes' => 0,
        'ues_importees' => 0,
        'ecues_importees' => 0,
        'notes_importees' => 0
    ];
    
    // ============================================
    // 1. CRÉER LES UE ET ECUE
    // ============================================
    
    $ueMapping = [];
    $ecueMapping = [];
    
    // Créer une UE par défaut si pas détectée
    if (empty($structure['colonnes_ues'])) {
        $ueId = $grilleAncienne->insertUE($importId, [
            'code_ue' => 'UE_DEFAULT',
            'designation_ue' => 'Unité d\'Enseignement',
            'credits' => 30, // Valeur par défaut
            'ordre_affichage' => 1
        ]);
        $ueMapping['default'] = $ueId;
        $stats['ues_importees']++;
    } else {
        $ordre = 1;
        foreach ($structure['colonnes_ues'] as $col => $info) {
            $ueId = $grilleAncienne->insertUE($importId, [
                'code_ue' => 'UE_' . $ordre,
                'designation_ue' => $info['designation'],
                'credits' => 30, // À ajuster selon la grille
                'ordre_affichage' => $ordre
            ]);
            $ueMapping[$col] = $ueId;
            $ordre++;
            $stats['ues_importees']++;
        }
    }
    
    // Créer les ECUE
    $ueDefault = !empty($ueMapping['default']) ? $ueMapping['default'] : reset($ueMapping);
    $ordre = 1;
    
    foreach ($structure['colonnes_ecues'] as $col => $info) {
        $ecueId = $grilleAncienne->insertECUE($ueDefault, [
            'code_ecue' => 'ECUE_' . $ordre,
            'designation_ecue' => $info['designation'],
            'coefficient' => 1.0,
            'ordre_affichage' => $ordre
        ]);
        $ecueMapping[$col] = $ecueId;
        $ordre++;
        $stats['ecues_importees']++;
    }
    
    // ============================================
    // 2. IMPORTER LES ÉTUDIANTS ET LEURS NOTES
    // ============================================
    
    $maxRow = $worksheet->getHighestRow();
    
    for ($row = $structure['ligne_donnees_debut']; $row <= $maxRow; $row++) {
        // Lire le matricule et le nom
        $matricule = trim($worksheet->getCell(Coordinate::stringFromColumnIndex($structure['colonne_matricule']) . $row)->getCalculatedValue());
        $noms = trim($worksheet->getCell(Coordinate::stringFromColumnIndex($structure['colonne_noms']) . $row)->getCalculatedValue());
        
        if (empty($matricule) || empty($noms)) {
            continue; // Ignorer les lignes vides
        }
        
        // Créer l'étudiant
        $etudiantId = $grilleAncienne->insertEtudiant($importId, [
            'matricule' => $matricule,
            'noms' => $noms,
            'ordre_affichage' => $row - $structure['ligne_donnees_debut'] + 1
        ]);
        
        $stats['etudiants_importes']++;
        
        // Importer les notes
        foreach ($structure['colonnes_notes'] as $col => $info) {
            $noteValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
            
            if ($noteValue !== null && $noteValue !== '') {
                // Déterminer l'ECUE associée (prendre la première disponible pour simplifier)
                $ecueId = !empty($ecueMapping) ? reset($ecueMapping) : null;
                
                if ($ecueId) {
                    $noteData = [
                        'note_cc' => $info['type'] === 'CC' ? $noteValue : null,
                        'note_examen' => $info['type'] === 'EX' ? $noteValue : null,
                        'note_finale' => $info['type'] === 'MF' ? $noteValue : $noteValue
                    ];
                    
                    $grilleAncienne->insertNote($etudiantId, $ecueId, $noteData);
                    $stats['notes_importees']++;
                }
            }
        }
        
        // Traiter les notes des ECUE
        foreach ($structure['colonnes_ecues'] as $col => $info) {
            if (isset($ecueMapping[$col])) {
                $noteValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                
                if ($noteValue !== null && $noteValue !== '') {
                    $noteData = [
                        'note_cc' => null,
                        'note_examen' => null,
                        'note_finale' => $noteValue
                    ];
                    
                    $grilleAncienne->insertNote($etudiantId, $ecueMapping[$col], $noteData);
                    $stats['notes_importees']++;
                }
            }
        }
    }
    
    return $stats;
}

/**
 * Récupérer les années académiques
 */
function getAnneesAcademiques() {
    $db = Connexion::getInstance()->getPDO();
    $stmt = $db->query("SELECT * FROM annee_acad ORDER BY designation DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupérer les sessions
 */
function getSessions() {
    return [
        ['id' => 'principale', 'nom' => 'Session Principale'],
        ['id' => 'rattrapage', 'nom' => 'Session de Rattrapage']
    ];
}
