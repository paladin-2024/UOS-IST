<?php
// Augmenter les limites d'exécution (fonctionne sur certains hébergeurs)
set_time_limit(1200); // 10 minutes
ini_set('memory_limit', '512M');

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
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
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;
$maxEtudiants = isset($_GET['max_etudiants']) ? intval($_GET['max_etudiants']) : 0;
$modeSimple = isset($_GET['mode_simple']) && $_GET['mode_simple'] == 1;
$avecRecours = isset($_GET['avec_recours']) && $_GET['avec_recours'] == 1;

// Récupérer le crédit horaire depuis la configuration de l'université
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
$config = $configQuery->fetch(PDO::FETCH_ASSOC);
$heureCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;


if ($bureauId <= 0 || $promotionId <= 0 || $sessionId <= 0 || $anneeId <= 0 || (!$semestreId && !$afficherDeuxSemestres)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

// Initialiser les objets
$universite = new Universite();
$ecue = new Ecue();
$deliberation = new Deliberation();

$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les informations sur la session
$sessionInfo = $universite->getSessionById($sessionId);
$isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;

// Récupérer les semestres à afficher
$semestres = $deliberation->getSemestresByPromotion($promotionId);
$semestresToShow = $afficherDeuxSemestres ? $semestres : array_values(array_filter($semestres, function ($sem) use ($semestreId) {
    return $sem['idsemestre'] == $semestreId;
}));

// Récupérer les étudiants
if ($isDeuxiemeSession) {
    $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresToShow);
} else {
    $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
}

// Filtrer les étudiants qui ont un recours si demandé
if ($avecRecours) {
    $query_recours = "SELECT DISTINCT matricule FROM recours 
                      WHERE id_annee_acad = :annee AND id_session = :session";
    $stmt_recours = $db->prepare($query_recours);
    $stmt_recours->bindParam(':annee', $anneeId);
    $stmt_recours->bindParam(':session', $sessionId);
    $stmt_recours->execute();
    $matriculesAvecRecours = $stmt_recours->fetchAll(PDO::FETCH_COLUMN);
    
    $etudiants = array_values(array_filter($etudiants, function($etudiant) use ($matriculesAvecRecours) {
        return in_array($etudiant['matricule'], $matriculesAvecRecours);
    }));
}

// Limiter le nombre d'étudiants si nécessaire
if ($maxEtudiants > 0 && count($etudiants) > $maxEtudiants) {
    $etudiants = array_slice($etudiants, 0, $maxEtudiants);
}

// Récupérer les UE et ECUE pour chaque semestre - optimisé pour un chargement plus rapide
$uesBySemestre = [];
$ecuesByUE = [];
foreach ($semestresToShow as $semestre) {
    $semId = $semestre['idsemestre'];
    $uesBySemestre[$semId] = $deliberation->getUEsBySemestre($semId);
    
    foreach ($uesBySemestre[$semId] as $ue) {
        $ueId = $ue['idUE'];
        $ecuesByUE[$ueId] = $ecue->getECUEsByUE2($ueId);
    }
}

// Récupérer les notes, moyennes et validations pour tous les étudiants
$notesByEtudiantEcue = [];
$moyennesUE = [];
$validationsUE = [];
$moyennesSemestre = [];
$validationsSemestre = [];
$moyennesAnnuelles = [];
$validationsAnnuelles = [];

// Collecter tous les matricules pour optimiser les requêtes
$matricules = array_column($etudiants, 'matricule');

// *******************************************************************
// OPTIMISATION: Chargement par lots des notes pour tous les étudiants
// *******************************************************************

