<?php
session_start();
set_time_limit(7200); // 2 heures
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Protection;

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer le crédit horaire depuis la configuration de l'université
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
$config = $configQuery->fetch(PDO::FETCH_ASSOC);
$heureCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;

// Récupérer les paramètres
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;
$avecRecours = isset($_GET['avec_recours']) && $_GET['avec_recours'] == 1;

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
    
    $etudiants = array_filter($etudiants, function($etudiant) use ($matriculesAvecRecours) {
        return in_array($etudiant['matricule'], $matriculesAvecRecours);
    });
}

// Récupérer les UE et ECUE pour chaque semestre
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

// Récupérer la configuration de délibération
$configDeliberation = null;
$calculerAvecNotesVides = false;
if ($bureauId > 0) {
    $configDeliberation = $universite->getDeliberationConfig($bureauId, $sessionId, $anneeId);
    $calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ? 
        (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;
}

// Créer un nouvel objet Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Grille de Notes');

// Protection basique
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setPassword(getenv('EXPORT_SHEET_PASSWORD') ?: 'e-gestion-university');

// Créer une feuille de métadonnées cachée
$metadataSheet = $spreadsheet->createSheet();
$metadataSheet->setTitle('Metadata');
$metadataSheet->getProtection()->setSheet(true);
$metadataSheet->getProtection()->setPassword('e-gestion-university');
$metadataSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

// Stocker les informations de base dans la feuille de métadonnées
$metadataSheet->setCellValue('A1', 'Type');
$metadataSheet->setCellValue('B1', 'ID');
$metadataSheet->setCellValue('C1', 'Cellule');
$metadataSheet->setCellValue('D1', 'Info');

$metadataRow = 2;
$metadataSheet->setCellValue('A' . $metadataRow, 'Session');
$metadataSheet->setCellValue('B' . $metadataRow, $sessionId);
$metadataRow++;

$metadataSheet->setCellValue('A' . $metadataRow, 'Annee');
$metadataSheet->setCellValue('B' . $metadataRow, $anneeId);
$metadataRow++;

$metadataSheet->setCellValue('A' . $metadataRow, 'Bureau');
$metadataSheet->setCellValue('B' . $metadataRow, $bureauId);
$metadataRow++;

$metadataSheet->setCellValue('A' . $metadataRow, 'Promotion');
$metadataSheet->setCellValue('B' . $metadataRow, $promotionId);
$metadataRow++;

// Métadonnées pour l'importation
$spreadsheet->getProperties()
    ->setCompany(!empty($configUniversite['nom']) ? $configUniversite['nom'] : 'E-GESTION UNIVERSITY')
    ->setTitle('Grille de Notes Simplifiée')
    ->setSubject('Encodage des notes')
    ->setDescription('Grille modifiable pour l\'encodage des notes')
    ->setCustomProperty('BureauId', $bureauId)
    ->setCustomProperty('PromotionId', $promotionId)
    ->setCustomProperty('SemestreId', $semestreId)
    ->setCustomProperty('SessionId', $sessionId)
    ->setCustomProperty('AnneeId', $anneeId)
    ->setCustomProperty('DeuxSemestres', $afficherDeuxSemestres ? '1' : '0')
    ->setCustomProperty('FileToken', md5($bureauId . $promotionId . $sessionId . $anneeId . time()));

$row = 1;

// En-têtes simplifiés
$sheet->setCellValue('A' . $row, !empty($configUniversite['nom']) ? $configUniversite['nom'] : 'E-GESTION UNIVERSITY');
$row++;

$sheet->setCellValue('A' . $row, 'GRILLE SIMPLIFIÉE DE NOTES - ' . ($afficherDeuxSemestres ? 'ANNÉE ACADÉMIQUE' : 'SEMESTRE'));
$row++;

$sheet->setCellValue('A' . $row, 'Promotion: ' . ($promotionId ? $universite->getPromotionById($promotionId)['designationPromotion'] : ''));
$row++;

$sheet->setCellValue('A' . $row, 'Session: ' . ($sessionId ? $sessionInfo['description'] : ''));
$row++;

$sheet->setCellValue('A' . $row, 'Année Académique: ' . ($anneeId ? $universite->getAcademicYearById($anneeId)['designation'] : ''));
$row++;


// Après la création des en-têtes de base, ajoutez ces lignes pour définir les largeurs des colonnes :

// Définir les largeurs des colonnes
$sheet->getColumnDimension('A')->setWidth(5);   // Numéro
$sheet->getColumnDimension('B')->setWidth(20);  // Matricule (augmenté de 15 à 20)
$sheet->getColumnDimension('C')->setWidth(40);  // Nom (augmenté de 30 à 40)

// Style simple pour les bordures (à ajouter après la création du spreadsheet)
$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];


