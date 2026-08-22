<?php
// Export Excel pour les grilles anciennes importées - Design 100% identique à export_grille_notes.php
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
$modeSimple = isset($_GET['mode_simple']) && $_GET['mode_simple'] == 1;

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
    
    // Déterminer le type de session (logique identique export_releve_notes_ancienne.php)
    $isDeuxiemeSession = stripos($importInfo['session'], 'rattrapage') !== false || 
                         stripos($importInfo['session'], 'deuxième') !== false ||
                         stripos($importInfo['session'], 'deuxieme') !== false ||
                         stripos($importInfo['session'], '2ème') !== false ||
                         stripos($importInfo['session'], '2eme') !== false;
    
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
    
    if (empty($etudiants)) {
        throw new Exception('Aucun étudiant trouvé pour cet import');
    }
    
    // Organiser les données par semestre
    $uesBySemestre = [];
    $ecuesByUE = [];
    $notesByEtudiantEcue = [];
    
    foreach ($ues as $ue) {
        $semestre = $ue['semestre'] ?? 'S1';
        $uesBySemestre[$semestre][] = $ue;
    }
    
    foreach ($ecues as $ecue) {
        $ecuesByUE[$ecue['ue_id']][] = $ecue;
    }
    
    foreach ($notes as $note) {
        $notesByEtudiantEcue[$note['etudiant_id']][$note['ecue_id']] = $note;
    }
    
    // Trier les semestres
    ksort($uesBySemestre);
    $afficherDeuxSemestres = count($uesBySemestre) > 1;
    
    // Créer un nouvel objet Spreadsheet avec des paramètres optimisés (IDENTIQUE export_grille_notes.php)
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('E-Gestion')
        ->setTitle('Grille de Notes')
        ->setSubject('Grille de Notes');

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Grille de Notes');

    // Ajuster la configuration pour un traitement plus rapide (IDENTIQUE)
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 10);

    // Configuration des marges étroites (IDENTIQUE)
    $sheet->getPageMargins()->setTop(0.25);
    $sheet->getPageMargins()->setRight(0.25);
    $sheet->getPageMargins()->setBottom(0.25);
    $sheet->getPageMargins()->setLeft(0.25);
    $sheet->getPageMargins()->setHeader(0.125);
    $sheet->getPageMargins()->setFooter(0.125);

    // Définir les styles (IDENTIQUE export_grille_notes.php)
    if ($modeSimple) {
        // Styles simplifiés pour performance
        $headerStyle = [
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        $subHeaderStyle = [
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        $ecueStyle = [
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
    } else {
        // Styles normaux avec tous les formatages (IDENTIQUE)
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $subHeaderStyle = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $ecueStyle = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
    }

    $row = 1;

    // Ajouter le logo si disponible (sauf en mode simple) - IDENTIQUE
    if (!$modeSimple && !empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            // Insérer le logo dans le document Excel
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo de l\'université');
            $drawing->setPath($logoPath);
            
            // Centrer le logo
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(30);
            $drawing->setHeight(80);
            $drawing->setWorksheet($sheet);
            
            // Ajuster la hauteur de la ligne pour le logo
            $sheet->getRowDimension(1)->setRowHeight(70);
            
            // Décaler les autres informations
            $row = 4;
        }
    }

    // Créer les en-têtes (IDENTIQUE export_grille_notes.php)
    $sheet->setCellValue('A1', !empty($configUniversite['nom']) ? $configUniversite['nom'] : 'E-GESTION UNIVERSITY');
    $sheet->setCellValue('A2', 'GRILLE DE NOTES - ' . ($afficherDeuxSemestres ? 'ANNÉE ACADÉMIQUE' : 'SEMESTRE'));
    $sheet->setCellValue('A3', 'Promotion: ' . $importInfo['promotion']);
    $sheet->setCellValue('A4', 'Session: ' . $importInfo['session']);
    $sheet->setCellValue('A5', 'Année Académique: ' . $importInfo['annee_academique']);

    // Style des titres (IDENTIQUE)
    $sheet->getStyle('A1:Z5')->getFont()->setBold(true);
    $sheet->getStyle('A1:Z5')->getFont()->setSize(11);
    $sheet->getStyle('A1:Z5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Définir les en-têtes du tableau (IDENTIQUE)
    $headerRow1 = 7;
    $headerRow2 = 8;
    $headerRow3 = 9;
    $headerRow4 = 10;
    $startRow = 11;

    $sheet->setCellValue('A' . $headerRow1, '#');
    $sheet->setCellValue('B' . $headerRow1, 'Matricule');
    $sheet->setCellValue('C' . $headerRow1, 'Nom de l\'étudiant');

    // Fusionner les cellules pour les en-têtes des trois premières colonnes (IDENTIQUE)
    $sheet->mergeCells('A' . $headerRow1 . ':A' . $headerRow4);
    $sheet->mergeCells('B' . $headerRow1 . ':B' . $headerRow4);
    $sheet->mergeCells('C' . $headerRow1 . ':C' . $headerRow4);

    // Appliquer le style aux en-têtes (IDENTIQUE)
    $sheet->getStyle('A' . $headerRow1 . ':C' . $headerRow4)->applyFromArray($headerStyle);

    // Définir les largeurs des colonnes (IDENTIQUE)
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(30);

    // Variable pour suivre la colonne actuelle (IDENTIQUE)
    $currentCol = 'D';
    $ecueColumns = [];
    $ueEndColumns = [];
    $moyenneSemestreColumns = [];
    $creditsSemestreColumns = [];
    $pourcentageSemestreColumns = [];

    // Optimisation: en mode simple, les en-têtes de colonnes sont plus simples (IDENTIQUE)
    if ($modeSimple) {
        $sheet->getRowDimension($headerRow3)->setRowHeight(30);
    } else {
        $sheet->getRowDimension($headerRow3)->setRowHeight(150);
    }

    // Construire les en-têtes pour chaque semestre
    foreach ($uesBySemestre as $semestreKey => $uesInSemestre) {
        
        // Calculer le nombre total de colonnes pour ce semestre
        $totalColspan = 0;
        foreach ($uesInSemestre as $ue) {
            $ueId = $ue['id'];
            $ecueCount = count($ecuesByUE[$ueId] ?? []);
            $totalColspan += $ecueCount + 2; // +2 pour moyenne UE et validation
        }
        $totalColspan += $afficherDeuxSemestres ? 3 : 4; // +3/4 pour résultats semestre
        
        // Déterminer la colonne de fin pour le semestre
        $colIndex = Coordinate::columnIndexFromString($currentCol);
        $lastColIndex = $colIndex + $totalColspan - 1;
        $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);
        
        // Fusionner les cellules pour l'en-tête du semestre (IDENTIQUE)
        $sheet->mergeCells($currentCol . $headerRow1 . ':' . $lastCol . $headerRow1);
        $semestreNum = str_replace('S', '', $semestreKey);
        $sheet->setCellValue($currentCol . $headerRow1, 'SEMESTRE ' . $semestreNum);
        $sheet->getStyle($currentCol . $headerRow1 . ':' . $lastCol . $headerRow1)->applyFromArray($headerStyle);
        
        // Pour chaque UE
        foreach ($uesInSemestre as $ue) {
            $ueId = $ue['id'];
            
            // En-tête de l'UE
            $ecueList = $ecuesByUE[$ueId] ?? [];
            $ecueCount = count($ecueList);
            $ueColSpan = $ecueCount + 2; // +2 pour la moyenne et validation
            
            $ueColStart = $currentCol;
            $colIndex = Coordinate::columnIndexFromString($currentCol);
            $lastUeColIndex = $colIndex + $ueColSpan - 1;
            $lastUeCol = Coordinate::stringFromColumnIndex($lastUeColIndex);
            
            // Fusionner les cellules pour l'en-tête de l'UE (IDENTIQUE)
            $sheet->mergeCells($currentCol . $headerRow2 . ':' . $lastUeCol . $headerRow2);
            $sheet->setCellValue($currentCol . $headerRow2, $ue['code_ue'] . ' - ' . $ue['designation_ue']);
            
            if (!$modeSimple) {
                $sheet->getStyle($currentCol . $headerRow2 . ':' . $lastUeCol . $headerRow2)->getAlignment()->setWrapText(true);
            }
            
            $sheet->getStyle($currentCol . $headerRow2 . ':' . $lastUeCol . $headerRow2)->applyFromArray($subHeaderStyle);
            
            // Pour chaque ECUE
            foreach ($ecueList as $ecueItem) {
                $ecueId = $ecueItem['id'];
                $ecueColumns[$ecueId] = $currentCol;
                
                // En-tête de l'ECUE (IDENTIQUE logique export_grille_notes.php)
                if ($modeSimple) {
                    $ecueCode = !empty($ecueItem['code_ecue']) ? $ecueItem['code_ecue'] : substr($ecueItem['designation_ecue'], 0, 10);
                    $sheet->setCellValue($currentCol . $headerRow3, $ecueCode);
                } else {
                    $sheet->setCellValue($currentCol . $headerRow3, $ecueItem['designation_ecue']);
                    $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setWrapText(true);
                    $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
                }
                
                // Crédit de l'ECUE
                $sheet->setCellValue($currentCol . $headerRow4, $ecueItem['coefficient']);
                
                // Appliquer le style ECUE
                $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($ecueStyle);
                
                // Largeur de colonne
                $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 8 : 12);
                
                $currentCol++;
            }
            
            // Colonne moyenne UE
            if ($modeSimple) {
                $sheet->setCellValue($currentCol . $headerRow3, 'Moy');
            } else {
                $sheet->setCellValue($currentCol . $headerRow3, 'Moy UE');
                $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
            }
            $sheet->setCellValue($currentCol . $headerRow4, $ue['credits']);
            $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
            $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
            $ueEndColumns[$ueId] = $currentCol;
            $currentCol++;
            
            // Colonne validation UE
            if ($modeSimple) {
                $sheet->setCellValue($currentCol . $headerRow3, 'Val');
            } else {
                $sheet->setCellValue($currentCol . $headerRow3, 'Valid');
                $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
            }
            $sheet->setCellValue($currentCol . $headerRow4, '-');
            $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
            $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 4 : 6);
            $currentCol++;
        }
        
        // Colonnes résultats du semestre
        // Moyenne semestre
        $moyenneSemestreColumns[$semestreKey] = $currentCol;
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Moy');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Moy Sem');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 6 : 8);
        $currentCol++;
        
        // Crédits semestre
        $creditsSemestreColumns[$semestreKey] = $currentCol;
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Créd');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Crédits');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
        $currentCol++;
        
        // Pourcentage semestre
        $pourcentageSemestreColumns[$semestreKey] = $currentCol;
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, '%');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Pourcentage');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
        $currentCol++;
    }
    
    // Colonnes résultats annuels (si plus d'un semestre) - IDENTIQUE logique
    if ($afficherDeuxSemestres) {
        $syntheseStartCol = $currentCol;
        
        $sheet->mergeCells($currentCol . $headerRow1 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($currentCol) + 3) . $headerRow1);
        $sheet->setCellValue($currentCol . $headerRow1, 'RÉSULTATS ANNUELS');
        $sheet->getStyle($currentCol . $headerRow1 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($currentCol) + 3) . $headerRow1)->applyFromArray($headerStyle);
        
        $sheet->mergeCells($currentCol . $headerRow2 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($currentCol) + 3) . $headerRow2);
        $sheet->setCellValue($currentCol . $headerRow2, 'Synthèse Annuelle');
        $sheet->getStyle($currentCol . $headerRow2 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($currentCol) + 3) . $headerRow2)->applyFromArray($subHeaderStyle);
        
        // Moyenne annuelle
        $moyenneAnnuelleCol = $currentCol;
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Moy');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Moy Ann');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
        $currentCol++;
        
        // Crédits annuels
        $creditsAnnuelCol = $currentCol;
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Créd');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Crédits');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
        $currentCol++;
        
        // Pourcentage annuel
        $pourcentageAnnuelCol = $currentCol;
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, '%');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Pourcentage');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
        $currentCol++;
        
        // Décision
        $decisionCol = $currentCol;
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Déc');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Décision');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow3 . ':' . $currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
        $currentCol++;
    }
    
    // Remplir les données des étudiants
    $currentRow = $startRow;
    foreach ($etudiants as $index => $etudiant) {
        $etudiantId = $etudiant['id'];
        
        // Colonnes fixes
        $sheet->setCellValue('A' . $currentRow, $index + 1);
        $sheet->setCellValue('B' . $currentRow, $etudiant['matricule']);
        $sheet->setCellValue('C' . $currentRow, strtoupper($etudiant['noms']));
        
        // Variables pour calculs annuels
        $moyennesSemestreEtudiant = [];
        $creditsSemestreEtudiant = [];
        $totalCreditsAnnuel = 0;
        $totalCreditsValidesAnnuel = 0;
        $totalMoyennesPonderees = 0;
        
        // Traitement pour chaque semestre
        foreach ($uesBySemestre as $semestreKey => $uesInSemestre) {
            $moyennesUESemestre = [];
            $creditsUESemestre = [];
            $totalCreditsSemestre = 0;
            $totalCreditsValidesSemestre = 0;
            
            // Traitement pour chaque UE
            foreach ($uesInSemestre as $ue) {
                $ueId = $ue['id'];
                $notesUE = [];
                $creditsECUE = [];
                $totalCreditsUE = 0;
                $hasAllNotes = true;
                
                // 🔥 CORRECTION: Calculer d'abord tous les crédits de l'UE (même sans notes)
                if (isset($ecuesByUE[$ueId])) {
                    foreach ($ecuesByUE[$ueId] as $ecue) {
                        $totalCreditsUE += floatval($ecue['coefficient']);
                    }
                }
                
                // Remplir les notes des ECUE
                if (isset($ecuesByUE[$ueId])) {
                    foreach ($ecuesByUE[$ueId] as $ecue) {
                        $ecueId = $ecue['id'];
                        $col = $ecueColumns[$ecueId];
                        
                        if (isset($notesByEtudiantEcue[$etudiantId][$ecueId])) {
                            $note = $notesByEtudiantEcue[$etudiantId][$ecueId]['note_finale'];
                            $sheet->setCellValue($col . $currentRow, number_format($note, 2));
                            $notesUE[] = $note;
                            $creditsECUE[] = floatval($ecue['coefficient']);
                        } else {
                            // Pas de note - cellule vide
                            $sheet->setCellValue($col . $currentRow, '');
                            $hasAllNotes = false;
                        }
                    }
                }
                
                // 🔥 IMPORTANT: Toujours compter les crédits UE dans le total semestre
                $totalCreditsSemestre += $totalCreditsUE;
                
                // Calculer la moyenne UE (seulement si toutes les notes sont présentes)
                $moyenneCol = $ueEndColumns[$ueId];
                $validationCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($moyenneCol) + 1);
                
                if ($hasAllNotes && count($notesUE) > 0) {
                    $moyenneUE = calculerMoyennePonderee($notesUE, $creditsECUE);
                    $sheet->setCellValue($moyenneCol . $currentRow, number_format($moyenneUE, 2));
                    
                    $validation = $moyenneUE >= 10 ? 'V' : 'NV';
                    $sheet->setCellValue($validationCol . $currentRow, $validation);
                    
                    $moyennesUESemestre[] = $moyenneUE;
                    $creditsUESemestre[] = $totalCreditsUE;
                    
                    // 🔥 CORRECTION: Capitalisation au niveau UE entière
                    if ($moyenneUE >= 10) {
                        $totalCreditsValidesSemestre += $totalCreditsUE; // TOUS les crédits de l'UE validée
                    }
                    // Si moyenne UE < 10, aucun crédit de cette UE n'est validé
                } else {
                    // 🔥 CORRECTION: Pas toutes les notes - UE non validée - 0 crédit validé
                    $sheet->setCellValue($moyenneCol . $currentRow, '');
                    $sheet->setCellValue($validationCol . $currentRow, '');
                    // totalCreditsUE est compté dans totalCreditsSemestre mais pas dans totalCreditsValidesSemestre
                }
            }
            
            // Calculer moyenne semestre
            $moyenneSemestreCol = $moyenneSemestreColumns[$semestreKey];
            $creditsSemestreCol = $creditsSemestreColumns[$semestreKey];
            $pourcentageSemestreCol = $pourcentageSemestreColumns[$semestreKey];
            
            // 🔥 LOGIQUE CORRIGÉE : Afficher crédits validés même sans moyenne complète
            $sheet->setCellValue($creditsSemestreCol . $currentRow, $totalCreditsValidesSemestre . '/' . $totalCreditsSemestre);
            
            if (count($moyennesUESemestre) === count($uesInSemestre)) {
                // Toutes les UE ont une moyenne - calculer moyenne semestre
                $moyenneSemestre = calculerMoyennePonderee($moyennesUESemestre, $creditsUESemestre);
                $sheet->setCellValue($moyenneSemestreCol . $currentRow, number_format($moyenneSemestre, 2));
                $sheet->setCellValue($pourcentageSemestreCol . $currentRow, number_format(($moyenneSemestre / 20) * 100, 1) . '%');
                
                $moyennesSemestreEtudiant[] = $moyenneSemestre;
                $creditsSemestreEtudiant[] = $totalCreditsSemestre;
                $totalCreditsAnnuel += $totalCreditsSemestre;
                $totalCreditsValidesAnnuel += $totalCreditsValidesSemestre;
                $totalMoyennesPonderees += $moyenneSemestre * $totalCreditsSemestre;
            } else {
                // Pas toutes les UE - pas de moyenne mais afficher crédits
                $sheet->setCellValue($moyenneSemestreCol . $currentRow, '');
                $sheet->setCellValue($pourcentageSemestreCol . $currentRow, '');
                $totalCreditsAnnuel += $totalCreditsSemestre;
                $totalCreditsValidesAnnuel += $totalCreditsValidesSemestre;
            }
        }
        
        // Résultats annuels (si plus d'un semestre) OU décision pour un semestre unique
        if ($afficherDeuxSemestres) {
            $sheet->setCellValue($creditsAnnuelCol . $currentRow, $totalCreditsValidesAnnuel . '/' . $totalCreditsAnnuel);
            
            if (count($moyennesSemestreEtudiant) === count($uesBySemestre)) {
                // Tous les semestres ont une moyenne
                $moyenneAnnuelle = $totalCreditsAnnuel > 0 ? $totalMoyennesPonderees / $totalCreditsAnnuel : 0;
                
                $sheet->setCellValue($moyenneAnnuelleCol . $currentRow, number_format($moyenneAnnuelle, 2));
                $sheet->setCellValue($pourcentageAnnuelCol . $currentRow, number_format(($moyenneAnnuelle / 20) * 100, 1) . '%');
                
                // Décision - Logique EXACTE du export_releve_notes_ancienne.php
                $decision = '';
                
                // Vérifier s'il y a des notes manquantes pour cet étudiant
                $etudiantNotesManquantes = false;
                foreach ($uesBySemestre as $semestreKey => $uesInSemestre) {
                    foreach ($uesInSemestre as $ue) {
                        $ueId = $ue['id'];
                        if (isset($ecuesByUE[$ueId])) {
                            foreach ($ecuesByUE[$ueId] as $ecue) {
                                $ecueId = $ecue['id'];
                                if (!isset($notesByEtudiantEcue[$etudiantId][$ecueId])) {
                                    $etudiantNotesManquantes = true;
                                    break 3; // Sortir de toutes les boucles
                                }
                            }
                        }
                    }
                }
                
                // Calculer le pourcentage de crédits validés
                $pourcentageCredits = $totalCreditsAnnuel > 0 ? ($totalCreditsValidesAnnuel / $totalCreditsAnnuel) * 100 : 0;
                $estValideGlobal = (!$etudiantNotesManquantes && $totalCreditsValidesAnnuel == $totalCreditsAnnuel && $moyenneAnnuelle >= 10);
                
                // Logique de décision selon le contexte (EXACTE export_releve_notes_ancienne.php)
                if ($afficherDeuxSemestres) {
                    // Pour les résultats annuels
                    if ($etudiantNotesManquantes) {
                        $decision = 'INCOMPLET';
                    } 
                    else if ($isDeuxiemeSession) {
                        // En deuxième session
                        if ($estValideGlobal) {
                            $decision = 'ADMIS SANS RACHAT';
                        } else if ($pourcentageCredits >= 75 && $moyenneAnnuelle >= 10) {
                            $decision = 'ADMIS AVEC RACHAT';
                        } else {
                            $decision = 'AJOURNÉ';
                        }
                    } 
                    else {
                        // En première session
                        if ($estValideGlobal) {
                            $decision = 'ADMIS';
                        } else if ($pourcentageCredits >= 75 && $moyenneAnnuelle >= 10) {
                            $decision = 'AUTORISÉ EN 2ème SESSION';
                        } else {
                            $decision = 'AJOURNÉ';
                        }
                    }
                } else {
                    // Pour les résultats semestriels
                    if ($etudiantNotesManquantes) {
                        $decision = 'INCOMPLET';
                    } else if ($estValideGlobal) {
                        $decision = 'VALIDÉ';
                    } else {
                        $decision = 'NON VALIDÉ';
                    }
                }
                
                $sheet->setCellValue($decisionCol . $currentRow, $decision);
            } else {
                // Pas tous les semestres - pas de moyenne ni décision
                $sheet->setCellValue($moyenneAnnuelleCol . $currentRow, '');
                $sheet->setCellValue($pourcentageAnnuelCol . $currentRow, '');
                $sheet->setCellValue($decisionCol . $currentRow, '');
            }
        }
        
        $currentRow++;
    }
    
    // 🎨 MISE EN FORME DES ÉCHECS (IDENTIQUE export_grille_notes.php)
    $currentRow = $startRow;
    foreach ($etudiants as $etudiant) {
        $etudiantId = $etudiant['id'];
        
        // Formater les notes des ECUE
        foreach ($uesBySemestre as $semestreKey => $uesInSemestre) {
            foreach ($uesInSemestre as $ue) {
                $ueId = $ue['id'];
                
                if (isset($ecuesByUE[$ueId])) {
                    foreach ($ecuesByUE[$ueId] as $ecueItem) {
                        $ecueId = $ecueItem['id'];
                        
                        if (isset($ecueColumns[$ecueId]) && isset($notesByEtudiantEcue[$etudiantId][$ecueId])) {
                            $ecueCol = $ecueColumns[$ecueId];
                            $note = $notesByEtudiantEcue[$etudiantId][$ecueId]['note_finale'];
                            
                            if ($note !== null && $note < 10) {
                                $sheet->getStyle($ecueCol . $currentRow)->applyFromArray([
                                    'fill' => [
                                        'fillType' => Fill::FILL_SOLID,
                                        'startColor' => ['rgb' => 'D9D9D9']
                                    ],
                                    'font' => [
                                        'color' => ['rgb' => '000000']
                                    ]
                                ]);
                            }
                        }
                    }
                }
                
                // Formater les moyennes UE
                if (isset($ueEndColumns[$ueId])) {
                    $moyenneUeCol = $ueEndColumns[$ueId];
                    $validUeCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($moyenneUeCol) + 1);
                    
                    // Récupérer la moyenne UE
                    $cell = $sheet->getCell($moyenneUeCol . $currentRow);
                    $moyenneUE = $cell->getCalculatedValue();
                    if (is_numeric($moyenneUE) && $moyenneUE < 10) {
                        $sheet->getStyle($moyenneUeCol . $currentRow)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'A6A6A6']
                            ],
                            'font' => [
                                'color' => ['rgb' => '000000'],
                                'bold' => true
                            ]
                        ]);
                    }
                    
                    // Formater la validation UE
                    $validCell = $sheet->getCell($validUeCol . $currentRow);
                    $validation = $validCell->getValue();
                    if ($validation == 'V') {
                        $sheet->getStyle($validUeCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                    } else if ($validation == 'NV') {
                        $sheet->getStyle($validUeCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                    }
                }
            }
            
            // Formater les résultats du semestre
            $moyenneSemestreCol = $moyenneSemestreColumns[$semestreKey];
            $creditsSemestreCol = $creditsSemestreColumns[$semestreKey];
            $pourcentageSemestreCol = $pourcentageSemestreColumns[$semestreKey];
            
            // Formater la moyenne du semestre
            $moyenneSemCell = $sheet->getCell($moyenneSemestreCol . $currentRow);
            $moyenneSemestre = $moyenneSemCell->getValue();
            if (is_numeric($moyenneSemestre) && $moyenneSemestre < 10) {
                $sheet->getStyle($moyenneSemestreCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
            }
            
            // Formater les crédits capitalisés
            $creditsCell = $sheet->getCell($creditsSemestreCol . $currentRow);
            $creditsText = $creditsCell->getValue();
            if (strpos($creditsText, '/') !== false) {
                list($creditsValides, $creditsTotal) = explode('/', $creditsText);
                $ratioCredits = $creditsTotal > 0 ? ($creditsValides / $creditsTotal) * 100 : 0;
                if ($ratioCredits >= 75) {
                    $sheet->getStyle($creditsSemestreCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                } else if ($ratioCredits >= 50) {
                    $sheet->getStyle($creditsSemestreCol . $currentRow)->getFont()->getColor()->setRGB('FFA500');
                } else {
                    $sheet->getStyle($creditsSemestreCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                }
            }
            
            // Formater le pourcentage
            $pourcentageCell = $sheet->getCell($pourcentageSemestreCol . $currentRow);
            $pourcentageText = $pourcentageCell->getValue();
            if (strpos($pourcentageText, '%') !== false) {
                $pourcentage = floatval(str_replace('%', '', $pourcentageText));
                $styleArray = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $pourcentage >= 75 ? '008000' : ($pourcentage >= 50 ? 'FFA500' : 'FF0000')]
                    ]
                ];
                $sheet->getStyle($pourcentageSemestreCol . $currentRow)->applyFromArray($styleArray);
            }
        }
        
        // Formater les résultats annuels
        if ($afficherDeuxSemestres) {
            // Formater la moyenne annuelle
            $moyenneAnnCell = $sheet->getCell($moyenneAnnuelleCol . $currentRow);
            $moyenneAnnuelle = $moyenneAnnCell->getValue();
            if (is_numeric($moyenneAnnuelle) && $moyenneAnnuelle < 10) {
                $sheet->getStyle($moyenneAnnuelleCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
            }
            
            // Formater les crédits capitalisés annuels
            $creditsAnnCell = $sheet->getCell($creditsAnnuelCol . $currentRow);
            $creditsAnnText = $creditsAnnCell->getValue();
            if (strpos($creditsAnnText, '/') !== false) {
                list($creditsValides, $creditsTotal) = explode('/', $creditsAnnText);
                $ratioCredits = $creditsTotal > 0 ? ($creditsValides / $creditsTotal) * 100 : 0;
                if ($ratioCredits >= 75) {
                    $sheet->getStyle($creditsAnnuelCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                } else if ($ratioCredits >= 50) {
                    $sheet->getStyle($creditsAnnuelCol . $currentRow)->getFont()->getColor()->setRGB('FFA500');
                } else {
                    $sheet->getStyle($creditsAnnuelCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                }
            }
            
            // Formater le pourcentage annuel
            $pourcentageAnnCell = $sheet->getCell($pourcentageAnnuelCol . $currentRow);
            $pourcentageAnnText = $pourcentageAnnCell->getValue();
            if (strpos($pourcentageAnnText, '%') !== false) {
                $pourcentage = floatval(str_replace('%', '', $pourcentageAnnText));
                $styleArray = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $pourcentage >= 75 ? '008000' : ($pourcentage >= 50 ? 'FFA500' : 'FF0000')]
                    ]
                ];
                $sheet->getStyle($pourcentageAnnuelCol . $currentRow)->applyFromArray($styleArray);
            }
            
            // Formater la décision annuelle
            $decisionCell = $sheet->getCell($decisionCol . $currentRow);
            $decision = $decisionCell->getValue();
            
            if ($decision == 'ADMIS SANS RACHAT' || $decision == 'ADMIS AVEC RACHAT') {
                $sheet->getStyle($decisionCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                $sheet->getStyle($decisionCol . $currentRow)->getFont()->setBold(true);
            } else if ($decision == 'ADMIS AU RATTRAPAGE') {
                $sheet->getStyle($decisionCol . $currentRow)->getFont()->getColor()->setRGB('FFA500');
                $sheet->getStyle($decisionCol . $currentRow)->getFont()->setBold(true);
            } else if ($decision == 'AJOURNÉ' || $decision == 'INCOMPLET') {
                $sheet->getStyle($decisionCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                $sheet->getStyle($decisionCol . $currentRow)->getFont()->setBold(true);
            }
        }
        
        $currentRow++;
    }
    
    // Appliquer les styles finaux aux données (IDENTIQUE)
    $lastCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($currentCol) - 1);
    $dataRange = 'A' . $startRow . ':' . $lastCol . ($currentRow - 1);
    $sheet->getStyle($dataRange)->applyFromArray($dataStyle);
    
    // Style centré pour les données
    $sheet->getStyle('D' . $startRow . ':' . $lastCol . ($currentRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Générer et télécharger le fichier (même technique que export_grille_notes.php)
    $promotion = substr(str_replace(' ', '_', $importInfo['promotion']), 0, 20); // Limiter à 20 caractères
    $session = substr(str_replace(' ', '_', $importInfo['session']), 0, 15); // Limiter à 15 caractères
    $fileName = 'grille_' . $promotion . '_' . $session . '_' . date('Ymd') . '.xlsx'; // Format date raccourci: YYYYmmdd

    // Répertoire de sortie
    $outputDir = dirname(__DIR__) . '/downloads';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Chemin du fichier temporaire
    $filePath = $outputDir . '/' . $fileName;

    // Écrire d'abord sur disque
    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($filePath);

    // Libérer mémoire
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    // Purger les buffers et forcer sans compression
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
    @ini_set('zlib.output_compression', 'Off');

    // Envoyer le fichier au navigateur
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    readfile($filePath);
    unlink($filePath);
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Calculer la moyenne pondérée - IDENTIQUE export_grille_notes.php
 */
function calculerMoyennePonderee($notes, $credits) {
    if (empty($notes) || empty($credits) || count($notes) !== count($credits)) {
        return 0;
    }
    
    $totalPondere = 0;
    $totalCredits = 0;
    
    for ($i = 0; $i < count($notes); $i++) {
        $totalPondere += $notes[$i] * $credits[$i];
        $totalCredits += $credits[$i];
    }
    
    return $totalCredits > 0 ? $totalPondere / $totalCredits : 0;
}
