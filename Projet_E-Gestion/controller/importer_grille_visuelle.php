<?php
session_start();
header('Content-Type: application/json');

// Augmenter les limites pour les gros imports
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '512M');

// Vérifier les droits d'accès
if (!isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

try {
    // Vérifier les données requises
    $requiredFields = ['annee_academique', 'session', 'promotion'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est requis.");
        }
    }
    
    // Vérifier les données JSON
    if (!isset($_POST['mapping_config']) || !isset($_POST['ue_configuration'])) {
        throw new Exception('Données de mapping manquantes.');
    }
    
    $mappingConfig = json_decode($_POST['mapping_config'], true);
    $ueConfiguration = json_decode($_POST['ue_configuration'], true);
    
    // Récupérer les données complètes depuis le fichier temporaire si tempId fourni
    $excelData = null;
    if (isset($_POST['tempId']) && !empty($_POST['tempId'])) {
        $tempId = $_POST['tempId'];
        $tempFile = dirname(__DIR__) . '/temp/excel_visuel_' . $tempId . '.json';
        
        if (file_exists($tempFile)) {
            $tempData = json_decode(file_get_contents($tempFile), true);
            if ($tempData && isset($tempData['donnees_completes'])) {
                $excelData = $tempData['donnees_completes'];
                error_log("Données complètes récupérées: " . count($excelData) . " lignes depuis tempId $tempId");
            }
        }
    }
    
    // Fallback sur les données POST si pas de tempId
    if (!$excelData && isset($_POST['excel_data'])) {
        $excelData = json_decode($_POST['excel_data'], true);
        error_log("Utilisation des données POST: " . count($excelData) . " lignes");
    }
    
    if (!$excelData) {
        throw new Exception('Aucunes données Excel disponibles.');
    }
    
    if (!$mappingConfig || !$excelData || !$ueConfiguration) {
        throw new Exception('Erreur de décodage des données JSON.');
    }
    
    // Valider le mapping
    if (!isset($mappingConfig['matricule']) || !isset($mappingConfig['nom']) || 
        !isset($mappingConfig['data_start_row']) || empty($ueConfiguration)) {
        throw new Exception('Configuration de mapping incomplète.');
    }
    
    require_once dirname(__DIR__) . '/models/GrilleAncienne.php';
    require_once dirname(__DIR__) . '/config/Connexion.php';
    
    $grilleAncienne = new GrilleAncienne();
    
    // Récupérer le crédit horaire depuis la configuration (logique de grille_notes.php)
    $db = Connexion::getInstance()->getPDO();
    $configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
    $config = $configQuery->fetch(PDO::FETCH_ASSOC);
    $creditHeure = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;
    
    // Créer les tables si nécessaire
    $grilleAncienne->createTablesIfNotExists();
    
    // Vérifier si un import avec ces paramètres existe déjà
    $existingImport = checkExistingImport($_POST);
    if ($existingImport) {
        throw new Exception("Un import existe déjà pour cette combinaison: {$_POST['annee_academique']} - {$_POST['session']} - {$_POST['promotion']}");
    }
    
    // Créer l'import avec la section si fournie
    $importData = [
        'annee_academique' => $_POST['annee_academique'],
        'session' => $_POST['session'],
        'semestre' => $_POST['semestre'] ?? 'annuel',
        'promotion' => $_POST['promotion'],
        'section_id' => isset($_POST['section_id']) && !empty($_POST['section_id']) ? intval($_POST['section_id']) : null,
        'fichier_origine' => 'Import_Visuel_' . date('Y-m-d_H-i-s') . '.xlsx',
        'mapping_config' => $mappingConfig
    ];
    
    $importId = $grilleAncienne->createImport($importData);
    
    // Debug: Afficher la configuration reçue
    error_log("=== DEBUG IMPORT ===");
    error_log("Section ID: " . ($importData['section_id'] ?? 'Aucune'));
    error_log("Nombre d'UE configurées: " . count($ueConfiguration));
    error_log("Configuration UE: " . json_encode($ueConfiguration, JSON_UNESCAPED_UNICODE));
    
    // Traiter les données avec la configuration des crédits
    $stats = processAdvancedMappingData($grilleAncienne, $excelData, $mappingConfig, $ueConfiguration, $importId, $creditHeure);
    
    // Calculer les moyennes si demandé
    if (isset($_POST['calculer_moyennes']) && $_POST['calculer_moyennes'] === 'on') {
        $grilleAncienne->calculerMoyennes($importId);
    }
    
    echo json_encode([
        'success' => true,
        'importId' => $importId,
        'statistiques' => $stats,
        'message' => 'Import réalisé avec succès'
    ]);
    
} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log("Erreur import grille visuelle: " . json_encode($errorDetails));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $errorDetails // Temporaire pour diagnostic
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
        
        return !empty($existing);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Traiter les données avec le mapping visuel avancé
 */