// Instructions d'utilisation simples
$row += 1;
$sheet->setCellValue('A' . $row, 'INSTRUCTIONS: Modifiez uniquement les cellules des notes et moyennes UE.');
$row++;

// Définir les en-têtes du tableau
$headerRow1 = $row + 1;
$headerRow2 = $headerRow1 + 1;
$headerRow3 = $headerRow2 + 1;
$headerRow4 = $headerRow3 + 1;
$headerRow5 = $headerRow4 + 1; // Ligne pour stocker les IDs
$startRow = $headerRow5 + 1;

$sheet->setCellValue('A' . $headerRow1, '#');
$sheet->setCellValue('B' . $headerRow1, 'Matricule');
$sheet->setCellValue('C' . $headerRow1, 'Nom de l\'étudiant');

// Variable pour suivre la colonne actuelle
$currentCol = 'D';
$ecueColumns = [];
$ueStartColumns = [];
$ueEndColumns = [];
$semestreStartColumns = [];
$semestreEndColumns = [];

// Pour chaque semestre
foreach ($semestresToShow as $semestre) {
    $semId = $semestre['idsemestre'];
    $semestreStartColumns[$semId] = $currentCol;

    // Calculer le nombre total de colonnes pour ce semestre
    $totalColspan = 0;
    foreach ($uesBySemestre[$semId] as $ue) {
        $ueId = $ue['idUE'];
        $ecueCount = count($ecuesByUE[$ueId] ?? []);
        $totalColspan += $ecueCount + 2; // +2 pour la moyenne UE et la validation
    }
    $totalColspan += 4; // Ajouter 4 colonnes pour les résultats du semestre
    
    // Déterminer la colonne de fin pour le semestre
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $lastColIndex = $colIndex + $totalColspan - 1;
    $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);
    
    // En-tête du semestre
    $sheet->setCellValue($currentCol . $headerRow1, 'SEMESTRE ' . $semestre['numeroSemestre']);
    $semestreEndColumns[$semId] = $lastCol;
    
    // Pour chaque UE
    foreach ($uesBySemestre[$semId] as $ue) {
        $ueId = $ue['idUE'];
        $ueStartColumns[$ueId] = $currentCol;
        
        // En-tête de l'UE
        $ecueList = $ecuesByUE[$ueId] ?? [];
        $ecueCount = count($ecueList);
        $ueColSpan = $ecueCount + 2; // +2 pour la moyenne et validation
        
        $colIndex = Coordinate::columnIndexFromString($currentCol);
        $lastUeColIndex = $colIndex + $ueColSpan - 1;
        $lastUeCol = Coordinate::stringFromColumnIndex($lastUeColIndex);
        
        // En-tête de l'UE
        $sheet->setCellValue($currentCol . $headerRow2, $ue['codeUE'] . ' - ' . $ue['designationUE']);
        
        // Pour chaque ECUE
        foreach ($ecueList as $ecueItem) {
            $ecueId = $ecueItem['idECUE'];
            $ecueColumns[$ecueId] = $currentCol;
            
            // En-tête de l'ECUE avec rotation du texte
            $sheet->setCellValue($currentCol . $headerRow3, $ecueItem['designationECUE']);
            
            // Ajouter uniquement la rotation du texte pour les ECUE
            $sheet->getStyle($currentCol . $headerRow3)->getAlignment()->setTextRotation(90);
            
            // Ajuster la hauteur de ligne pour accommoder le texte roté
            $sheet->getRowDimension($headerRow3)->setRowHeight(150);
            
            // Ajouter l'ID de l'ECUE dans la ligne des IDs
            $sheet->setCellValue($currentCol . $headerRow5, $ecueId);
            
            // Crédits de l'ECUE
            $credits = number_format(($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit, 1);
            $sheet->setCellValue($currentCol . $headerRow4, $credits);
            
            // Définir une largeur appropriée pour la colonne
            $sheet->getColumnDimension($currentCol)->setWidth(8);
            
            // Passer à la colonne suivante
            $colIndex = Coordinate::columnIndexFromString($currentCol);
            $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
        }
        
        // Colonnes pour la moyenne et validation de l'UE
        $sheet->setCellValue($currentCol . $headerRow3, 'Moy UE');
        
        // Calculer la somme des crédits pour cette UE
        $totalCreditsUE = 0;
        foreach ($ecueList as $ecueItem) {
            $totalCreditsUE += ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit;
        }
        $sheet->setCellValue($currentCol . $headerRow4, number_format($totalCreditsUE, 1));
        
        // Ajouter l'ID de l'UE dans la ligne des IDs
        $sheet->setCellValue($currentCol . $headerRow5, $ueId);
        
                $colIndex = Coordinate::columnIndexFromString($currentCol);
        $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
        
        $sheet->setCellValue($currentCol . $headerRow3, 'Valid');
        $sheet->setCellValue($currentCol . $headerRow4, '-');
        
        // Ajouter une cellule vide dans la ligne des IDs pour la validation
        $sheet->setCellValue($currentCol . $headerRow5, '');
        
        $ueEndColumns[$ueId] = $currentCol;
        
        $colIndex = Coordinate::columnIndexFromString($currentCol);
        $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    }
    
    // Colonnes pour les résultats du semestre
    $resultsSemColStart = $currentCol;
    
    // Moyenne Semestre
    $sheet->setCellValue($currentCol . $headerRow3, 'Moy Sem');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, '' . $semId);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Crédits
    $sheet->setCellValue($currentCol . $headerRow3, 'Crédits');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, '' . $semId);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Pourcentage
    $sheet->setCellValue($currentCol . $headerRow3, '%');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, '' . $semId);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Décision
    $sheet->setCellValue($currentCol . $headerRow3, 'Décision');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, '' . $semId);
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // En-tête pour les résultats du semestre
    $resultsSemColEnd = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($resultsSemColStart) + 3);
    $sheet->setCellValue($resultsSemColStart . $headerRow2, 'Résultats S' . $semestre['numeroSemestre']);
}