foreach ($etudiants as $etudiant) {
    $matricule = $etudiant['matricule'];
    
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        $totalPointsSemestre = 0;
        $totalCreditsSemestre = 0;
        $creditsValidesSemestre = 0;
        $ecueAvecNotesSemestre = 0;
        $totalEcueSemestre = 0;
        $ueAvecMoyenne = 0;
        $totalUE = 0;
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $totalUE++;
            $totalPointsUE = 0;
            $totalCoeffUE = 0;
            $ecueCount = 0;
            $ecueWithNotesCount = 0;
            $ecueWithCompleteNotesCount = 0;
            
            $ueValideeEnPremiereSession = false;
            if ($isDeuxiemeSession) {
                $moyenneUEPremiereSession = $deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
                $ueValideeEnPremiereSession = ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10);
            }
            
            // Récupérer l'ID de la première session pour les comparaisons
            static $premiereSessionId = null;
            if ($premiereSessionId === null && $isDeuxiemeSession) {
                $premiereSessions = $universite->getSessions("Première session");
                if (!empty($premiereSessions)) {
                    $premiereSessionId = $premiereSessions[0]['idsession'];
                }
            }
            
            foreach ($ecuesByUE[$ueId] as $ecueItem) {
                $ecueId = $ecueItem['idECUE'];
                $ecueCount++;
                $totalEcueSemestre++;
                
                if ($isDeuxiemeSession && $premiereSessionId) {
                    // Récupérer les notes des deux sessions
                    $notePremiereSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $premiereSessionId, $anneeId);
                    $noteDeuxiemeSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);

                    // Vérifier si l'ECUE a une note valide en première session
                    $hasValidS1Note = $notePremiereSession && $notePremiereSession['MF'] !== null;
                    $s1NoteGe10 = $hasValidS1Note && floatval($notePremiereSession['MF']) >= 10;

                    // Si l'ECUE avait une note >= 10 en première session, utiliser cette note
                    if ($s1NoteGe10) {
                        $notes = $notePremiereSession;
                    } 
                    // Si l'UE a été validée en S1 ET l'ECUE avait une note en S1 (même < 10), garder cette note
                    else if ($ueValideeEnPremiereSession && $hasValidS1Note) {
                        $notes = $notePremiereSession;
                    } 
                    // Sinon, utiliser la note de deuxième session (ou première session si pas de note S2)
                    else {
                        if ($noteDeuxiemeSession && $noteDeuxiemeSession['MF'] !== null) {
                            $notes = $noteDeuxiemeSession;
                        } else if ($hasValidS1Note) {
                            $notes = $notePremiereSession;
                        } else {
                            $notes = $noteDeuxiemeSession ?: $notePremiereSession;
                        }
                    }
                } else {
                    $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                }
                
                if ($notes) {
                    $notesByEtudiantEcue[$matricule][$ecueId] = $notes;
                    
                    $notesCompletes = false;
                    if ($isDeuxiemeSession) {
                        $notesCompletes = $notes['EX'] !== null;
                    } else {
                        $notesCompletes = $notes['CC'] !== null && $notes['EX'] !== null;
                    }
                    
                    if ($notes['MF'] !== null) {
                        $ecueWithNotesCount++;
                        $ecueAvecNotesSemestre++;
                    }
                    
                    if ($notesCompletes) {
                        $ecueWithCompleteNotesCount++;
                    }
                    
                    $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit;
                    $totalCoeffUE += $coeffECUE;
                    
                    if ($notes['MF'] !== null) {
                        $totalPointsUE += $notes['MF'] * $coeffECUE;
                    }
                } else {
                    $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit;
                    $totalCoeffUE += $coeffECUE;
                }
            }
            
            // Calculer la moyenne de l'UE
            $configMoyenne = $deliberation->getDeliberationConfig($bureauId, $sessionId, $anneeId);
            $calculerAvecNotesVides = isset($configMoyenne['calculer_moyenne_avec_notes_vides']) ? 
                (bool)$configMoyenne['calculer_moyenne_avec_notes_vides'] : false;
            
            if ($ecueCount > 0) {
                // Vérifier si TOUTES les ECUEs ont une note
                $toutesEcuesOntNote = ($ecueWithNotesCount == $ecueCount);
                
                if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                    // En 2ème session, ne calculer la moyenne que si toutes les ECUEs ont une note
                    if ($toutesEcuesOntNote && $totalCoeffUE > 0) {
                        $moyenneUE = $totalPointsUE / $totalCoeffUE;
                        $moyennesUE[$matricule][$ueId] = $moyenneUE;
                        $validationsUE[$matricule][$ueId] = true;
                        $totalPointsSemestre += $totalPointsUE;
                        $ueAvecMoyenne++;
                        $creditsValidesSemestre += $totalCoeffUE;
                    } else {
                        // Notes manquantes - pas de moyenne
                        $moyennesUE[$matricule][$ueId] = null;
                        $validationsUE[$matricule][$ueId] = false;
                    }
                } 
                else if ($calculerAvecNotesVides || $ecueWithCompleteNotesCount == $ecueCount) {
                    if ($totalCoeffUE > 0) {
                        $moyenneUE = $totalPointsUE / $totalCoeffUE;
                        $moyennesUE[$matricule][$ueId] = $moyenneUE;
                        $validationsUE[$matricule][$ueId] = $moyenneUE >= 10;
                        $totalPointsSemestre += $totalPointsUE;
                        $ueAvecMoyenne++;
                        
                        if ($moyenneUE >= 10) {
                            $creditsValidesSemestre += $totalCoeffUE;
                        }
                    }
                } else {
                    $moyennesUE[$matricule][$ueId] = null;
                    $validationsUE[$matricule][$ueId] = false;
                }
                
                $totalCreditsSemestre += $totalCoeffUE;
            }
        }
        
        // Calculer la moyenne du semestre
        if ($totalCreditsSemestre > 0) {
            if ($calculerAvecNotesVides || $ueAvecMoyenne == $totalUE) {
                $moyenneSemestre = $totalPointsSemestre / $totalCreditsSemestre;
                $moyennesSemestre[$matricule][$semId] = $moyenneSemestre;
                
                $pourcentageValidation = ($moyenneSemestre / 20) * 100;
                
                $validationsSemestre[$matricule][$semId] = [
                    'credits_valides' => $creditsValidesSemestre,
                    'credits_total' => $totalCreditsSemestre,
                    'pourcentage' => $pourcentageValidation,
                    'est_valide' => $moyenneSemestre >= 10
                ];
            } else {
                $moyennesSemestre[$matricule][$semId] = null;
                $validationsSemestre[$matricule][$semId] = [
                    'credits_valides' => $creditsValidesSemestre,
                    'credits_total' => $totalCreditsSemestre,
                    'pourcentage' => 0,
                    'est_valide' => false
                ];
            }
        }
    }
    
    // Calculer la moyenne annuelle si nécessaire
    if ($afficherDeuxSemestres && count($semestresToShow) >= 2) {
        $totalPointsAnnee = 0;
        $totalCreditsAnnee = 0;
        $creditsValidesAnnee = 0;
        $semestreAvecMoyenne = 0;
        
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            
            if (isset($validationsSemestre[$matricule][$semId])) {
                $creditsValidesAnnee += $validationsSemestre[$matricule][$semId]['credits_valides'];
                $totalCreditsAnnee += $validationsSemestre[$matricule][$semId]['credits_total'];
                
                if (isset($moyennesSemestre[$matricule][$semId]) && $moyennesSemestre[$matricule][$semId] !== null) {
                    $semestreAvecMoyenne++;
                    $totalPointsAnnee += $moyennesSemestre[$matricule][$semId] * $validationsSemestre[$matricule][$semId]['credits_total'];
                }
            }
        }
        
        if ($totalCreditsAnnee > 0) {
            if ($calculerAvecNotesVides || $semestreAvecMoyenne == count($semestresToShow)) {
                $moyenneAnnuelle = $totalPointsAnnee / $totalCreditsAnnee;
                $moyennesAnnuelles[$matricule] = $moyenneAnnuelle;
                
                $pourcentageValidationAnnee = ($moyenneAnnuelle / 20) * 100;
                
                                $validationsAnnuelles[$matricule] = [
                    'credits_valides' => $creditsValidesAnnee,
                    'credits_total' => $totalCreditsAnnee,
                    'pourcentage' => $pourcentageValidationAnnee,
                    'est_valide' => $moyenneAnnuelle >= 10
                ];
            } else {
                $moyennesAnnuelles[$matricule] = null;
                $validationsAnnuelles[$matricule] = [
                    'credits_valides' => $creditsValidesAnnee,
                    'credits_total' => $totalCreditsAnnee,
                    'pourcentage' => 0,
                    'est_valide' => false
                ];
            }
        }
    }
}