function processAdvancedMappingData($grilleAncienne, $excelData, $mappingConfig, $ueConfiguration, $importId, $creditHeure = 25) {
    $stats = [
        'etudiants' => 0,
        'ues' => 0,
        'ecues' => 0,
        'notes' => 0
    ];
    
    // 🔥 NOUVELLE LOGIQUE: Détection automatique de toutes les colonnes ECUE
    $colonnesEcueDetectees = detecterColonnesECUE($excelData, $mappingConfig);
    error_log("=== COLONNES ECUE AUTO-DÉTECTÉES ===");
    error_log("Nombre de colonnes ECUE détectées: " . count($colonnesEcueDetectees));
    
    // Créer les UE et ECUE selon la configuration OU auto-détection
    $ueIds = [];
    $ecueIds = [];
    $colonneToEcueId = []; // Mapping colonne Excel -> ID ECUE
    
    error_log("=== CRÉATION UE/ECUE ===");
    
    error_log("Diagnostic: ueConfiguration vide = " . (empty($ueConfiguration) ? "OUI" : "NON"));
    error_log("Diagnostic: ECUE détectées = " . count($colonnesEcueDetectees));
    error_log("Diagnostic: ECUE config = " . getTotalEcuesFromConfig($ueConfiguration));
    
    // 🎯 Utiliser la configuration UE si complète, sinon auto-détection
    $useAutoDetection = false;
    if (empty($ueConfiguration)) {
        $useAutoDetection = true;
        error_log("Auto-détection: configuration UE vide");
    } else if (getTotalEcuesFromConfig($ueConfiguration) < count($colonnesEcueDetectees) * 0.8) {
        $useAutoDetection = true;
        error_log("Auto-détection: configuration UE incomplète (" . getTotalEcuesFromConfig($ueConfiguration) . " vs " . count($colonnesEcueDetectees) . ")");
    } else {
        error_log("Utilisation de la configuration UE manuelle");
    }
    
    if ($useAutoDetection) {
        // Mode auto-détection: créer automatiquement les UE/ECUE
        error_log("Mode auto-détection activé - " . count($colonnesEcueDetectees) . " ECUE détectées");
        
        $ueS1 = [
            'code' => 'UE_S1_AUTO',
            'nom' => 'Unités d\'Enseignement - Semestre 1',
            'credits_total' => 0,
            'semestre' => 'S1',
            'ecues' => []
        ];
        
        $ueS2 = [
            'code' => 'UE_S2_AUTO', 
            'nom' => 'Unités d\'Enseignement - Semestre 2',
            'credits_total' => 0,
            'semestre' => 'S2',
            'ecues' => []
        ];
        
        // Distribuer les ECUE par semestre (approximation par position)
        foreach ($colonnesEcueDetectees as $index => $ecueDetectee) {
            $semestre = $ecueDetectee['colonne'] < 25 ? 'S1' : 'S2'; // Approximation
            
            $ecueConfig = [
                'code' => "ECUE" . ($index + 1),
                'nom' => $ecueDetectee['nom'],
                'credit' => $ecueDetectee['credit'],
                'colonne_excel' => $ecueDetectee['colonne']
            ];
            
            error_log("ECUE auto-détectée: {$ecueConfig['code']} - {$ecueConfig['nom']} → Colonne {$ecueConfig['colonne_excel']} ($semestre)");
            
            if ($semestre === 'S1') {
                $ueS1['ecues'][] = $ecueConfig;
                $ueS1['credits_total'] += $ecueDetectee['credit'];
            } else {
                $ueS2['ecues'][] = $ecueConfig;
                $ueS2['credits_total'] += $ecueDetectee['credit'];
            }
        }
        
        $ueConfiguration = [];
        if (!empty($ueS1['ecues'])) $ueConfiguration[] = $ueS1;
        if (!empty($ueS2['ecues'])) $ueConfiguration[] = $ueS2;
    }
    
    foreach ($ueConfiguration as $index => $ueConfig) {
        // Créer l'UE
        $ueData = [
            'code_ue' => $ueConfig['code'],
            'designation_ue' => $ueConfig['nom'],
            'credits' => $ueConfig['credits_total'],
            'semestre' => $ueConfig['semestre'] ?? 'S1',
            'ordre_affichage' => $index
        ];
        
        $ueId = $grilleAncienne->insertUE($importId, $ueData);
        $ueIds[$index] = $ueId;
        $stats['ues']++;
        
        error_log("UE créée: {$ueConfig['code']} - {$ueConfig['nom']} (ID: $ueId)");
        
        // Créer toutes les ECUE de cette UE
        foreach ($ueConfig['ecues'] as $ecueIndex => $ecueConfig) {
            // Lire le crédit depuis la ligne de crédits si disponible
            $creditEcue = $ecueConfig['credit'];
            if (isset($mappingConfig['credits_ligne_row']) && isset($ecueConfig['colonne_excel'])) {
                $creditsLigneRow = $mappingConfig['credits_ligne_row'];
                $colExcel = $ecueConfig['colonne_excel'];
                if (isset($excelData[$creditsLigneRow][$colExcel])) {
                    $creditFromExcel = convertToFloat($excelData[$creditsLigneRow][$colExcel]);
                    if ($creditFromExcel !== null && $creditFromExcel > 0) {
                        $creditEcue = $creditFromExcel;
                    }
                }
            }
            
            $ecueData = [
                'code_ecue' => $ecueConfig['code'],
                'designation_ecue' => $ecueConfig['nom'],
                'coefficient' => $creditEcue,
                'ordre_affichage' => $ecueIndex
            ];
            
            $ecueId = $grilleAncienne->insertECUE($ueId, $ecueData);
            $ecueIds[$index][$ecueIndex] = $ecueId;
            $stats['ecues']++;
            
            // ✅ OBLIGATOIRE: Mapper la colonne Excel à l'ECUE
            if (isset($ecueConfig['colonne_excel']) && $ecueConfig['colonne_excel'] !== null) {
                $colonneToEcueId[$ecueConfig['colonne_excel']] = $ecueId;
                error_log("Mapping ajouté: Colonne {$ecueConfig['colonne_excel']} → ECUE ID $ecueId ({$ecueConfig['nom']})");
            } else {
                // 🔥 CORRECTION: Essayer de trouver automatiquement la colonne pour cette ECUE
                $coloneTrouvee = null;
                foreach ($colonnesEcueDetectees as $ecueDetectee) {
                    if (trim($ecueDetectee['nom']) === trim($ecueConfig['nom'])) {
                        $coloneTrouvee = $ecueDetectee['colonne'];
                        break;
                    }
                }
                
                if ($coloneTrouvee !== null) {
                    $colonneToEcueId[$coloneTrouvee] = $ecueId;
                    error_log("Mapping auto-trouvé: Colonne $coloneTrouvee → ECUE ID $ecueId ({$ecueConfig['nom']})");
                } else {
                    error_log("⚠️ ECUE sans colonne_excel et non trouvée: {$ecueConfig['nom']}");
                }
            }
        }
    }
    
    // 🔍 DEBUG: Afficher le mapping colonnes → ECUE
    error_log("=== MAPPING FINAL colonneToEcueId ===");
    foreach ($colonneToEcueId as $col => $ecueId) {
        error_log("Colonne $col → ECUE ID $ecueId");
    }
    error_log("Total mappings: " . count($colonneToEcueId));
    
    // Traiter les étudiants et leurs notes
    $matriculeCol = $mappingConfig['matricule']['col'];
    $nomCol = $mappingConfig['nom']['col'];
    $startRow = $mappingConfig['data_start_row'];
    
    $etudiantsTraites = [];
    
    // Filtrer les lignes supprimées (conversion Set JavaScript -> Array PHP)
    $deletedRows = isset($mappingConfig['deleted_rows']) ? convertSetToArray($mappingConfig['deleted_rows']) : [];
    error_log("Lignes supprimées: " . count($deletedRows) . " / Total lignes: " . count($excelData));
    
    for ($row = $startRow; $row < count($excelData); $row++) {
        // Ignorer les lignes supprimées
        if (in_array($row, $deletedRows)) {
            error_log("Ligne $row ignorée (supprimée)");
            continue;
        }
        
        if (!isset($excelData[$row]) || empty($excelData[$row])) {
            error_log("Ligne $row ignorée (vide)");
            continue;
        }
        
        $matricule = trim($excelData[$row][$matriculeCol] ?? '');
        $nom = trim($excelData[$row][$nomCol] ?? '');
        
        if (empty($matricule) || empty($nom)) {
            error_log("Ligne $row ignorée - Matricule: '$matricule', Nom: '$nom'");
            continue; // Ignorer les lignes sans matricule ou nom
        }
    
        // Éviter les doublons
        if (isset($etudiantsTraites[$matricule])) {
            error_log("Ligne $row ignorée - Matricule '$matricule' déjà traité");
            continue;
        }
        
        // Insérer l'étudiant
        $etudiantData = [
            'matricule' => $matricule,
            'noms' => $nom,
            'ordre_affichage' => $stats['etudiants']
        ];
        
        $etudiantId = $grilleAncienne->insertEtudiant($importId, $etudiantData);
        $etudiantsTraites[$matricule] = $etudiantId;
        $stats['etudiants']++;
        
        error_log("Étudiant traité " . $stats['etudiants'] . ": '$matricule' - '$nom' (ligne $row)");

        // Traiter les notes pour chaque colonne mappée à une ECUE
        foreach ($colonneToEcueId as $col => $ecueId) {
            // Ignorer les colonnes supprimées
            $deletedCols = isset($mappingConfig['deleted_cols']) ? convertSetToArray($mappingConfig['deleted_cols']) : [];
            if (in_array($col, $deletedCols)) {
                continue;
            }
            
            $noteValue = isset($excelData[$row][$col]) ? trim($excelData[$row][$col]) : '';
            
            // Debug: Log pour vérifier l'import des notes
            error_log("Import note - Etudiant: $matricule, ECUE: $ecueId, Col: $col, Valeur: '$noteValue'");
            
            if ($noteValue !== '' && $noteValue !== null) {
                $note = convertToFloat($noteValue);
                
                if ($note !== null && $note >= 0 && $note <= 20) {
                    $noteData = [
                        'note_cc' => null,
                        'note_examen' => null,
                        'note_finale' => $note
                    ];
                    
                    $grilleAncienne->insertNote($etudiantId, $ecueId, $noteData);
                    $stats['notes']++;
                    error_log("Note insérée: $note pour étudiant $matricule, ECUE $ecueId");
                } else {
                    error_log("Note invalide ignorée: '$noteValue' pour étudiant $matricule, ECUE $ecueId");
                }
            } else if (!isset($_POST['ignorer_notes_vides']) || $_POST['ignorer_notes_vides'] !== 'on') {
                // Insérer une note à 0 si on ne doit pas ignorer les notes vides
                $noteData = [
                    'note_cc' => null,
                    'note_examen' => null,
                    'note_finale' => 0
                ];
                
                $grilleAncienne->insertNote($etudiantId, $ecueId, $noteData);
                $stats['notes']++;
                error_log("Note vide insérée comme 0 pour étudiant $matricule, ECUE $ecueId");
            } else {
                error_log("Note vide ignorée pour étudiant $matricule, ECUE $ecueId");
            }
        }
    }
    
    // Log final des statistiques
    error_log("=== STATISTIQUES FINALES ===");
    error_log("Étudiants traités: " . $stats['etudiants']);
    error_log("UE créées: " . $stats['ues']);
    error_log("ECUE créées: " . $stats['ecues']);
    error_log("Notes insérées: " . $stats['notes']);
    error_log("Lignes Excel totales: " . count($excelData));
    error_log("Ligne de début: $startRow");
    
    return $stats;
}