// Si on affiche les résultats annuels (pour deux semestres)
if ($afficherDeuxSemestres) {
    $resultsAnnColStart = $currentCol;
    
    // Moyenne Annuelle
    $sheet->setCellValue($currentCol . $headerRow3, 'Moy Ann');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, '');
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Crédits Annuels
    $sheet->setCellValue($currentCol . $headerRow3, 'Crédits');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, '');
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Pourcentage Annuel
    $sheet->setCellValue($currentCol . $headerRow3, '%');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, '');
    
    $colIndex = Coordinate::columnIndexFromString($currentCol);
    $currentCol = Coordinate::stringFromColumnIndex($colIndex + 1);
    
    // Décision Annuelle
    $sheet->setCellValue($currentCol . $headerRow3, 'Décision');
    $sheet->setCellValue($currentCol . $headerRow4, '-');
    $sheet->setCellValue($currentCol . $headerRow5, 'DEC_ANN');
    
    // En-tête pour les résultats annuels
    $resultsAnnColEnd = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($resultsAnnColStart) + 3);
    $sheet->setCellValue($resultsAnnColStart . $headerRow2, 'Résultats Annuels');
}

// Ajouter les données des étudiants
$rowIndex = 0;
foreach ($etudiants as $etudiant) {
    $currentRow = $startRow + $rowIndex;
    $matricule = $etudiant['matricule'];
    
    // Numéro, matricule et nom de l'étudiant
    $sheet->setCellValue('A' . $currentRow, $rowIndex + 1);
    $sheet->setCellValue('B' . $currentRow, $matricule);
    $sheet->setCellValue('C' . $currentRow, $etudiant['noms']);
    
    // Pour chaque semestre, ajouter les données
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        
        // Pour chaque UE du semestre
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            
            // Récupérer les colonnes de début et fin d'UE pour les formules
            $ueStartCol = $ueStartColumns[$ueId];
            $ueEndCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($ueEndColumns[$ueId]) - 2);
            
            // Pour chaque ECUE de l'UE
            foreach ($ecuesByUE[$ueId] as $ecueItem) {
                $ecueId = $ecueItem['idECUE'];
                $ecueCol = $ecueColumns[$ecueId];

                // Ajouter l'ECUE dans la feuille de métadonnées
                $metadataSheet->setCellValue('A' . $metadataRow, 'ECUE');
                $metadataSheet->setCellValue('B' . $metadataRow, $ecueId);
                $metadataSheet->setCellValue('C' . $metadataRow, $ecueCol);
                $metadataSheet->setCellValue('D' . $metadataRow, $ecueItem['designationECUE']);
                $metadataRow++;
                
                // Récupérer la note actuelle
                $cote = null;
                $ueValideeEnPremiereSession = false;

                // Récupérer l'ID de la première session pour les comparaisons
                static $premiereSessionIdMod = null;
                if ($premiereSessionIdMod === null && $isDeuxiemeSession) {
                    $premiereSessions = $universite->getSessions("Première session");
                    if (!empty($premiereSessions)) {
                        $premiereSessionIdMod = $premiereSessions[0]['idsession'];
                    }
                }

                if ($isDeuxiemeSession && $premiereSessionIdMod) {
                    // Vérifier si l'UE a été validée en première session
                    $moyenneUEPremiereSession = $deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
                    $ueValideeEnPremiereSession = ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10);
                    
                    // Récupérer les notes des deux sessions
                    $notePremiereSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $premiereSessionIdMod, $anneeId);
                    $noteDeuxiemeSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);

                    // Vérifier si l'ECUE a une note valide en première session
                    $hasValidS1Note = $notePremiereSession && $notePremiereSession['MF'] !== null;
                    $s1NoteGe10 = $hasValidS1Note && floatval($notePremiereSession['MF']) >= 10;

                    // Si l'ECUE avait une note >= 10 en première session, utiliser cette note
                    if ($s1NoteGe10) {
                        $cote = $notePremiereSession;
                    } 
                    // Si l'UE a été validée en S1 ET l'ECUE avait une note en S1 (même < 10), garder cette note
                    else if ($ueValideeEnPremiereSession && $hasValidS1Note) {
                        $cote = $notePremiereSession;
                    } 
                    // Sinon, utiliser la note de deuxième session (ou première session si pas de note S2)
                    else {
                        if ($noteDeuxiemeSession && $noteDeuxiemeSession['MF'] !== null) {
                            $cote = $noteDeuxiemeSession;
                        } else if ($hasValidS1Note) {
                            $cote = $notePremiereSession;
                        } else {
                            $cote = $noteDeuxiemeSession ?: $notePremiereSession;
                        }
                    }
                } else {
                    // Pour la première session, récupérer les notes normalement
                    $cote = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $matricule);
                }

                $mf = isset($cote['MF']) && $cote['MF'] !== null ? $cote['MF'] : '';

                // Afficher la note finale
                $sheet->setCellValue($ecueCol . $currentRow, $mf);

                // Ajouter une information dans les métadonnées pour indiquer si la note vient de la première session
                $metadataSheet->setCellValue('A' . $metadataRow, 'Note');
                $metadataSheet->setCellValue('B' . $metadataRow, $ecueId);
                $metadataSheet->setCellValue('C' . $metadataRow, $ecueCol . $currentRow);
                $metadataSheet->setCellValue('D' . $metadataRow, $matricule);
                $metadataSheet->setCellValue('E' . $metadataRow, $ueValideeEnPremiereSession ? 'PremièreSession' : 'SessionActuelle');
                $metadataRow++;

                // Déverrouiller la cellule pour modification
                $sheet->getStyle($ecueCol . $currentRow)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
            }
            
            // Calculer la moyenne de l'UE
            $moyenneUECol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($ueEndCol) + 1);
            
            // Déverrouiller la cellule de moyenne UE
            $sheet->getStyle($moyenneUECol . $currentRow)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

            // Calculer la moyenne de l'UE
            if ($isDeuxiemeSession && $premiereSessionIdMod) {
                $moyenneUEPremiereSession = $deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
                $ueValideeEnS1 = ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10);
                
                // Recalculer la moyenne UE avec les notes actuelles (S1 + S2)
                $totalPoints = 0;
                $totalCredits = 0;
                $ecuesAvecNote = 0;
                $totalEcues = count($ecuesByUE[$ueId]);
                
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueIdCalc = $ecueItem['idECUE'];
                    $credits = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit;
                    $totalCredits += $credits;
                    
                    // Récupérer les notes des deux sessions
                    $noteS1 = $deliberation->getNotesEtudiantECUE($matricule, $ecueIdCalc, $premiereSessionIdMod, $anneeId);
                    $noteS2 = $deliberation->getNotesEtudiantECUE($matricule, $ecueIdCalc, $sessionId, $anneeId);
                    
                    // Déterminer quelle note utiliser
                    $hasValidS1 = $noteS1 && $noteS1['MF'] !== null;
                    $hasValidS2 = $noteS2 && $noteS2['MF'] !== null;
                    $s1Ge10 = $hasValidS1 && floatval($noteS1['MF']) >= 10;
                    
                    $noteToUse = null;
                    if ($s1Ge10) {
                        $noteToUse = $noteS1['MF'];
                    } else if ($ueValideeEnS1 && $hasValidS1) {
                        // Si UE validée en S1 et ECUE a une note S1, garder S1
                        $noteToUse = $noteS1['MF'];
                    } else if ($hasValidS2) {
                        $noteToUse = $noteS2['MF'];
                    } else if ($hasValidS1) {
                        $noteToUse = $noteS1['MF'];
                    }
                    
                    if ($noteToUse !== null) {
                        $totalPoints += floatval($noteToUse) * $credits;
                        $ecuesAvecNote++;
                    }
                }
                
                // Ne calculer la moyenne que si TOUTES les ECUEs ont une note
                $toutesEcuesOntNote = ($ecuesAvecNote == $totalEcues);
                $moyenneUE = ($toutesEcuesOntNote && $totalCredits > 0) ? $totalPoints / $totalCredits : '';
                $sheet->setCellValue($moyenneUECol . $currentRow, $moyenneUE !== '' ? number_format($moyenneUE, 2) : '');
                
                // Ajouter une note dans les métadonnées si UE était validée en S1
                if ($ueValideeEnS1) {
                    $metadataSheet->setCellValue('A' . $metadataRow, 'UE_Validee_S1');
                    $metadataSheet->setCellValue('B' . $metadataRow, $ueId);
                    $metadataSheet->setCellValue('C' . $metadataRow, $moyenneUECol . $currentRow);
                    $metadataSheet->setCellValue('D' . $metadataRow, $matricule);
                    $metadataRow++;
                }
            } else {
                // Calculer la moyenne de l'UE
                $totalPoints = 0;
                $totalCredits = 0;
                $hasValidNotes = false;
                $hasAllNotes = true;
                
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    $credits = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit;
                    $totalCredits += $credits;
                    
                    $cote = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $matricule);
                    if (isset($cote['MF']) && $cote['MF'] !== null) {
                        $totalPoints += $cote['MF'] * $credits;
                        $hasValidNotes = true;
                    } else {
                        $hasAllNotes = false;
                    }
                }
                
                // Calculer la moyenne uniquement si tous les ECUE ont des notes ou si calculerAvecNotesVides est activé
                $moyenneUE = '';
                if ($hasValidNotes && ($hasAllNotes || $calculerAvecNotesVides) && $totalCredits > 0) {
                    $moyenneUE = $totalPoints / $totalCredits;
                    $sheet->setCellValue($moyenneUECol . $currentRow, number_format($moyenneUE, 2));
                } else {
                    $sheet->setCellValue($moyenneUECol . $currentRow, '');
                }
            }
            
            // Formule pour la validation UE (V ou NV)
            $validationUECol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($moyenneUECol) + 1);
            $validationFormula = "=IF($moyenneUECol$currentRow=\"\",\"\",IF($moyenneUECol$currentRow>=10,\"V\",\"NV\"))";
            $sheet->setCellValue($validationUECol . $currentRow, $validationFormula);
        }
        
        // Calculer les résultats du semestre
        $moyenneSemCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($semestreEndColumns[$semId]) - 3
        );
        
        // Formule pour la moyenne du semestre
        $moyenneSemFormula = "=ROUND(";
        $moyenneUECols = [];
        $creditsCols = [];
        
                foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $moyenneUECol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($ueEndColumns[$ueId]) - 1);
            $moyenneUECols[] = "IF(ISBLANK($moyenneUECol$currentRow),0,$moyenneUECol$currentRow*$moyenneUECol$headerRow4)";
            $creditsCols[] = "IF(ISBLANK($moyenneUECol$currentRow),0,$moyenneUECol$headerRow4)";
        }
        
        $moyenneSemFormula .= "SUM(" . implode(",", $moyenneUECols) . ")/IF(SUM(" . implode(",", $creditsCols) . ")=0,1,SUM(" . implode(",", $creditsCols) . ")),2)";
        $sheet->setCellValue($moyenneSemCol . $currentRow, $moyenneSemFormula);
        
        // Crédits obtenus - formule
        $creditsSemCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($moyenneSemCol) + 1
        );
        
        // Formule pour calculer les crédits validés
        $creditsValidesFormula = "=CONCATENATE(";
        $creditsValidesArray = [];
        $creditsTotalArray = [];
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $validationUECol = $ueEndColumns[$ueId];
            $moyenneUECol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($validationUECol) - 1);
            
            $creditsValidesArray[] = "IF($validationUECol$currentRow=\"V\",$moyenneUECol$headerRow4,0)";
            $creditsTotalArray[] = "$moyenneUECol$headerRow4";
        }
        
        $creditsValidesFormula .= "SUM(" . implode(",", $creditsValidesArray) . "),\"/\",SUM(" . implode(",", $creditsTotalArray) . "))";
        $sheet->setCellValue($creditsSemCol . $currentRow, $creditsValidesFormula);
        
        // Pourcentage - formule
        $pourcentageSemCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($creditsSemCol) + 1
        );
        
        $pourcentageFormula = "=ROUND(($moyenneSemCol$currentRow/20)*100,2)";
        $sheet->setCellValue($pourcentageSemCol . $currentRow, $pourcentageFormula);
        
        // Décision - formule automatique basée sur la logique d'export_grille_notes.php
        $decisionSemCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($pourcentageSemCol) + 1
        );

        // Calculer la décision du semestre de manière simple
        $ueValidees = 0;
        $totalUE = count($uesBySemestre[$semId]);
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $moyenneUECol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($ueEndColumns[$ueId]) - 1);
            $moyenneUE = $sheet->getCell($moyenneUECol . $currentRow)->getValue();
            
            if ($moyenneUE !== '' && is_numeric($moyenneUE) && floatval($moyenneUE) >= 10) {
                $ueValidees++;
            }
        }
        
        $decisionSemestre = 'NV';
        if ($totalUE > 0) {
            if ($ueValidees == $totalUE) {
                $decisionSemestre = 'V';
            } elseif ($ueValidees > 0) {
                $decisionSemestre = 'VP';
            }
        }
        
        $sheet->setCellValue($decisionSemCol . $currentRow, $decisionSemestre);
    }
    
    // Si on affiche deux semestres, calculer les résultats annuels
    if ($afficherDeuxSemestres) {
        // Moyenne annuelle - formule
        $moyenneAnnCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($semestreEndColumns[end($semestresToShow)['idsemestre']]) + 1
        );
        
        // Formule pour la moyenne annuelle (restaurée)
        $moyenneAnnFormula = "=IF(";
        $conditionsArray = [];
        $moyenneCalculArray = [];
        $creditsCalculArray = [];
        
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            $moyenneSemCol = Coordinate::stringFromColumnIndex(
                Coordinate::columnIndexFromString($semestreEndColumns[$semId]) - 3
            );
            $conditionsArray[] = "ISNUMBER($moyenneSemCol$currentRow)";
        }
        
        // Calculer les crédits totaux pour chaque semestre
        $creditsBySemestre = [];
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            $creditsTotal = 0;
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $creditsTotal += ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/$heureCredit;
                }
            }
            $creditsBySemestre[$semId] = $creditsTotal;
        }
        
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            $moyenneSemCol = Coordinate::stringFromColumnIndex(
                Coordinate::columnIndexFromString($semestreEndColumns[$semId]) - 3
            );
            $creditsValue = $creditsBySemestre[$semId];
            $moyenneCalculArray[] = "$moyenneSemCol$currentRow*$creditsValue";
            $creditsCalculArray[] = "$creditsValue";
        }
        
        $moyenneAnnFormula .= "AND(" . implode(",", $conditionsArray) . "),";
        $moyenneAnnFormula .= "ROUND((" . implode("+", $moyenneCalculArray) . ")/(" . implode("+", $creditsCalculArray) . "),2),\"\")";
        $sheet->setCellValue($moyenneAnnCol . $currentRow, $moyenneAnnFormula);
        
        // Crédits annuels - formule restaurée
        $creditsAnnCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($moyenneAnnCol) + 1
        );
        
        // Formule pour les crédits annuels
        $creditsAnnFormula = "=CONCATENATE(";
        $creditsValidesAnnArray = [];
        $creditsTotalAnnArray = [];
        
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            
            $creditsValidesForSem = [];
            $creditsTotalForSem = [];
            
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $validationUECol = $ueEndColumns[$ueId];
                $moyenneUECol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($validationUECol) - 1);
                
                $creditsValidesForSem[] = "IF($validationUECol$currentRow=\"V\",$moyenneUECol$headerRow4,0)";
                $creditsTotalForSem[] = "$moyenneUECol$headerRow4";
            }
            
            if (!empty($creditsValidesForSem)) {
                $creditsValidesAnnArray[] = "SUM(" . implode(",", $creditsValidesForSem) . ")";
                $creditsTotalAnnArray[] = "SUM(" . implode(",", $creditsTotalForSem) . ")";
            }
        }
        
        $creditsAnnFormula .= "SUM(" . implode(",", $creditsValidesAnnArray) . "),\"/\",SUM(" . implode(",", $creditsTotalAnnArray) . "))";
        $sheet->setCellValue($creditsAnnCol . $currentRow, $creditsAnnFormula);
        
        // Pourcentage annuel - formule restaurée
        $pourcentageAnnCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($creditsAnnCol) + 1
        );
        
        $pourcentageAnnFormula = "=IF($moyenneAnnCol$currentRow=\"\",\"\",ROUND(($moyenneAnnCol$currentRow/20)*100,1)&\"%\")";
        $sheet->setCellValue($pourcentageAnnCol . $currentRow, $pourcentageAnnFormula);
        
        // Décision annuelle - calculée de manière simple
        $decisionAnnCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($pourcentageAnnCol) + 1
        );

        // Décision annuelle - utiliser une formule Excel basée sur la logique d'export_grille_notes.php
        $decisionAnnCol = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($pourcentageAnnCol) + 1
        );

        // Créer une formule Excel pour la décision annuelle
        if ($isDeuxiemeSession) {
            // En deuxième session: ADMIS si 100% crédits, RACHAT si moy>=10 et >=75% crédits, sinon AJOURNÉ
            $decisionFormula = "=IF($moyenneAnnCol$currentRow=\"\",\"\",";
            $decisionFormula .= "IF(LEFT($creditsAnnCol$currentRow,FIND(\"/\",$creditsAnnCol$currentRow)-1)=MID($creditsAnnCol$currentRow,FIND(\"/\",$creditsAnnCol$currentRow)+1,LEN($creditsAnnCol$currentRow)),\"ADMIS\",";
            $decisionFormula .= "IF(AND($moyenneAnnCol$currentRow>=10,(LEFT($creditsAnnCol$currentRow,FIND(\"/\",$creditsAnnCol$currentRow)-1)/MID($creditsAnnCol$currentRow,FIND(\"/\",$creditsAnnCol$currentRow)+1,LEN($creditsAnnCol$currentRow)))>=0.75),\"RACHAT\",\"AJOURNÉ\")))";
        } else {
            // En première session: ADMIS si 100% crédits, sinon RATTRAPAGE
            $decisionFormula = "=IF($moyenneAnnCol$currentRow=\"\",\"\",";
            $decisionFormula .= "IF(LEFT($creditsAnnCol$currentRow,FIND(\"/\",$creditsAnnCol$currentRow)-1)=MID($creditsAnnCol$currentRow,FIND(\"/\",$creditsAnnCol$currentRow)+1,LEN($creditsAnnCol$currentRow)),\"ADMIS\",\"RATTRAPAGE\"))";
        }
        
        $sheet->setCellValue($decisionAnnCol . $currentRow, $decisionFormula);
    }
    
    // Passer à la ligne suivante
    $rowIndex++;
}