// Créer un nouvel objet Spreadsheet avec des paramètres optimisés
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('E-Gestion')
    ->setTitle('Grille de Notes')
    ->setSubject('Grille de Notes');

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Grille de Notes');

// Ajuster la configuration pour un traitement plus rapide
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 10);

// Configuration des marges étroites
$sheet->getPageMargins()->setTop(0.25);
$sheet->getPageMargins()->setRight(0.25);
$sheet->getPageMargins()->setBottom(0.25);
$sheet->getPageMargins()->setLeft(0.25);
$sheet->getPageMargins()->setHeader(0.125);
$sheet->getPageMargins()->setFooter(0.125);

// Définir les styles (simplifiés en mode simple)
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
    // Styles normaux avec tous les formatages
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

// Ajouter le logo si disponible (sauf en mode simple)
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

// Créer les en-têtes
$sheet->setCellValue('A1', !empty($configUniversite['nom']) ? $configUniversite['nom'] : 'E-GESTION UNIVERSITY');
$sheet->setCellValue('A2', 'GRILLE DE NOTES - ' . ($afficherDeuxSemestres ? 'ANNÉE ACADÉMIQUE' : 'SEMESTRE'));
$sheet->setCellValue('A3', 'Promotion: ' . ($promotionId ? $universite->getPromotionById($promotionId)['designationPromotion'] : ''));
$sessionLabel = ($sessionId ? $sessionInfo['description'] : '');
if ($avecRecours) {
    $sessionLabel .= ' - Recours';
}
$sheet->setCellValue('A4', 'Session: ' . $sessionLabel);
$sheet->setCellValue('A5', 'Année Académique: ' . ($anneeId ? $universite->getAcademicYearById($anneeId)['designation'] : ''));