/**
 * Détecter automatiquement toutes les colonnes ECUE dans le fichier Excel
 */
function detecterColonnesECUE($excelData, $mappingConfig) {
    $colonnesEcue = [];
    
    if (count($excelData) < 9) {
        return $colonnesEcue; // Pas assez de lignes
    }
    
    $ligneEcue = $excelData[8]; // Ligne avec les noms d'ECUE
    $ligneCredits = $excelData[9]; // Ligne avec les crédits
    
    for ($col = 0; $col < count($ligneEcue); $col++) {
        $nomEcue = trim($ligneEcue[$col] ?? '');
        $credit = trim($ligneCredits[$col] ?? '');
        
        // Filtre: doit avoir un nom d'ECUE non vide, non "Moy UE", etc.
        if (!empty($nomEcue) && 
            !in_array($nomEcue, ['Moy UE', 'Valid', 'Moy Sem', 'Crédits', 'Pourcentage', 'Moy Ann', 'Décision', 'Synthèse Annuelle', 'Résultats S1', 'Résultats S2']) &&
            is_numeric($credit) && floatval($credit) > 0) {
            
            $colonnesEcue[] = [
                'colonne' => $col,
                'nom' => $nomEcue,
                'credit' => floatval($credit)
            ];
        }
    }
    
    return $colonnesEcue;
}

