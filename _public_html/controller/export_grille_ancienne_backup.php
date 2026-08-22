<?php
// Export Excel pour les grilles anciennes importées
set_time_limit(1200); // 10 minutes
ini_set('memory_limit', '512M');

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/GrilleAncienne.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer les paramètres
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

if ($importId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID d\'import invalide']);
    exit;
}

try {
    // Initialiser les objets
    $grilleAncienne = new GrilleAncienne();
    $universite = new Universite();
    
    // Récupérer la configuration de l'université
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Récupérer les données de l'import
    $importInfo = $grilleAncienne->getImportById($importId);
    if (!$importInfo) {
        throw new Exception('Import non trouvé');
    }
    
    // Récupérer la configuration des crédits horaires
    $db = Connexion::getInstance()->getPDO();
    $configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
    $config = $configQuery->fetch(PDO::FETCH_ASSOC);
    $creditHeure = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;
    
    // Récupérer les données de la grille
    $etudiants = $grilleAncienne->getEtudiantsByImport($importId);
    $ues = $grilleAncienne->getUEsByImport($importId);
    $ecues = $grilleAncienne->getECUEsByImport($importId);
    $notes = $grilleAncienne->getNotesByImport($importId);
    $resultats = $grilleAncienne->getResultatsByImport($importId);
    
    if (empty($etudiants)) {
        throw new Exception('Aucun étudiant trouvé pour cet import');
    }
    
    // Organiser les données par semestre (si applicable)
    $uesBySemestre = [];
    $ecuesByUE = [];
    
    // Décoder la configuration de mapping pour récupérer les semestres
    $mappingConfig = json_decode($importInfo['mapping_config'], true);
    $deuxSemestres = false; // Par défaut, supposer un seul semestre
    
    foreach ($ues as $ue) {
        // Utiliser le semestre de l'UE ou S1 par défaut
        $semestre = $ue['semestre'] ?? 'S1';
        $uesBySemestre[$semestre][] = $ue;
    }
    
    foreach ($ecues as $ecue) {
        $ecuesByUE[$ecue['ue_id']][] = $ecue;
    }
    
    $notesByEtudiantEcue = [];
    foreach ($notes as $note) {
        $notesByEtudiantEcue[$note['etudiant_id']][$note['ecue_id']] = $note;
    }
    
    $moyennesUE = [];
    $moyennesGenerales = [];
    $moyennesSemestre = [];
    $validationsUE = [];
    $validationsSemestre = [];
    
    foreach ($resultats as $resultat) {
        if ($resultat['type_resultat'] == 'ue') {
            $moyennesUE[$resultat['etudiant_id']][$resultat['ue_id']] = $resultat['moyenne'];
            $validationsUE[$resultat['etudiant_id']][$resultat['ue_id']] = $resultat['est_valide'];
        } elseif ($resultat['type_resultat'] == 'semestre') {
            $moyennesSemestre[$resultat['etudiant_id']][$resultat['semestre']] = $resultat['moyenne'];
            $validationsSemestre[$resultat['etudiant_id']][$resultat['semestre']] = $resultat['est_valide'];
        } elseif ($resultat['type_resultat'] == 'annuel') {
            $moyennesGenerales[$resultat['etudiant_id']] = $resultat;
        }
    }
    
    // Créer le spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Configuration de la page
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
    $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
    
    // Variables pour les positions
    $currentRow = 1;
    $currentCol = 1;
    
    // Titre principal
    $sheet->setCellValue('A1', strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'));
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getRowDimension(1)->setRowHeight(25);
    $currentRow = 3;
    
    // Informations de l'import
    $sheet->setCellValue('A3', "GRILLE DE NOTES - {$importInfo['promotion']}");
    $sheet->setCellValue('A4', "Année Académique: {$importInfo['annee_academique']} - Session: {$importInfo['session']}");
    $sheet->getStyle('A3:A4')->getFont()->setBold(true)->setSize(12);
    $currentRow = 6;
    
    // En-têtes des colonnes fixes
    $sheet->setCellValue('A6', 'N°');
    $sheet->setCellValue('B6', 'MATRICULE');
    $sheet->setCellValue('C6', 'NOMS ET PRÉNOMS');
    
    $currentCol = 4; // Commencer après les colonnes fixes
    
    // Mapping pour les colonnes
    $ecueColMap = []; // Mapping ECUE ID -> colonne Excel
    $moyenneUEColMap = []; // Mapping UE ID -> colonne moyenne UE
    $moyenneSemestreColMap = []; // Mapping semestre -> colonne moyenne semestre
    
    // Traiter chaque semestre
    foreach ($uesBySemestre as $semestre => $uesInSemestre) {
        $semestreStartCol = $currentCol;
        $semestreEcueCount = 0;
        
        // Traiter chaque UE du semestre
        foreach ($uesInSemestre as $ue) {
            $ueStartCol = $currentCol;
            $ecueCount = 0;
            
            // Ajouter les ECUE de cette UE
            if (isset($ecuesByUE[$ue['id']])) {
                foreach ($ecuesByUE[$ue['id']] as $ecue) {
                    $colLetter = Coordinate::stringFromColumnIndex($currentCol);
                    
                    // Pour les grilles anciennes, on n'a que la moyenne finale
                    $sheet->setCellValue($colLetter . '4', $ecue['designation_ecue']);
                    $sheet->setCellValue($colLetter . '5', $ecue['coefficient'] . ' cr');
                    $sheet->setCellValue($colLetter . '6', '/20');
                    
                    // Orientation verticale du texte comme dans export_grille_notes.php
                    $sheet->getStyle($colLetter . '4')->getAlignment()->setTextRotation(90);
                    $sheet->getStyle($colLetter . '4')->getAlignment()->setWrapText(true);
                    
                    $ecueColMap[$ecue['id']] = $currentCol;
                    $currentCol++;
                    $ecueCount++;
                    $semestreEcueCount++;
                }
            }
            
            // Moyenne UE
            if ($ecueCount > 0) {
                $moyColLetter = Coordinate::stringFromColumnIndex($currentCol);
                $sheet->setCellValue($moyColLetter . '5', 'Moy UE');
                $sheet->setCellValue($moyColLetter . '6', '/20');
                $moyenneUEColMap[$ue['id']] = $currentCol;
                $currentCol++;
                $semestreEcueCount++;
                
                // Fusionner pour le nom de l'UE
                $ueStartColLetter = Coordinate::stringFromColumnIndex($ueStartCol);
                $ueEndColLetter = Coordinate::stringFromColumnIndex($currentCol - 1);
                $sheet->mergeCells($ueStartColLetter . '3:' . $ueEndColLetter . '3');
                $sheet->setCellValue($ueStartColLetter . '3', $ue['designation_ue'] . ' (' . $ue['credits'] . ' crédits)');
            }
        }
        
        // Moyenne du semestre si plusieurs UE
        if (count($uesInSemestre) > 1) {
            $moyColLetter = Coordinate::stringFromColumnIndex($currentCol);
            $sheet->setCellValue($moyColLetter . '4', 'Moy ' . $semestre);
            $sheet->setCellValue($moyColLetter . '5', '/20');
            $sheet->setCellValue($moyColLetter . '6', '');
            $moyenneSemestreColMap[$semestre] = $currentCol;
            $currentCol++;
            $semestreEcueCount++;
        }
        
        // Fusionner pour le nom du semestre (si plusieurs semestres)
        if (count($uesBySemestre) > 1 && $semestreEcueCount > 0) {
            $semestreStartColLetter = Coordinate::stringFromColumnIndex($semestreStartCol);
            $semestreEndColLetter = Coordinate::stringFromColumnIndex($currentCol - 1);
            $sheet->mergeCells($semestreStartColLetter . '2:' . $semestreEndColLetter . '2');
            $sheet->setCellValue($semestreStartColLetter . '2', strtoupper($semestre));
        }
    }
    
    // Colonnes finales (moyennes générales)
    $moyGenColLetter = Coordinate::stringFromColumnIndex($currentCol);
    $sheet->setCellValue($moyGenColLetter . '4', 'Moy Générale');
    $sheet->setCellValue($moyGenColLetter . '5', '/20');
    $sheet->setCellValue($moyGenColLetter . '6', '');
    $moyGenCol = $currentCol;
    $currentCol++;
    
    $validationColLetter = Coordinate::stringFromColumnIndex($currentCol);
    $sheet->setCellValue($validationColLetter . '4', 'Validation');
    $sheet->setCellValue($validationColLetter . '5', 'Statut');
    $sheet->setCellValue($validationColLetter . '6', '');
    $validationCol = $currentCol;
    $currentCol++;
    
    $mentionColLetter = Coordinate::stringFromColumnIndex($currentCol);
    $sheet->setCellValue($mentionColLetter . '4', 'Mention');
    $sheet->setCellValue($mentionColLetter . '5', '');
    $sheet->setCellValue($mentionColLetter . '6', '');
    $mentionCol = $currentCol;
    
    // Styliser les en-têtes
    $lastCol = Coordinate::stringFromColumnIndex($currentCol);
    
    // Style des en-têtes de niveaux différents
    $sheet->getStyle('A2:' . $lastCol . '6')->getFont()->setBold(true);
    $sheet->getStyle('A2:' . $lastCol . '6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2:' . $lastCol . '6')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    
    // Couleurs différentes pour chaque niveau d'en-tête
    // Niveau semestre (ligne 2)
    if (count($uesBySemestre) > 1) {
        $sheet->getStyle('A2:' . $lastCol . '2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF4472C4'); // Bleu foncé
        $sheet->getStyle('A2:' . $lastCol . '2')->getFont()->getColor()->setARGB('FFFFFFFF');
    }
    
    // Niveau UE (ligne 3)
    $sheet->getStyle('A3:' . $lastCol . '3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF5B9BD5'); // Bleu moyen
    $sheet->getStyle('A3:' . $lastCol . '3')->getFont()->getColor()->setARGB('FFFFFFFF');
    
    // Niveau ECUE (ligne 4)
    $sheet->getStyle('A4:' . $lastCol . '4')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFBDD7EE'); // Bleu clair
    
    // Sous-en-têtes (lignes 5-6)
    $sheet->getStyle('A5:' . $lastCol . '6')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFF2F2F2'); // Gris clair
    
    // Données des étudiants
    $currentRow = 7;
    $numeroEtudiant = 1;
    
    foreach ($etudiants as $etudiant) {
        $sheet->setCellValue('A' . $currentRow, $numeroEtudiant);
        $sheet->setCellValue('B' . $currentRow, $etudiant['matricule']);
        $sheet->setCellValue('C' . $currentRow, $etudiant['noms']);
        
        // Notes des ECUE (seulement la moyenne finale)
        foreach ($ecueColMap as $ecueId => $colIndex) {
            $noteData = $notesByEtudiantEcue[$etudiant['id']][$ecueId] ?? null;
            
            // Debug: Vérifier l'association note-étudiant-ECUE
            error_log("Export - Etudiant ID: {$etudiant['id']}, ECUE ID: $ecueId, Note: " . 
                     ($noteData ? $noteData['note_finale'] : 'NULL'));
            
            if ($noteData && $noteData['note_finale'] !== null) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $currentRow, 
                    round($noteData['note_finale'], 2));
            } else {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $currentRow, '');
            }
        }
        
        // Moyennes UE
        foreach ($moyenneUEColMap as $ueId => $colIndex) {
            $moyenneUE = $moyennesUE[$etudiant['id']][$ueId] ?? '';
            if ($moyenneUE !== '') {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $currentRow, round($moyenneUE, 2));
            }
        }
        
        // Moyennes par semestre
        foreach ($moyenneSemestreColMap as $semestre => $colIndex) {
            $moyenneSemestre = $moyennesSemestre[$etudiant['id']][$semestre] ?? '';
            if ($moyenneSemestre !== '') {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $currentRow, round($moyenneSemestre, 2));
            }
        }
        
        // Moyenne générale
        $moyenneGenerale = $moyennesGenerales[$etudiant['id']]['moyenne'] ?? '';
        if ($moyenneGenerale !== '') {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($moyGenCol) . $currentRow, round($moyenneGenerale, 2));
        }
        
        // Validation
        $validation = $moyennesGenerales[$etudiant['id']]['est_valide'] ?? false;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($validationCol) . $currentRow, 
            $validation ? 'Validé' : 'Non Validé');
        
        // Mention
        $mention = $moyennesGenerales[$etudiant['id']]['mention'] ?? '';
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($mentionCol) . $currentRow, $mention);
        
        $currentRow++;
        $numeroEtudiant++;
    }
    
    // Ajuster la largeur des colonnes
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(30);
    
    // Largeurs adaptatives pour les colonnes de notes et moyennes
    for ($col = 4; $col <= $currentCol; $col++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(7);
    }
    
    // Bordures pour toute la grille
    $lastRow = $currentRow - 1;
    $sheet->getStyle('A2:' . Coordinate::stringFromColumnIndex($currentCol) . $lastRow)
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    
    // Bordures plus épaisses pour les en-têtes
    $sheet->getStyle('A2:' . Coordinate::stringFromColumnIndex($currentCol) . '6')
        ->getBorders()
        ->getOutline()
        ->setBorderStyle(Border::BORDER_MEDIUM);
    
    // Hauteur des lignes d'en-têtes (comme dans export_grille_notes.php)
    $sheet->getRowDimension(2)->setRowHeight(40);
    $sheet->getRowDimension(3)->setRowHeight(40);
    $sheet->getRowDimension(4)->setRowHeight(150); // Hauteur importante pour le texte en rotation
    $sheet->getRowDimension(5)->setRowHeight(20);
    $sheet->getRowDimension(6)->setRowHeight(20);
    
    // Centrer verticalement les données des étudiants
    $sheet->getStyle('A7:' . Coordinate::stringFromColumnIndex($currentCol) . $lastRow)
        ->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);
    
    // Alignement des notes et moyennes au centre
    for ($col = 4; $col <= $currentCol; $col++) {
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '7:' . 
                        Coordinate::stringFromColumnIndex($col) . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    
    // Nom du fichier
    $fileName = 'Grille_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $importInfo['promotion']) . '_' . 
                $importInfo['annee_academique'] . '_' . $importInfo['session'] . '.xlsx';
    
    // Headers pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    // Générer et envoyer le fichier
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