// Style des titres
$sheet->getStyle('A1:Z5')->getFont()->setBold(true);
$sheet->getStyle('A1:Z5')->getFont()->setSize(11);
$sheet->getStyle('A1:Z5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

// Définir les en-têtes du tableau
$headerRow1 = 7;
$headerRow2 = 8;
$headerRow3 = 9;
$headerRow4 = 10;
$startRow = 11;

$sheet->setCellValue('A' . $headerRow1, '#');
$sheet->setCellValue('B' . $headerRow1, 'Matricule');
$sheet->setCellValue('C' . $headerRow1, 'Nom de l\'étudiant');

// Fusionner les cellules pour les en-têtes des trois premières colonnes
$sheet->mergeCells('A' . $headerRow1 . ':A' . $headerRow4);
$sheet->mergeCells('B' . $headerRow1 . ':B' . $headerRow4);
$sheet->mergeCells('C' . $headerRow1 . ':C' . $headerRow4);

// Appliquer le style aux en-têtes
$sheet->getStyle('A' . $headerRow1 . ':C' . $headerRow4)->applyFromArray($headerStyle);

// Définir les largeurs des colonnes
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(30);

// Variable pour suivre la colonne actuelle
$currentCol = 'D';
$ecueColumns = []; // Pour mémoriser les colonnes de chaque ECUE
$ueStartColumns = []; // Pour mémoriser la première colonne de chaque UE
$ueEndColumns = []; // Pour mémoriser la dernière colonne de chaque UE
$semestreStartColumns = []; // Pour mémoriser la première colonne de chaque semestre
$semestreEndColumns = []; // Pour mémoriser la dernière colonne de chaque semestre

// Optimisation: en mode simple, les en-têtes de colonnes sont plus simples
if ($modeSimple) {
    $sheet->getRowDimension($headerRow3)->setRowHeight(30); // Hauteur réduite en mode simple
} else {
    $sheet->getRowDimension($headerRow3)->setRowHeight(150); // Hauteur normale
}

// Pour chaque semestre
foreach ($semestresToShow as $semestre) {
    $semId = $semestre['idsemestre'];
    $semestreStartColumns[$semId] = $currentCol;
    
    // En-tête du semestre
    $semestreColStart = $currentCol;
    $totalColspan = 0;
    
    // Calculer le nombre total de colonnes pour ce semestre
    foreach ($uesBySemestre[$semId] as $ue) {
        $ueId = $ue['idUE'];
        $ecueCount = count($ecuesByUE[$ueId] ?? []);
        $totalColspan += $ecueCount + 2; // +2 pour la moyenne UE et la validation
    }
    
    // Ajouter 4 colonnes pour les résultats du semestre
    $totalColspan += $afficherDeuxSemestres ? 3 : 4;
    
    // Déterminer la colonne de fin pour le semestre
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $lastColIndex = $colIndex + $totalColspan - 1;
    $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);
    
    // Fusionner les cellules pour l'en-tête du semestre
    $sheet->mergeCells($currentCol . $headerRow1 . ':' . $lastCol . $headerRow1);
    $sheet->setCellValue($currentCol . $headerRow1, 'SEMESTRE ' . $semestre['numeroSemestre']);
    $sheet->getStyle($currentCol . $headerRow1 . ':' . $lastCol . $headerRow1)->applyFromArray($headerStyle);
    
    // Pour chaque UE
    foreach ($uesBySemestre[$semId] as $ue) {
        $ueId = $ue['idUE'];
        $ueStartColumns[$ueId] = $currentCol;
        
        // En-tête de l'UE
        $ecueList = $ecuesByUE[$ueId] ?? [];
        $ecueCount = count($ecueList);
        $ueColSpan = $ecueCount + 2; // +2 pour la moyenne et validation
        
        $ueColStart = $currentCol;
        $colIndex = Coordinate::columnIndexFromString($currentCol);
        $lastUeColIndex = $colIndex + $ueColSpan - 1;
        $lastUeCol = Coordinate::stringFromColumnIndex($lastUeColIndex);
        
        // Fusionner les cellules pour l'en-tête de l'UE
        $sheet->mergeCells($currentCol . $headerRow2 . ':' . $lastUeCol . $headerRow2);
        $sheet->setCellValue($currentCol . $headerRow2, $ue['codeUE'] . ' - ' . $ue['designationUE']);
        
        if (!$modeSimple) {
            $sheet->getStyle($currentCol . $headerRow2 . ':' . $lastUeCol . $headerRow2)->getAlignment()->setWrapText(true);
        }
        
        $sheet->getStyle($currentCol . $headerRow2 . ':' . $lastUeCol . $headerRow2)->applyFromArray($subHeaderStyle);
        
        // Pour chaque ECUE
        foreach ($ecueList as $ecueItem) {
            $ecueId = $ecueItem['idECUE'];
            $ecueColumns[$ecueId] = $currentCol;
            
            // En-tête de l'ECUE (simplifié ou détaillé)
            if ($modeSimple) {
                // En mode simple, afficher juste le code ECUE ou une abbréviation
                $ecueCode = !empty($ecueItem['codeECUE']) ? $ecueItem['codeECUE'] : substr($ecueItem['designationECUE'], 0, 10);
                $sheet->setCellValue($currentCol . $headerRow3, $ecueCode);
            } else {
                // En mode normal, afficher la désignation complète avec rotation
                $sheet->setCellValue($currentCol . $headerRow3, $ecueItem['designationECUE']);
                $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setWrapText(true);
                $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
            }
            
            $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($ecueStyle);
            
            // Crédits de l'ECUE
            $credits = number_format(($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit, 1);
            $sheet->setCellValue($currentCol . $headerRow4, $credits);
            $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
            
            // Définir la largeur de la colonne
            $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
            
            // Passer à la colonne suivante
            $colIndex = Coordinate::columnIndexFromString($currentCol);
            $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
        }
        
        // Colonnes pour la moyenne et validation de l'UE
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Moy');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Moy UE');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
                $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);

        // Calculer la somme des crédits pour cette UE
        $totalCreditsUE = 0;
        foreach ($ecueList as $ecueItem) {
            $totalCreditsUE += ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit;
        }
        $sheet->setCellValue($currentCol . $headerRow4, number_format($totalCreditsUE, 1));
        $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        
        $colIndex = Coordinate::columnIndexFromString($currentCol);
        $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
        
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Val');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Valid');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 4 : 6);
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        
        $ueEndColumns[$ueId] = $currentCol;
        
        $colIndex = Coordinate::columnIndexFromString($currentCol);
        $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    }

    $sheet->getRowDimension($headerRow2)->setRowHeight($modeSimple ? 20 : 40);
    
    // Colonnes pour les résultats du semestre
    $semestreResultatsStart = $currentCol;
    
    // Fusionner les cellules pour l'en-tête des résultats du semestre
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $lastSemResultColIndex = $colIndex + ($afficherDeuxSemestres ? 2 : 3);
    $lastSemResultCol = Coordinate::stringFromColumnIndex($lastSemResultColIndex);
    
    $sheet->mergeCells($currentCol . $headerRow2 . ':' . $lastSemResultCol . $headerRow2);
    $sheet->setCellValue($currentCol . $headerRow2, 'Résultats S' . $semestre['numeroSemestre']);
    $sheet->getStyle($currentCol . $headerRow2 . ':' . $lastSemResultCol . $headerRow2)
        ->applyFromArray(array_merge($subHeaderStyle, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'A9D08E']]]));
    
    // Moyenne du semestre
    if ($modeSimple) {
        $sheet->setCellValue($currentCol . $headerRow3, 'Moy');
    } else {
        $sheet->setCellValue($currentCol . $headerRow3, 'Moy Sem');
        $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
    }
    $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
    $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 6 : 8);
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Crédits capitalisés
    if ($modeSimple) {
        $sheet->setCellValue($currentCol . $headerRow3, 'Créd');
    } else {
        $sheet->setCellValue($currentCol . $headerRow3, 'Crédits');
        $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
    }
    $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
    $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Pourcentage
    if ($modeSimple) {
        $sheet->setCellValue($currentCol . $headerRow3, '%');
    } else {
        $sheet->setCellValue($currentCol . $headerRow3, 'Pourcentage');
        $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
    }
    $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
    $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Décision (seulement si ce n'est pas l'affichage des deux semestres)
    if (!$afficherDeuxSemestres) {
        if ($modeSimple) {
            $sheet->setCellValue($currentCol . $headerRow3, 'Déc');
        } else {
            $sheet->setCellValue($currentCol . $headerRow3, 'Décision');
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
        }
        $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
        $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
        
        $colIndex = Coordinate::columnIndexFromString($currentCol);
        $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    }
    
    $semestreEndColumns[$semId] = $currentCol;
}