/**
 * Compter le nombre total d'ECUE dans la configuration UE
 */
function getTotalEcuesFromConfig($ueConfiguration) {
    $total = 0;
    foreach ($ueConfiguration as $ue) {
        $total += count($ue['ecues'] ?? []);
    }
    return $total;
}

/**
 * Générer un code UE à partir du nom
 */
function genererCodeUE($nom) {
    // Nettoyer et normaliser le nom
    $nom = strtoupper(trim($nom));
    $nom = preg_replace('/[^A-Z0-9\s]/', '', $nom);
    
    // Prendre les premières lettres des mots
    $mots = explode(' ', $nom);
    $code = '';
    
    foreach ($mots as $mot) {
        if (strlen($mot) > 0) {
            $code .= substr($mot, 0, 3);
        }
        if (strlen($code) >= 6) break;
    }
    
    // Compléter si nécessaire
    if (strlen($code) < 3) {
        $code = substr($nom, 0, 6);
    }
    
    return substr($code, 0, 8);
}

/**
 * Convertir une valeur en nombre décimal
 */
function convertToFloat($value) {
    if ($value === null || $value === '') {
        return null;
    }
    
    // Nettoyer la valeur
    $value = str_replace(',', '.', strval($value));
    $value = preg_replace('/[^\d\.]/', '', $value);
    
    // Extraire le nombre
    if (is_numeric($value)) {
        return floatval($value);
    }
    
    return null;
}

/**
 * Convertir un Set JavaScript (envoyé comme objet) en array PHP
 */
function convertSetToArray($setData) {
    if (is_array($setData)) {
        return array_keys($setData);
    } elseif (is_object($setData)) {
        return array_keys((array)$setData);
    }
    return [];
}
?>