$dataEndRow = $startRow + $rowIndex - 1;
if ($dataEndRow >= $startRow) {
    $lastColumn = $sheet->getHighestColumn();
    
    // Appliquer les bordures à toute la zone de données en une seule fois
    $dataRange = 'A' . $headerRow1 . ':' . $lastColumn . $dataEndRow;
    $sheet->getStyle($dataRange)->applyFromArray($borderStyle);
}


// Légende simplifiée
$legendRow = $startRow + $rowIndex + 2;
$sheet->setCellValue('A' . $legendRow, 'LÉGENDE:');
$legendRow++;

$sheet->setCellValue('A' . $legendRow, '• Cellules déverrouillées: notes et moyennes UE modifiables');
$legendRow++;

$sheet->setCellValue('A' . $legendRow, '• V: UE Validée (moyenne >= 10) | NV: UE Non Validée');
$legendRow++;

$sheet->setCellValue('A' . $legendRow, '• VP: Semestre Validé Partiellement');
$legendRow++;

$sheet->setCellValue('A' . $legendRow, '• Décisions calculées automatiquement par formules Excel');
$legendRow++;

if ($afficherDeuxSemestres) {
    if ($isDeuxiemeSession) {
        $sheet->setCellValue('A' . $legendRow, '• ADMIS: Tous crédits validés | RACHAT: Moy>=10 et 75% crédits | AJOURNÉ: Autres cas');
    } else {
        $sheet->setCellValue('A' . $legendRow, '• ADMIS: Tous crédits validés | RATTRAPAGE: Autres cas');
    }
    $legendRow++;
}

$legendRow += 1;
$sheet->setCellValue('A' . $legendRow, 'IMPORTANT: Modifiez uniquement les cellules déverrouillées');
$legendRow++;

$sheet->setCellValue('A' . $legendRow, 'Sauvegardez sans modifier la structure du fichier');

// Créer le nom du fichier
$sessionName = $sessionInfo ? str_replace(' ', '_', $sessionInfo['designSession']) : 'Session';
$promotionName = $promotionId ? str_replace(' ', '_', $universite->getPromotionById($promotionId)['designationPromotion']) : 'Promotion';
$fileName = 'Grille_Simplifiee_' . $promotionName . '_' . $sessionName . '_' . date('Y-m-d_H-i-s') . '.xlsx';

// Nettoyer la sortie
if (ob_get_length()) ob_clean();

// En-têtes HTTP pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Créer l'objet Writer pour sauvegarder le fichier
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