// Si on affiche les deux semestres, ajouter les colonnes pour les résultats annuels
if ($afficherDeuxSemestres) {
    $annuelStartCol = $currentCol;
    
    // Fusionner les cellules pour l'en-tête des résultats annuels
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $lastAnnuelColIndex = $colIndex + 3;
    $lastAnnuelCol = Coordinate::stringFromColumnIndex($lastAnnuelColIndex);
    
    $sheet->mergeCells($currentCol . $headerRow1 . ':' . $lastAnnuelCol . $headerRow1);
    $sheet->setCellValue($currentCol . $headerRow1, 'RÉSULTATS ANNUELS');
    $sheet->getStyle($currentCol . $headerRow1 . ':' . $lastAnnuelCol . $headerRow1)
        ->applyFromArray(array_merge($headerStyle, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '70AD47']]]));
    
    $sheet->mergeCells($currentCol . $headerRow2 . ':' . $lastAnnuelCol . $headerRow2);
    $sheet->setCellValue($currentCol . $headerRow2, 'Synthèse Annuelle');
    $sheet->getStyle($currentCol . $headerRow2 . ':' . $lastAnnuelCol . $headerRow2)
        ->applyFromArray(array_merge($subHeaderStyle, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C6E0B4']]]));
    
    // Moyenne annuelle
    if ($modeSimple) {
        $sheet->setCellValue($currentCol . $headerRow3, 'Moy');
    } else {
        $sheet->setCellValue($currentCol . $headerRow3, 'Moy Ann');
        $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
    }
    $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
    $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Crédits capitalisés
    if ($modeSimple) {
        $sheet->setCellValue($currentCol . $headerRow3, 'Créd');
    } else {
        $sheet->setCellValue($currentCol . $headerRow3, 'Crédits');
        $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
    }
    $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
    $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Pourcentage
    if ($modeSimple) {
        $sheet->setCellValue($currentCol . $headerRow3, '%');
    } else {
        $sheet->setCellValue($currentCol . $headerRow3, 'Pourcentage');
        $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
    }
    $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
    $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Décision
    if ($modeSimple) {
        $sheet->setCellValue($currentCol . $headerRow3, 'Déc');
    } else {
        $sheet->setCellValue($currentCol . $headerRow3, 'Décision');
        $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
    }
    $sheet->getStyle($currentCol . $headerRow3)->applyFromArray($subHeaderStyle);
    $sheet->getColumnDimension($currentCol)->setWidth($modeSimple ? 5 : 6);
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->getStyle($currentCol . $headerRow4)->applyFromArray($subHeaderStyle);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
}

// Optimisation: Écriture des données en un seul passage
$dataRows = [];
$rowIndex = 1;

// Préparer les données des étudiants dans un format tabulaire pour une écriture efficace
foreach ($etudiants as $etudiant) {
    $currentRow = $startRow + $rowIndex - 1;
    $matricule = $etudiant['matricule'];
    
    // Informations de base
    $rowData = [
        'A' => $rowIndex,
        'B' => $matricule,
        'C' => $etudiant['noms']
    ];
    
    // Pour chaque semestre
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        
        // Pour chaque UE
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            
            // Pour chaque ECUE
            foreach ($ecuesByUE[$ueId] as $ecueItem) {
                $ecueId = $ecueItem['idECUE'];
                
                if (isset($ecueColumns[$ecueId])) {
                    $ecueCol = $ecueColumns[$ecueId];
                    
                    // Afficher la note si elle existe
                    if (isset($notesByEtudiantEcue[$matricule][$ecueId])) {
                        $note = $notesByEtudiantEcue[$matricule][$ecueId]['MF'];
                        $rowData[$ecueCol] = $note !== null ? number_format($note, 2) : '';
                    }
                }
            }
            
            // Afficher la moyenne et validation de l'UE
            if (isset($ueStartColumns[$ueId]) && isset($ueEndColumns[$ueId])) {
                $colLetter = $ueStartColumns[$ueId];
                $ecueCount = count($ecuesByUE[$ueId] ?? []);
                
                // Trouver la colonne de la moyenne UE (juste après les ECUE)
                $colIndex = Coordinate::columnIndexFromString($colLetter);
                $moyenneUeCol = Coordinate::stringFromColumnIndex($colIndex + $ecueCount);
                
                // Trouver la colonne de validation (après la moyenne)
                $validUeCol = Coordinate::stringFromColumnIndex($colIndex + $ecueCount + 1);
                
                // Afficher la moyenne de l'UE
                if (isset($moyennesUE[$matricule][$ueId])) {
                    $moyenneUE = $moyennesUE[$matricule][$ueId];
                                        $rowData[$moyenneUeCol] = $moyenneUE !== null ? number_format($moyenneUE, 2) : '';
                }
                
                // Afficher la validation de l'UE
                if (isset($validationsUE[$matricule][$ueId])) {
                    $validation = $validationsUE[$matricule][$ueId] ? 'V' : 'NV';
                    $rowData[$validUeCol] = $validation;
                }
            }
        }
        
        // Afficher les résultats du semestre
        if (isset($semestreStartColumns[$semId]) && isset($semestreEndColumns[$semId])) {
            $colLetter = $semestreStartColumns[$semId];
            
            // Trouver la première colonne des résultats du semestre (après la dernière UE)
            $colIndex = 0;
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                if (isset($ueEndColumns[$ueId])) {
                    $lastUeColIndex = Coordinate::columnIndexFromString($ueEndColumns[$ueId]);
                    if ($lastUeColIndex > $colIndex) {
                        $colIndex = $lastUeColIndex;
                    }
                }
            }
            
            $moyenneSemCol = Coordinate::stringFromColumnIndex($colIndex + 1);
            $creditCapCol = Coordinate::stringFromColumnIndex($colIndex + 2);
            $pourcentageCol = Coordinate::stringFromColumnIndex($colIndex + 3);
            $validSemCol = $afficherDeuxSemestres ? null : Coordinate::stringFromColumnIndex($colIndex + 4);
            
            // Afficher la moyenne du semestre
            if (isset($moyennesSemestre[$matricule][$semId])) {
                $moyenneSemestre = $moyennesSemestre[$matricule][$semId];
                $rowData[$moyenneSemCol] = $moyenneSemestre !== null ? number_format($moyenneSemestre, 2) : '';
            }
            
            // Afficher les crédits capitalisés
            if (isset($validationsSemestre[$matricule][$semId])) {
                $creditsValidation = $validationsSemestre[$matricule][$semId];
                $creditsValides = $creditsValidation['credits_valides'];
                $creditsTotal = $creditsValidation['credits_total'];
                
                $rowData[$creditCapCol] = $creditsValides . '/' . $creditsTotal;
                
                // Afficher le pourcentage si disponible
                if (isset($moyennesSemestre[$matricule][$semId]) && $moyennesSemestre[$matricule][$semId] !== null) {
                    $pourcentage = ($moyennesSemestre[$matricule][$semId] / 20) * 100;
                    $rowData[$pourcentageCol] = number_format($pourcentage, 1) . '%';
                }
                
                // Afficher la décision semestrielle (seulement si ce n'est pas l'affichage des deux semestres)
                if (!$afficherDeuxSemestres && $validSemCol) {
                    // Compter les UE validées pour déterminer la décision
                    $ueValidees = 0;
                    $totalUE = 0;
                    
                    foreach ($uesBySemestre[$semId] as $ue) {
                        $ueId = $ue['idUE'];
                        $totalUE++;
                        
                        if (isset($validationsUE[$matricule][$ueId]) && $validationsUE[$matricule][$ueId]) {
                            $ueValidees++;
                        }
                    }
                    
                    $decisionSemestre = 'NV';
                    if ($ueValidees == $totalUE && $totalUE > 0) {
                        $decisionSemestre = 'V';
                    } else if ($ueValidees > 0) {
                        $decisionSemestre = 'VP';
                    }
                    
                    $rowData[$validSemCol] = $decisionSemestre;
                }
            }
        }
    }
    
    // Afficher les résultats annuels si nécessaire
    if ($afficherDeuxSemestres) {
        // Déterminer le dernier semestre affiché
        $dernierSemestre = $semestresToShow[count($semestresToShow) - 1];
        $dernierSemId = $dernierSemestre['idsemestre'];
        
        if (isset($semestreEndColumns[$dernierSemId])) {
            $colLetter = $semestreEndColumns[$dernierSemId];
            $colIndex = Coordinate::columnIndexFromString($colLetter);
            
            $moyenneAnnCol = Coordinate::stringFromColumnIndex($colIndex);
            $creditCapAnnCol = Coordinate::stringFromColumnIndex($colIndex + 1);
            $pourcentageAnnCol = Coordinate::stringFromColumnIndex($colIndex + 2);
            $decisionCol = Coordinate::stringFromColumnIndex($colIndex + 3);
            
            // Afficher la moyenne annuelle
            if (isset($moyennesAnnuelles[$matricule])) {
                $moyenneAnnuelle = $moyennesAnnuelles[$matricule];
                $rowData[$moyenneAnnCol] = $moyenneAnnuelle !== null ? number_format($moyenneAnnuelle, 2) : '';
            }
            
            // Afficher les crédits capitalisés annuels
            if (isset($validationsAnnuelles[$matricule])) {
                $creditsValidation = $validationsAnnuelles[$matricule];
                $creditsValides = $creditsValidation['credits_valides'];
                $creditsTotal = $creditsValidation['credits_total'];
                
                $rowData[$creditCapAnnCol] = $creditsValides . '/' . $creditsTotal;
                
                // Afficher le pourcentage annuel
                if (isset($moyennesAnnuelles[$matricule]) && $moyennesAnnuelles[$matricule] !== null) {
                    $pourcentage = ($moyennesAnnuelles[$matricule] / 20) * 100;
                    $rowData[$pourcentageAnnCol] = number_format($pourcentage, 1) . '%';
                }
                
                // Décision annuelle
                if (isset($moyennesAnnuelles[$matricule]) && $moyennesAnnuelles[$matricule] !== null) {
                    // Logique différente selon la session
                    if ($isDeuxiemeSession) {
                        // En deuxième session
                        if (isset($validationsAnnuelles[$matricule]) && 
                            $validationsAnnuelles[$matricule]['credits_valides'] == $validationsAnnuelles[$matricule]['credits_total']) {
                            $rowData[$decisionCol] = 'ADMIS';
                        } 
                        else if (isset($validationsAnnuelles[$matricule]['pourcentage']) && 
                                $validationsAnnuelles[$matricule]['pourcentage'] >= 75 && 
                                $moyennesAnnuelles[$matricule] >= 10) {
                            $rowData[$decisionCol] = 'RACHAT';
                        } 
                        else {
                            $rowData[$decisionCol] = 'AJOURNÉ';
                        }
                    } 
                    else {
                        // En première session
                        if (isset($validationsAnnuelles[$matricule]) && 
                            $validationsAnnuelles[$matricule]['credits_valides'] == $validationsAnnuelles[$matricule]['credits_total']) {
                            $rowData[$decisionCol] = 'ADMIS';
                        } 
                        else {
                            $rowData[$decisionCol] = 'RATTRAPAGE';
                        }
                    }
                }
            }
        }
    }
    
    $dataRows[] = [
        'row' => $currentRow,
        'data' => $rowData
    ];
    $rowIndex++;
}

// Optimisation: Écrire toutes les données en une seule passe
foreach ($dataRows as $rowInfo) {
    $currentRow = $rowInfo['row'];
    foreach ($rowInfo['data'] as $column => $value) {
        $sheet->setCellValue($column . $currentRow, $value);
    }
    
    // Appliquer le style de base pour la ligne
    $sheet->getStyle('A' . $currentRow . ':' . $currentCol . $currentRow)->applyFromArray($dataStyle);
    
    // Centrer certaines cellules
    $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $currentRow . ':' . $currentCol . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    
    // Appliquer les styles conditionnels pour les moyennes et validations
    // (Le code de formatage peut être simplifié en mode simple pour plus de performance)
    if (!$modeSimple) {
        $matricule = $etudiants[$rowInfo['row'] - $startRow]['matricule'];
        
        // Formater les notes des ECUE
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    
                    if (isset($ecueColumns[$ecueId]) && isset($notesByEtudiantEcue[$matricule][$ecueId])) {
                        $ecueCol = $ecueColumns[$ecueId];
                        $note = $notesByEtudiantEcue[$matricule][$ecueId]['MF'];
                        
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
                
                // Formater les moyennes UE
                if (isset($ueStartColumns[$ueId])) {
                    $colLetter = $ueStartColumns[$ueId];
                    $ecueCount = count($ecuesByUE[$ueId] ?? []);
                    $moyenneUeCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($colLetter) + $ecueCount);
                    $validUeCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($colLetter) + $ecueCount + 1);
                    
                    if (isset($moyennesUE[$matricule][$ueId])) {
                        $moyenneUE = $moyennesUE[$matricule][$ueId];
                        if ($moyenneUE !== null && $moyenneUE < 10) {
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
                    }
                    
                    // Formater la validation UE
                    if (isset($validationsUE[$matricule][$ueId])) {
                        $validation = $validationsUE[$matricule][$ueId] ? 'V' : 'NV';
                        if ($validation == 'V') {
                            $sheet->getStyle($validUeCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                        } else {
                            $sheet->getStyle($validUeCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                        }
                    }
                }
            }
            
            // Formater les résultats du semestre
            if (isset($semestreStartColumns[$semId])) {
                $colIndex = 0;
                foreach ($uesBySemestre[$semId] as $ue) {
                    $ueId = $ue['idUE'];
                    if (isset($ueEndColumns[$ueId])) {
                        $lastUeColIndex = Coordinate::columnIndexFromString($ueEndColumns[$ueId]);
                        if ($lastUeColIndex > $colIndex) {
                            $colIndex = $lastUeColIndex;
                        }
                    }
                }
                
                $moyenneSemCol = Coordinate::stringFromColumnIndex($colIndex + 1);
                $creditCapCol = Coordinate::stringFromColumnIndex($colIndex + 2);
                $pourcentageCol = Coordinate::stringFromColumnIndex($colIndex + 3);
                $validSemCol = $afficherDeuxSemestres ? null : Coordinate::stringFromColumnIndex($colIndex + 4);
                
                // Formater la moyenne du semestre
                if (isset($moyennesSemestre[$matricule][$semId]) && $moyennesSemestre[$matricule][$semId] !== null) {
                    $moyenneSemestre = $moyennesSemestre[$matricule][$semId];
                    if ($moyenneSemestre < 10) {
                        $sheet->getStyle($moyenneSemCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                    }
                }
                
                // Formater les crédits capitalisés
                if (isset($validationsSemestre[$matricule][$semId])) {
                    $creditsValidation = $validationsSemestre[$matricule][$semId];
                    $creditsValides = $creditsValidation['credits_valides'];
                    $creditsTotal = $creditsValidation['credits_total'];
                    
                    $ratioCredits = $creditsTotal > 0 ? ($creditsValides / $creditsTotal) * 100 : 0;
                    if ($ratioCredits >= 75) {
                                                $sheet->getStyle($creditCapCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                    } else if ($ratioCredits >= 50) {
                        $sheet->getStyle($creditCapCol . $currentRow)->getFont()->getColor()->setRGB('FFA500');
                    } else {
                        $sheet->getStyle($creditCapCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                    }
                }
                
                // Formater le pourcentage
                if (isset($moyennesSemestre[$matricule][$semId]) && $moyennesSemestre[$matricule][$semId] !== null) {
                    $pourcentage = ($moyennesSemestre[$matricule][$semId] / 20) * 100;
                    $styleArray = [
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => $pourcentage >= 75 ? '008000' : ($pourcentage >= 50 ? 'FFA500' : 'FF0000')]
                        ]
                    ];
                    $sheet->getStyle($pourcentageCol . $currentRow)->applyFromArray($styleArray);
                }
                
                // Formater la décision semestrielle
                if (!$afficherDeuxSemestres && $validSemCol) {
                    $cell = $sheet->getCell($validSemCol . $currentRow);
                    $decision = $cell->getValue();
                    
                    if ($decision == 'V') {
                        $sheet->getStyle($validSemCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                    } else if ($decision == 'VP') {
                        $sheet->getStyle($validSemCol . $currentRow)->getFont()->getColor()->setRGB('FFA500');
                    } else {
                        $sheet->getStyle($validSemCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                    }
                }
            }
        }
        
        // Formater les résultats annuels
        if ($afficherDeuxSemestres) {
            $dernierSemestre = $semestresToShow[count($semestresToShow) - 1];
            $dernierSemId = $dernierSemestre['idsemestre'];
            
            if (isset($semestreEndColumns[$dernierSemId])) {
                $colLetter = $semestreEndColumns[$dernierSemId];
                $colIndex = Coordinate::columnIndexFromString($colLetter);
                
                $moyenneAnnCol = Coordinate::stringFromColumnIndex($colIndex);
                $creditCapAnnCol = Coordinate::stringFromColumnIndex($colIndex + 1);
                $pourcentageAnnCol = Coordinate::stringFromColumnIndex($colIndex + 2);
                $decisionCol = Coordinate::stringFromColumnIndex($colIndex + 3);
                
                // Formater la moyenne annuelle
                if (isset($moyennesAnnuelles[$matricule]) && $moyennesAnnuelles[$matricule] !== null) {
                    $moyenneAnnuelle = $moyennesAnnuelles[$matricule];
                    if ($moyenneAnnuelle < 10) {
                        $sheet->getStyle($moyenneAnnCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                    }
                }
                
                // Formater les crédits capitalisés annuels
                if (isset($validationsAnnuelles[$matricule])) {
                    $creditsValidation = $validationsAnnuelles[$matricule];
                    $creditsValides = $creditsValidation['credits_valides'];
                    $creditsTotal = $creditsValidation['credits_total'];
                    
                    $ratioCredits = $creditsTotal > 0 ? ($creditsValides / $creditsTotal) * 100 : 0;
                    if ($ratioCredits >= 75) {
                        $sheet->getStyle($creditCapAnnCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                    } else if ($ratioCredits >= 50) {
                        $sheet->getStyle($creditCapAnnCol . $currentRow)->getFont()->getColor()->setRGB('FFA500');
                    } else {
                        $sheet->getStyle($creditCapAnnCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                    }
                }
                
                // Formater le pourcentage annuel
                if (isset($moyennesAnnuelles[$matricule]) && $moyennesAnnuelles[$matricule] !== null) {
                    $pourcentage = ($moyennesAnnuelles[$matricule] / 20) * 100;
                    $styleArray = [
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => $pourcentage >= 75 ? '008000' : ($pourcentage >= 50 ? 'FFA500' : 'FF0000')]
                        ]
                    ];
                    $sheet->getStyle($pourcentageAnnCol . $currentRow)->applyFromArray($styleArray);
                }
                
                // Formater la décision annuelle
                $cell = $sheet->getCell($decisionCol . $currentRow);
                $decision = $cell->getValue();
                
                if ($decision == 'ADMIS' || $decision == 'RACHAT') {
                    $sheet->getStyle($decisionCol . $currentRow)->getFont()->getColor()->setRGB('008000');
                } else if ($decision == 'RATTRAPAGE') {
                    $sheet->getStyle($decisionCol . $currentRow)->getFont()->getColor()->setRGB('FFA500');
                } else if ($decision == 'AJOURNÉ' || $decision == 'INCOMPLET') {
                    $sheet->getStyle($decisionCol . $currentRow)->getFont()->getColor()->setRGB('FF0000');
                }
            }
        }
    }
}



// Ajouter la légende seulement si non mode simple
if (!$modeSimple) {
    $legendRow = $startRow + count($etudiants) + 2;
    $sheet->setCellValue('A' . $legendRow, 'Légende:');
    $sheet->getStyle('A' . $legendRow)->getFont()->setBold(true);
    
    $legendRow++;
    $sheet->setCellValue('A' . $legendRow, 'V = UE Validée (moyenne ≥ 10)');
    $sheet->setCellValue('C' . $legendRow, 'NV = UE Non Validée (moyenne < 10)');
    $sheet->setCellValue('E' . $legendRow, 'VP = Validé Partiellement (au moins une UE validée)');
    
    $legendRow++;
    $sheet->setCellValue('A' . $legendRow, 'Note en rouge = Note < 10');
    $sheet->setCellValue('C' . $legendRow, '% = Pourcentage correspondant à la moyenne (moyenne/20 * 100)');
}

// Optimiser le traitement de la mémoire
$sheet->garbageCollect();

// Configurer le nom du fichier
$fileName = 'Grille_Notes_' . ($promotionId ? str_replace(' ', '_', $universite->getPromotionById($promotionId)['designationPromotion']) : '') . '_' . date('Y-m-d');
if ($avecRecours) {
    $fileName .= '_Recours';
}
if ($modeSimple) {
    $fileName .= '_simple';
}
if ($maxEtudiants > 0) {
    $fileName .= '_limit' . $maxEtudiants;
}
$fileName .= '.xlsx';

// Créer le répertoire de sortie si nécessaire
$outputDir = dirname(__DIR__) . '/downloads';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Optimisation pour l'écriture du fichier Excel
$filePath = $outputDir . '/' . $fileName;

// Utiliser un objet de cache disque pour économiser la mémoire
$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false); // Désactiver le précalcul des formules
$writer->save($filePath);

// Nettoyer les objets majeurs pour libérer de la mémoire
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

// Purger tous les buffers et désactiver la compression
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




