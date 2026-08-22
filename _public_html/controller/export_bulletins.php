<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/assets/html2pdf/vendor/autoload.php';
use Spipu\Html2Pdf\Html2Pdf;
    
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Vérifier les permissions d'accès
$userId = $_SESSION['id'] ?? null;
$idRole = $_SESSION['idRole'] ?? null;

if (!$userId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Initialiser les modèles
$universite = new Universite();
$agent = new Agent();
$ecue = new Ecue();
$deliberation = new Deliberation();
$etudiantModel = new Etudiant();

// Récupérer les paramètres du formulaire
$bureauId = isset($_POST['bureau']) ? intval($_POST['bureau']) : 0;
$promotionId = isset($_POST['promotion']) ? intval($_POST['promotion']) : 0;
$semestreId = isset($_POST['semestre']) ? intval($_POST['semestre']) : 0;
$afficherDeuxSemestres = isset($_POST['deux_semestres']) && $_POST['deux_semestres'] == '1';
$sessionId = isset($_POST['session']) ? intval($_POST['session']) : 0;
$anneeId = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
$format = isset($_POST['format']) ? $_POST['format'] : 'excel';
$inclureLogo = isset($_POST['inclure_logo']) && $_POST['inclure_logo'] == '1';
$inclureSignature = isset($_POST['inclure_signature']) && $_POST['inclure_signature'] == '1';
$inclureStatistiques = isset($_POST['inclure_statistiques']) && $_POST['inclure_statistiques'] == '1';
$etudiantsSelection = isset($_POST['etudiants']) ? $_POST['etudiants'] : ['tous'];

// Vérifier que tous les paramètres nécessaires sont présents
if (!$bureauId || !$promotionId || !$sessionId || !$anneeId || (!$semestreId && !$afficherDeuxSemestres)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres incomplets']);
    exit;
}

// Vérifier les permissions (admin ou membre du jury)
$isAdmin = $idRole == 1;
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryMember = false;

if ($agentId) {
    $juryBureaux = $deliberation->getJuryBureauxByAgent($agentId);
    foreach ($juryBureaux as $jury) {
        if ($jury['idbureau'] == $bureauId) {
            $isJuryMember = true;
            break;
        }
    }
}

if (!$isAdmin && !$isJuryMember) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

// Récupérer les données pour les bulletins
try {
    // Récupérer la configuration de l'université
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Récupérer les informations de session
    $sessionInfo = $universite->getSessionById($sessionId);
    $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;
    
    // Récupérer la configuration de la délibération
    $configDeliberation = $deliberation->getDeliberationConfig($bureauId, $sessionId, $anneeId);
    $calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ? 
        (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;
    
    // Récupérer les semestres à afficher
    $semestres = $deliberation->getSemestresByPromotion($promotionId);
    $semestresToShow = $afficherDeuxSemestres ? $semestres : array_filter($semestres, function ($sem) use ($semestreId) {
        return $sem['idsemestre'] == $semestreId;
    });
    
    // Récupérer les étudiants
    $etudiants = [];
    if ($isDeuxiemeSession) {
        $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresToShow);
    } else {
        $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
    }
    
    // Si une sélection spécifique d'étudiants est demandée
    if (!in_array('tous', $etudiantsSelection)) {
        $etudiants = array_filter($etudiants, function($etudiant) use ($etudiantsSelection) {
            return in_array($etudiant['matricule'], $etudiantsSelection);
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
    
    // Récupérer les notes, moyennes et validations pour chaque étudiant
    $notesByEtudiantEcue = [];
    $moyennesUE = [];
    $validationsUE = [];
    $moyennesSemestre = [];
    $validationsSemestre = [];
    $moyennesAnnuelles = [];
    $validationsAnnuelles = [];
    
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        
        // Pour chaque semestre
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            $totalPointsSemestre = 0;
            $totalCreditsSemestre = 0;
            $creditsValidesSemestre = 0;
            $ecueAvecNotesSemestre = 0;
            $totalEcueSemestre = 0;
            $ueAvecMoyenne = 0;
            $totalUE = 0;
            
            // Pour chaque UE du semestre
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $totalUE++;
                $totalPointsUE = 0;
                $totalCoeffUE = 0;
                $ecueCount = 0;
                $ecueWithNotesCount = 0;
                $ecueWithCompleteNotesCount = 0;
                
                // Vérifier si l'UE a été validée en première session
                $ueValideeEnPremiereSession = false;
                if ($isDeuxiemeSession) {
                    $moyenneUEPremiereSession = $deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
                    $ueValideeEnPremiereSession = ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10);
                }
                
                // Pour chaque ECUE de l'UE
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    $ecueCount++;
                    $totalEcueSemestre++;
                    
                    // Récupérer la note pour cet ECUE
                    if ($isDeuxiemeSession) {
                        $notePremiereSession = $deliberation->getNotesEtudiantECUEPremiereSession($matricule, $ecueId, $anneeId);
                        
                        if ($notePremiereSession && $notePremiereSession['MF'] !== null && $notePremiereSession['MF'] >= 10) {
                            $notes = $notePremiereSession;
                        } else {
                            if ($ueValideeEnPremiereSession) {
                                $notes = $notePremiereSession;
                            } else {
                                $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                            }
                        }
                    } else {
                        $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                    }
                    
                    if ($notes) {
                        $notesByEtudiantEcue[$matricule][$ecueId] = $notes;
                        
                        // Vérifier si les notes sont complètes selon la configuration
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
                        
                        $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                        $totalCoeffUE += $coeffECUE;
                        
                        if ($notes['MF'] !== null) {
                            $totalPointsUE += $notes['MF'] * $coeffECUE;
                        }
                    } else {
                        $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                        $totalCoeffUE += $coeffECUE;
                    }
                }
                
                // Calculer la moyenne de l'UE
                if ($ecueCount > 0) {
                    if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                        $moyenneUE = $moyenneUEPremiereSession;
                        $moyennesUE[$matricule][$ueId] = $moyenneUE;
                        $validationsUE[$matricule][$ueId] = true;
                        
                        $totalPointsSemestre += $totalPointsUE;
                        $ueAvecMoyenne++;
                        
                        $creditsValidesSemestre += $totalCoeffUE;
                    } else if ($calculerAvecNotesVides || $ecueWithCompleteNotesCount == $ecueCount) {
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
        
        // Calculer la moyenne annuelle si on affiche deux semestres
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
    
    // Récupérer les informations sur la promotion
    $promotion = $deliberation->getPromotionById($promotionId);
    $anneeAcademique = $universite->getAnneeAcademiqueById($anneeId);
    $bureau = $deliberation->getJuryById($bureauId);
    
    // Statistiques globales si demandées
    $statsGlobales = null;
    if ($inclureStatistiques) {
        $statsGlobales = $afficherDeuxSemestres
            ? $deliberation->getStatistiquesGlobales($promotionId, $sessionId, $anneeId)
            : $deliberation->getStatistiquesGlobales($promotionId, $sessionId, $anneeId, $semestreId);
    }
    
    // Générer les bulletins en fonction du format demandé
    switch ($format) {
        case 'excel':
            generateExcelBulletins(
                $etudiants,
                $semestresToShow,
                $uesBySemestre,
                $ecuesByUE,
                $notesByEtudiantEcue,
                $moyennesUE,
                $validationsUE,
                $moyennesSemestre,
                $validationsSemestre,
                $moyennesAnnuelles,
                $validationsAnnuelles,
                $configUniversite,
                $promotion,
                $anneeAcademique,
                $bureau,
                $sessionInfo,
                $statsGlobales,
                $inclureLogo,
                $inclureSignature,
                $afficherDeuxSemestres
            );
            break;
            
        case 'pdf':
            generatePDFBulletins(
                $etudiants,
                $semestresToShow,
                $uesBySemestre,
                $ecuesByUE,
                $notesByEtudiantEcue,
                $moyennesUE,
                $validationsUE,
                $moyennesSemestre,
                $validationsSemestre,
                $moyennesAnnuelles,
                $validationsAnnuelles,
                $configUniversite,
                $promotion,
                $anneeAcademique,
                $bureau,
                $sessionInfo,
                $statsGlobales,
                $inclureLogo,
                $inclureSignature,
                $afficherDeuxSemestres
            );
            break;
            
        case 'zip':
            generateZipBulletins(
                $etudiants,
                $semestresToShow,
                $uesBySemestre,
                $ecuesByUE,
                $notesByEtudiantEcue,
                $moyennesUE,
                $validationsUE,
                $moyennesSemestre,
                $validationsSemestre,
                $moyennesAnnuelles,
                $validationsAnnuelles,
                $configUniversite,
                $promotion,
                $anneeAcademique,
                $bureau,
                $sessionInfo,
                $statsGlobales,
                $inclureLogo,
                $inclureSignature,
                $afficherDeuxSemestres
            );
            break;
            
        default:
            throw new Exception('Format non pris en charge');
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Fonction pour générer les bulletins en Excel
function generateExcelBulletins($etudiants, $semestresToShow, $uesBySemestre, $ecuesByUE, $notesByEtudiantEcue, 
                               $moyennesUE, $validationsUE, $moyennesSemestre, $validationsSemestre, 
                               $moyennesAnnuelles, $validationsAnnuelles, $configUniversite, $promotion, 
                               $anneeAcademique, $bureau, $sessionInfo, $statsGlobales, $inclureLogo, 
                               $inclureSignature, $afficherDeuxSemestres) {
    

    
    // Créer le classeur Excel
    $spreadsheet = new Spreadsheet();
    
    // Supprimer la feuille par défaut
    $spreadsheet->removeSheetByIndex(0);
    
    // Pour chaque étudiant, créer une feuille
    foreach ($etudiants as $index => $etudiant) {
        $matricule = $etudiant['matricule'];
        $nomEtudiant = $etudiant['noms'];
        
        // Créer une nouvelle feuille pour l'étudiant
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr("Bulletin_" . $matricule, 0, 31)); // Limiter la longueur du titre
        
        // En-tête du bulletin
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', strtoupper($configUniversite['designUniversite']));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', "BULLETIN DE NOTES - " . strtoupper($sessionInfo['designSession'] ?? 'SESSION') . " - ANNÉE ACADÉMIQUE " . $anneeAcademique['designation']);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Informations de l'étudiant
        $sheet->setCellValue('A4', "Matricule:");
        $sheet->setCellValue('B4', $matricule);
        $sheet->setCellValue('A5', "Nom et prénoms:");
        $sheet->setCellValue('B5', $nomEtudiant);
        $sheet->setCellValue('A6', "Promotion:");
        $sheet->setCellValue('B6', $promotion['designationPromotion']);
        
        $sheet->getStyle('A4:A6')->getFont()->setBold(true);
        
        // Si logo demandé
        if ($inclureLogo && !empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/uploads/logos/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $drawing = new Drawing();
                $drawing->setName('Logo');
                $drawing->setDescription('Logo');
                $drawing->setPath($logoPath);
                $drawing->setCoordinates('G1');
                $drawing->setHeight(60);
                $drawing->setWorksheet($sheet);
            }
        }
        
        // Ligne de départ pour les résultats
        $row = 8;
        
        // Pour chaque semestre
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            
            // Titre du semestre
            $sheet->mergeCells('A' . $row . ':H' . $row);
            $sheet->setCellValue('A' . $row, "SEMESTRE " . $semestre['numeroSemestre']);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            
            // En-têtes du tableau
            $sheet->setCellValue('A' . $row, "CODE");
            $sheet->setCellValue('B' . $row, "DESIGNATION");
            $sheet->setCellValue('C' . $row, "CRÉDITS");
            $sheet->setCellValue('D' . $row, "CC");
            $sheet->setCellValue('E' . $row, "EX");
            $sheet->setCellValue('F' . $row, "MOYENNE");
            $sheet->setCellValue('G' . $row, "DÉCISION");
            $sheet->setCellValue('H' . $row, "MENTION");
            
            $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
            $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
            
            // Pour chaque UE du semestre
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $moyenneUE = isset($moyennesUE[$matricule][$ueId]) ? $moyennesUE[$matricule][$ueId] : null;
                $estValidee = isset($validationsUE[$matricule][$ueId]) ? $validationsUE[$matricule][$ueId] : false;
                
                // Ligne pour l'UE
                $sheet->setCellValue('A' . $row, $ue['codeUE']);
                $sheet->setCellValue('B' . $row, $ue['designationUE']);
                
                // Calculer les crédits totaux de l'UE
                $creditsUE = 0;
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $creditsUE += ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                }
                
                $sheet->setCellValue('C' . $row, number_format($creditsUE, 1));
                $sheet->setCellValue('F' . $row, $moyenneUE !== null ? number_format($moyenneUE, 2) : '-');
                $sheet->setCellValue('G' . $row, $estValidee ? 'VALIDÉE' : 'NON VALIDÉE');
                
                // Déterminer la mention
                $mention = '-';
                if ($moyenneUE !== null) {
                    if ($moyenneUE >= 16) $mention = 'EXCELLENT';
                    else if ($moyenneUE >= 14) $mention = 'TRÈS BIEN';
                    else if ($moyenneUE >= 12) $mention = 'BIEN';
                    else if ($moyenneUE >= 10) $mention = 'ASSEZ BIEN';
                    else if ($moyenneUE >= 8) $mention = 'PASSABLE';
                    else $mention = 'INSUFFISANT';
                }
                
                $sheet->setCellValue('H' . $row, $mention);
                
                // Appliquer les styles pour l'UE
                $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEFF');
                $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $row++;
                
                                // Pour chaque ECUE de l'UE
                                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                                    $ecueId = $ecueItem['idECUE'];
                                    $notes = isset($notesByEtudiantEcue[$matricule][$ecueId]) ? $notesByEtudiantEcue[$matricule][$ecueId] : null;
                                    
                                    // Calculer les crédits de l'ECUE
                                    $creditsECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                                    
                                    // Ligne pour l'ECUE
                                    $sheet->setCellValue('A' . $row, $ecueItem['codeECUE'] ?? '');
                                    $sheet->setCellValue('B' . $row, '    ' . $ecueItem['designationECUE']);
                                    $sheet->setCellValue('C' . $row, number_format($creditsECUE, 1));
                                    $sheet->setCellValue('D' . $row, $notes ? ($notes['CC'] !== null ? number_format($notes['CC'], 2) : '-') : '-');
                                    $sheet->setCellValue('E' . $row, $notes ? ($notes['EX'] !== null ? number_format($notes['EX'], 2) : '-') : '-');
                                    $sheet->setCellValue('F' . $row, $notes ? ($notes['MF'] !== null ? number_format($notes['MF'], 2) : '-') : '-');
                                    
                                    // Déterminer la validation et la mention pour l'ECUE
                                    $validationECUE = ($notes && $notes['MF'] !== null && $notes['MF'] >= 10) ? 'VALIDÉ' : 'NON VALIDÉ';
                                    $sheet->setCellValue('G' . $row, $validationECUE);
                                    
                                    $mention = '-';
                                    if ($notes && $notes['MF'] !== null) {
                                        if ($notes['MF'] >= 16) $mention = 'EXCELLENT';
                                        else if ($notes['MF'] >= 14) $mention = 'TRÈS BIEN';
                                        else if ($notes['MF'] >= 12) $mention = 'BIEN';
                                        else if ($notes['MF'] >= 10) $mention = 'ASSEZ BIEN';
                                        else if ($notes['MF'] >= 8) $mention = 'PASSABLE';
                                        else $mention = 'INSUFFISANT';
                                    }
                                    
                                    $sheet->setCellValue('H' . $row, $mention);
                                    
                                    // Appliquer les styles pour l'ECUE
                                    $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                                    $row++;
                                }
                            }
                            
                            // Résultats du semestre
                            $moyenneSemestre = isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : null;
                            $validationSemestre = isset($validationsSemestre[$matricule][$semId]) ? $validationsSemestre[$matricule][$semId] : null;
                            
                            $row++;
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "RÉSULTATS DU SEMESTRE " . $semestre['numeroSemestre']);
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            
                            // Moyenne du semestre
                            $row++;
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Moyenne générale:");
                            $sheet->setCellValue('F' . $row, $moyenneSemestre !== null ? number_format($moyenneSemestre, 2) . '/20' : '-');
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            
                            // Crédits validés
                            $row++;
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Crédits validés:");
                            $sheet->setCellValue('F' . $row, $validationSemestre ? $validationSemestre['credits_valides'] . '/' . $validationSemestre['credits_total'] : '-');
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            
                            // Pourcentage
                            $row++;
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Pourcentage:");
                            $sheet->setCellValue('F' . $row, ($validationSemestre && $moyenneSemestre !== null) ? number_format($validationSemestre['pourcentage'], 1) . '%' : '-');
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            
                            // Décision
                            $row++;
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Décision:");
                            
                            // Déterminer la décision pour le semestre
                            $decision = 'NON VALIDÉ';
                            if ($moyenneSemestre === null) {
                                $decision = '-';
                            } else if ($validationSemestre && $validationSemestre['est_valide']) {
                                $decision = 'VALIDÉ';
                            }
                            
                            $sheet->setCellValue('F' . $row, $decision);
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            $sheet->getStyle('F' . $row)->getFont()->setBold(true);
                            
                            // Laisser un espace entre les semestres
                            $row += 2;
                        }
                        
                        // Résultats annuels si affichage des deux semestres
                        if ($afficherDeuxSemestres && count($semestresToShow) >= 2) {
                            $moyenneAnnuelle = isset($moyennesAnnuelles[$matricule]) ? $moyennesAnnuelles[$matricule] : null;
                            $validationAnnuelle = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule] : null;
                            
                            $sheet->mergeCells('A' . $row . ':H' . $row);
                            $sheet->setCellValue('A' . $row, "RÉSULTATS ANNUELS");
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCFFCC');
                            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $row++;
                            
                            // Moyenne annuelle
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Moyenne générale annuelle:");
                            $sheet->setCellValue('F' . $row, $moyenneAnnuelle !== null ? number_format($moyenneAnnuelle, 2) . '/20' : '-');
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            $row++;
                            
                            // Crédits validés
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Crédits validés:");
                            $sheet->setCellValue('F' . $row, $validationAnnuelle ? $validationAnnuelle['credits_valides'] . '/' . $validationAnnuelle['credits_total'] : '-');
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            $row++;
                            
                            // Pourcentage
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Pourcentage:");
                            $sheet->setCellValue('F' . $row, ($validationAnnuelle && $moyenneAnnuelle !== null) ? number_format($validationAnnuelle['pourcentage'], 1) . '%' : '-');
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            $row++;
                            
                            // Décision finale
                            $sheet->mergeCells('A' . $row . ':E' . $row);
                            $sheet->setCellValue('A' . $row, "Décision finale:");
                            
                            $decision = '-';
                            if ($moyenneAnnuelle !== null) {
                                $decision = ($validationAnnuelle && $validationAnnuelle['est_valide']) ? 'ADMIS' : 'AJOURNÉ';
                            }
                            
                            $sheet->setCellValue('F' . $row, $decision);
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            $sheet->getStyle('F' . $row)->getFont()->setBold(true);
                            $row += 2;
                        }
                        
                        // Statistiques si demandées
                        if ($statsGlobales) {
                            $sheet->mergeCells('A' . $row . ':H' . $row);
                            $sheet->setCellValue('A' . $row, "STATISTIQUES DE LA PROMOTION");
                            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $row++;
                            
                            $sheet->mergeCells('A' . $row . ':C' . $row);
                            $sheet->setCellValue('A' . $row, "Nombre total d'étudiants:");
                            $sheet->setCellValue('D' . $row, $statsGlobales['total_etudiants']);
                            $row++;
                            
                            $sheet->mergeCells('A' . $row . ':C' . $row);
                            $sheet->setCellValue('A' . $row, "Nombre d'étudiants admis:");
                            $sheet->setCellValue('D' . $row, $statsGlobales['etudiants_admis']);
                            $row++;
                            
                            $sheet->mergeCells('A' . $row . ':C' . $row);
                            $sheet->setCellValue('A' . $row, "Taux de réussite:");
                            $sheet->setCellValue('D' . $row, number_format($statsGlobales['taux_reussite'], 1) . '%');
                            $row++;
                        }
                        
                        // Signature si demandée
                        if ($inclureSignature) {
                            $row += 2;
                            $sheet->mergeCells('E' . $row . ':H' . $row);
                            $sheet->setCellValue('E' . $row, "Le Président du Jury");
                            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            
                            $row += 4; // Espace pour la signature
                            $sheet->mergeCells('E' . $row . ':H' . $row);
                            $sheet->setCellValue('E' . $row, $bureau['president'] ?? 'Pr. ' . ($bureau['nomPresident'] ?? ''));
                            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $sheet->getStyle('E' . $row)->getFont()->setBold(true);
                        }
                        
                        // Formater les colonnes
                        $sheet->getColumnDimension('A')->setWidth(15);
                        $sheet->getColumnDimension('B')->setWidth(40);
                        $sheet->getColumnDimension('C')->setWidth(10);
                        $sheet->getColumnDimension('D')->setWidth(12);
                        $sheet->getColumnDimension('E')->setWidth(12);
                        $sheet->getColumnDimension('F')->setWidth(12);
                        $sheet->getColumnDimension('G')->setWidth(15);
                        $sheet->getColumnDimension('H')->setWidth(15);
                    }
                    
                    // Générer le fichier Excel
                    $writer = new Xlsx($spreadsheet);
                    
                    // Configurer l'en-tête HTTP pour le téléchargement
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment;filename="bulletins_notes.xlsx"');
                    header('Cache-Control: max-age=0');
                    
                    // Envoyer le fichier au navigateur
                    $writer->save('php://output');
                    exit;
                }
                
                // Fonction pour générer les bulletins en PDF
                function generatePDFBulletins($etudiants, $semestresToShow, $uesBySemestre, $ecuesByUE, $notesByEtudiantEcue, 
                                             $moyennesUE, $validationsUE, $moyennesSemestre, $validationsSemestre, 
                                             $moyennesAnnuelles, $validationsAnnuelles, $configUniversite, $promotion, 
                                             $anneeAcademique, $bureau, $sessionInfo, $statsGlobales, $inclureLogo, 
                                             $inclureSignature, $afficherDeuxSemestres) {
                    
                    
                    
                    // Créer un conteneur HTML pour tous les bulletins
                    $htmlContent = '';
                    
                    // Pour chaque étudiant, créer un bulletin
                    foreach ($etudiants as $index => $etudiant) {
                        $matricule = $etudiant['matricule'];
                        $nomEtudiant = $etudiant['noms'];
                        
                        // Début du bulletin
                        $html = '
                        <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                            <table style="width: 100%;">
                                <tr>
                                    <td style="width: 20%; text-align: left;">
                                        ' . ($inclureLogo && !empty($configUniversite['logo']) ? '<img src="' . dirname(__DIR__) . '/uploads/logos/' . $configUniversite['logo'] . '" style="height: 60px;">' : '') . '
                                    </td>
                                    <td style="width: 60%; text-align: center;">
                                                                <h3 style="margin: 0;">' . strtoupper($configUniversite['designUniversite']) . '</h3>
                        <p style="margin: 5px 0;">BULLETIN DE NOTES - ' . strtoupper($sessionInfo['designSession'] ?? 'SESSION') . '</p>
                        <p style="margin: 5px 0;">ANNÉE ACADÉMIQUE ' . $anneeAcademique['designation'] . '</p>
                    </td>
                    <td style="width: 20%; text-align: right;"></td>
                </tr>
            </table>
            
            <hr style="margin: 10px 0;">
            
            <table style="width: 100%; margin-bottom: 15px;">
                <tr>
                    <td style="width: 25%;"><strong>Matricule:</strong></td>
                    <td style="width: 75%;">' . $matricule . '</td>
                </tr>
                <tr>
                    <td><strong>Nom et prénoms:</strong></td>
                    <td>' . $nomEtudiant . '</td>
                </tr>
                <tr>
                    <td><strong>Promotion:</strong></td>
                    <td>' . $promotion['designationPromotion'] . '</td>
                </tr>
            </table>';
        
        // Pour chaque semestre
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            
            $html .= '
            <h4 style="background-color: #f0f0f0; padding: 5px; text-align: center; margin: 10px 0;">
                SEMESTRE ' . $semestre['numeroSemestre'] . '
            </h4>
            
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;" border="1" cellpadding="3">
                <tr style="background-color: #eee;">
                    <th style="width: 10%;">CODE</th>
                    <th style="width: 30%;">DESIGNATION</th>
                    <th style="width: 8%;">CRÉDITS</th>
                    <th style="width: 8%;">CC</th>
                    <th style="width: 8%;">EX</th>
                    <th style="width: 10%;">MOYENNE</th>
                    <th style="width: 13%;">DÉCISION</th>
                    <th style="width: 13%;">MENTION</th>
                </tr>';
            
            // Pour chaque UE du semestre
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $moyenneUE = isset($moyennesUE[$matricule][$ueId]) ? $moyennesUE[$matricule][$ueId] : null;
                $estValidee = isset($validationsUE[$matricule][$ueId]) ? $validationsUE[$matricule][$ueId] : false;
                
                // Calculer les crédits totaux de l'UE
                $creditsUE = 0;
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $creditsUE += ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                }
                
                // Déterminer la mention
                $mention = '-';
                if ($moyenneUE !== null) {
                    if ($moyenneUE >= 16) $mention = 'EXCELLENT';
                    else if ($moyenneUE >= 14) $mention = 'TRÈS BIEN';
                    else if ($moyenneUE >= 12) $mention = 'BIEN';
                    else if ($moyenneUE >= 10) $mention = 'ASSEZ BIEN';
                    else if ($moyenneUE >= 8) $mention = 'PASSABLE';
                    else $mention = 'INSUFFISANT';
                }
                
                $html .= '
                <tr style="background-color: #eeeeff; font-weight: bold;">
                    <td>' . $ue['codeUE'] . '</td>
                    <td>' . $ue['designationUE'] . '</td>
                    <td style="text-align: center;">' . number_format($creditsUE, 1) . '</td>
                    <td></td>
                    <td></td>
                    <td style="text-align: center;">' . ($moyenneUE !== null ? number_format($moyenneUE, 2) : '-') . '</td>
                    <td style="text-align: center;">' . ($estValidee ? 'VALIDÉE' : 'NON VALIDÉE') . '</td>
                    <td style="text-align: center;">' . $mention . '</td>
                </tr>';
                
                // Pour chaque ECUE de l'UE
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    $notes = isset($notesByEtudiantEcue[$matricule][$ecueId]) ? $notesByEtudiantEcue[$matricule][$ecueId] : null;
                    
                    // Calculer les crédits de l'ECUE
                    $creditsECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                    
                    // Déterminer la validation et la mention pour l'ECUE
                    $validationECUE = ($notes && $notes['MF'] !== null && $notes['MF'] >= 10) ? 'VALIDÉ' : 'NON VALIDÉ';
                    
                    $mentionECUE = '-';
                    if ($notes && $notes['MF'] !== null) {
                        if ($notes['MF'] >= 16) $mentionECUE = 'EXCELLENT';
                        else if ($notes['MF'] >= 14) $mentionECUE = 'TRÈS BIEN';
                        else if ($notes['MF'] >= 12) $mentionECUE = 'BIEN';
                        else if ($notes['MF'] >= 10) $mentionECUE = 'ASSEZ BIEN';
                        else if ($notes['MF'] >= 8) $mentionECUE = 'PASSABLE';
                        else $mentionECUE = 'INSUFFISANT';
                    }
                    
                    $html .= '
                    <tr>
                        <td>' . ($ecueItem['codeECUE'] ?? '') . '</td>
                        <td style="padding-left: 15px;">' . $ecueItem['designationECUE'] . '</td>
                        <td style="text-align: center;">' . number_format($creditsECUE, 1) . '</td>
                        <td style="text-align: center;">' . ($notes ? ($notes['CC'] !== null ? number_format($notes['CC'], 2) : '-') : '-') . '</td>
                        <td style="text-align: center;">' . ($notes ? ($notes['EX'] !== null ? number_format($notes['EX'], 2) : '-') : '-') . '</td>
                        <td style="text-align: center;">' . ($notes ? ($notes['MF'] !== null ? number_format($notes['MF'], 2) : '-') : '-') . '</td>
                        <td style="text-align: center;">' . $validationECUE . '</td>
                        <td style="text-align: center;">' . $mentionECUE . '</td>
                    </tr>';
                }
            }
            
            $html .= '</table>';
            
            // Résultats du semestre
            $moyenneSemestre = isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : null;
            $validationSemestre = isset($validationsSemestre[$matricule][$semId]) ? $validationsSemestre[$matricule][$semId] : null;
            
            // Déterminer la décision pour le semestre
            $decision = 'NON VALIDÉ';
            if ($moyenneSemestre === null) {
                $decision = '-';
            } else if ($validationSemestre && $validationSemestre['est_valide']) {
                $decision = 'VALIDÉ';
            }
            
            $html .= '
            <table style="width: 70%; margin: 15px auto;">
                <tr>
                    <td style="width: 60%; font-weight: bold;">Moyenne générale:</td>
                    <td style="width: 40%;">' . ($moyenneSemestre !== null ? number_format($moyenneSemestre, 2) . '/20' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Crédits validés:</td>
                    <td>' . ($validationSemestre ? $validationSemestre['credits_valides'] . '/' . $validationSemestre['credits_total'] : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Pourcentage:</td>
                    <td>' . (($validationSemestre && $moyenneSemestre !== null) ? number_format($validationSemestre['pourcentage'], 1) . '%' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Décision:</td>
                    <td style="font-weight: bold;">' . $decision . '</td>
                </tr>
            </table>';
        }
        
        // Résultats annuels si affichage des deux semestres
        if ($afficherDeuxSemestres && count($semestresToShow) >= 2) {
            $moyenneAnnuelle = isset($moyennesAnnuelles[$matricule]) ? $moyennesAnnuelles[$matricule] : null;
            $validationAnnuelle = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule] : null;
            
            $decision = '-';
            if ($moyenneAnnuelle !== null) {
                $decision = ($validationAnnuelle && $validationAnnuelle['est_valide']) ? 'ADMIS' : 'AJOURNÉ';
            }
            
            $html .= '
            <h4 style="background-color: #ccffcc; padding: 5px; text-align: center; margin: 20px 0 10px 0;">
                RÉSULTATS ANNUELS
            </h4>
            
            <table style="width: 70%; margin: 15px auto;">
                <tr>
                    <td style="width: 60%; font-weight: bold;">Moyenne générale annuelle:</td>
                    <td style="width: 40%;">' . ($moyenneAnnuelle !== null ? number_format($moyenneAnnuelle, 2) . '/20' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Crédits validés:</td>
                    <td>' . ($validationAnnuelle ? $validationAnnuelle['credits_valides'] . '/' . $validationAnnuelle['credits_total'] : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Pourcentage:</td>
                    <td>' . (($validationAnnuelle && $moyenneAnnuelle !== null) ? number_format($validationAnnuelle['pourcentage'], 1) . '%' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Décision finale:</td>
                    <td style="font-weight: bold;">' . $decision . '</td>
                </tr>
            </table>';
        }
        
        // Statistiques si demandées
        if ($statsGlobales) {
            $html .= '
            <h4 style="margin-top: 20px;">STATISTIQUES DE LA PROMOTION</h4>
            
            <table style="width: 60%;">
                <tr>
                    <td style="width: 70%;">Nombre total d\'étudiants:</td>
                    <td style="width: 30%;">' . $statsGlobales['total_etudiants'] . '</td>
                </tr>
                <tr>
                    <td>Nombre d\'étudiants admis:</td>
                    <td>' . $statsGlobales['etudiants_admis'] . '</td>
                </tr>
                <tr>
                    <td>Taux de réussite:</td>
                    <td>' . number_format($statsGlobales['taux_reussite'], 1) . '%</td>
                </tr>
            </table>';
        }
        
        // Signature si demandée
        if ($inclureSignature) {
            $html .= '
            <div style="margin-top: 30px; text-align: right; padding-right: 50px;">
                <p>Le Président du Jury</p>
                <div style="height: 60px;"></div>
                <p><strong>' . ($bureau['president'] ?? 'Pr. ' . ($bureau['nomPresident'] ?? '')) . '</strong></p>
            </div>';
        }
        
        // Fin du bulletin
        $html .= '</page>';
        
        // Ajouter le bulletin au contenu global
        $htmlContent .= $html;
        
        // Ajouter un saut de page entre les bulletins sauf pour le dernier
        if ($index < count($etudiants) - 1) {
            $htmlContent .= '<page_break />';
        }
    }
    
    // Générer le fichier PDF
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->writeHTML($htmlContent);
    
    // Envoyer le PDF au navigateur
    $html2pdf->output('bulletins_notes.pdf');
    exit;
}

// Fonction pour générer une archive ZIP contenant les bulletins individuels
function generateZipBulletins($etudiants, $semestresToShow, $uesBySemestre, $ecuesByUE, $notesByEtudiantEcue, 
                             $moyennesUE, $validationsUE, $moyennesSemestre, $validationsSemestre, 
                             $moyennesAnnuelles, $validationsAnnuelles, $configUniversite, $promotion, 
                             $anneeAcademique, $bureau, $sessionInfo, $statsGlobales, $inclureLogo, 
                             $inclureSignature, $afficherDeuxSemestres) {
    // Créer un répertoire temporaire pour stocker les fichiers
    $tempDir = dirname(__DIR__) . '/temp/bulletins_' . time();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Créer l'archive ZIP
    $zipFile = $tempDir . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
        throw new Exception('Impossible de créer l\'archive ZIP');
    }
    
    
    
    // Pour chaque étudiant, générer un bulletin PDF individuel
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        $nomEtudiant = preg_replace('/[^a-zA-Z0-9_]/', '_', $etudiant['noms']);
        
        // Même contenu HTML que dans la fonction generatePDFBulletins, mais pour un seul étudiant
        $html = '
        <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 20%; text-align: left;">
                        ' . ($inclureLogo && !empty($configUniversite['logo']) ? '<img src="' . dirname(__DIR__) . '/uploads/logos/' . $configUniversite['logo'] . '" style="height: 60px;">' : '') . '
                    </td>
                    <td style="width: 60%; text-align: center;">
                        <h3 style="margin: 0;">' . strtoupper($configUniversite['designUniversite']) . '</h3>
                        <p style="margin: 5px 0;">BULLETIN DE NOTES - ' . strtoupper($sessionInfo['designSession'] ?? 'SESSION') . '</p>
                        <p style="margin: 5px 0;">ANNÉE ACADÉMIQUE ' . $anneeAcademique['designation'] . '</p>
                    </td>
                    <td style="width: 20%; text-align: right;"></td>
                </tr>
            </table>
            
            <hr style="margin: 10px 0;">
            
            <table style="width: 100%; margin-bottom: 15px;">
                <tr>
                    <td style="width: 25%;"><strong>Matricule:</strong></td>
                    <td style="width: 75%;">' . $matricule . '</td>
                </tr>
                <tr>
                    <td><strong>Nom et prénoms:</strong></td>
                    <td>' . $etudiant['noms'] . '</td>
                </tr>
                <tr>
                    <td><strong>Promotion:</strong></td>
                    <td>' . $promotion['designationPromotion'] . '</td>
                </tr>
            </table>';
        
        // Pour chaque semestre
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            
            $html .= '
            <h4 style="background-color: #f0f0f0; padding: 5px; text-align: center; margin: 10px 0;">
                SEMESTRE ' . $semestre['numeroSemestre'] . '
            </h4>
            
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;" border="1" cellpadding="3">
                <tr style="background-color: #eee;">
                    <th style="width: 10%;">CODE</th>
                    <th style="width: 30%;">DESIGNATION</th>
                    <th style="width: 8%;">CRÉDITS</th>
                    <th style="width: 8%;">CC</th>
                    <th style="width: 8%;">EX</th>
                    <th style="width: 10%;">MOYENNE</th>
                    <th style="width: 13%;">DÉCISION</th>
                    <th style="width: 13%;">MENTION</th>
                </tr>';
            
            // Pour chaque UE et ECUE, générer les lignes comme dans generatePDFBulletins
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $moyenneUE = isset($moyennesUE[$matricule][$ueId]) ? $moyennesUE[$matricule][$ueId] : null;
                $estValidee = isset($validationsUE[$matricule][$ueId]) ? $validationsUE[$matricule][$ueId] : false;
                
                // Calculer les crédits totaux de l'UE
                $creditsUE = 0;
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $creditsUE += ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                }
                
                // Déterminer la mention
                $mention = '-';
                if ($moyenneUE !== null) {
                    if ($moyenneUE >= 16) $mention = 'EXCELLENT';
                    else if ($moyenneUE >= 14) $mention = 'TRÈS BIEN';
                    else if ($moyenneUE >= 12) $mention = 'BIEN';
                    else if ($moyenneUE >= 10) $mention = 'ASSEZ BIEN';
                    else if ($moyenneUE >= 8) $mention = 'PASSABLE';
                    else $mention = 'INSUFFISANT';
                }
                
                $html .= '
                <tr style="background-color: #eeeeff; font-weight: bold;">
                    <td>' . $ue['codeUE'] . '</td>
                    <td>' . $ue['designationUE'] . '</td>
                    <td style="text-align: center;">' . number_format($creditsUE, 1) . '</td>
                    <td></td>
                    <td></td>
                    <td style="text-align: center;">' . ($moyenneUE !== null ? number_format($moyenneUE, 2) : '-') . '</td>
                    <td style="text-align: center;">' . ($estValidee ? 'VALIDÉE' : 'NON VALIDÉE') . '</td>
                    <td style="text-align: center;">' . $mention . '</td>
                </tr>';
                
                // Pour chaque ECUE de l'UE
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    $notes = isset($notesByEtudiantEcue[$matricule][$ecueId]) ? $notesByEtudiantEcue[$matricule][$ecueId] : null;
                    
                    // Calculer les crédits de l'ECUE
                    $creditsECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / 15;
                    
                    // Déterminer la validation et la mention pour l'ECUE
                    $validationECUE = ($notes && $notes['MF'] !== null && $notes['MF'] >= 10) ? 'VALIDÉ' : 'NON VALIDÉ';
                    
                    $mentionECUE = '-';
                    if ($notes && $notes['MF'] !== null) {
                        if ($notes['MF'] >= 16) $mentionECUE = 'EXCELLENT';
                        else if ($notes['MF'] >= 14) $mentionECUE = 'TRÈS BIEN';
                        else if ($notes['MF'] >= 12) $mentionECUE = 'BIEN';
                        else if ($notes['MF'] >= 10) $mentionECUE = 'ASSEZ BIEN';
                        else if ($notes['MF'] >= 8) $mentionECUE = 'PASSABLE';
                        else $mentionECUE = 'INSUFFISANT';
                    }
                    
                    $html .= '
                    <tr>
                        <td>' . ($ecueItem['codeECUE'] ?? '') . '</td>
                        <td style="padding-left: 15px;">' . $ecueItem['designationECUE'] . '</td>
                        <td style="text-align: center;">' . number_format($creditsECUE, 1) . '</td>
                        <td style="text-align: center;">' . ($notes ? ($notes['CC'] !== null ? number_format($notes['CC'], 2) : '-') : '-') . '</td>
                        <td style="text-align: center;">' . ($notes ? ($notes['EX'] !== null ? number_format($notes['EX'], 2) : '-') : '-') . '</td>
                        <td style="text-align: center;">' . ($notes ? ($notes['MF'] !== null ? number_format($notes['MF'], 2) : '-') : '-') . '</td>
                        <td style="text-align: center;">' . $validationECUE . '</td>
                        <td style="text-align: center;">' . $mentionECUE . '</td>
                    </tr>';
                }
            }
            
            $html .= '</table>';
            
            // Résultats du semestre comme dans generatePDFBulletins
            $moyenneSemestre = isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : null;
            $validationSemestre = isset($validationsSemestre[$matricule][$semId]) ? $validationsSemestre[$matricule][$semId] : null;
            
            // Déterminer la décision pour le semestre
            $decision = 'NON VALIDÉ';
            if ($moyenneSemestre === null) {
                $decision = '-';
            } else if ($validationSemestre && $validationSemestre['est_valide']) {
                $decision = 'VALIDÉ';
            }
            
            $html .= '
            <table style="width: 70%; margin: 15px auto;">
                <tr>
                    <td style="width: 60%; font-weight: bold;">Moyenne générale:</td>
                    <td style="width: 40%;">' . ($moyenneSemestre !== null ? number_format($moyenneSemestre, 2) . '/20' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Crédits validés:</td>
                    <td>' . ($validationSemestre ? $validationSemestre['credits_valides'] . '/' . $validationSemestre['credits_total'] : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Pourcentage:</td>
                    <td>' . (($validationSemestre && $moyenneSemestre !== null) ? number_format($validationSemestre['pourcentage'], 1) . '%' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Décision:</td>
                    <td style="font-weight: bold;">' . $decision . '</td>
                </tr>
            </table>';
        }
        
        // Résultats annuels si nécessaire, comme dans generatePDFBulletins
        if ($afficherDeuxSemestres && count($semestresToShow) >= 2) {
            $moyenneAnnuelle = isset($moyennesAnnuelles[$matricule]) ? $moyennesAnnuelles[$matricule] : null;
            $validationAnnuelle = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule] : null;
            
            $decision = '-';
            if ($moyenneAnnuelle !== null) {
                $decision = ($validationAnnuelle && $validationAnnuelle['est_valide']) ? 'ADMIS' : 'AJOURNÉ';
            }
            
            $html .= '
            <h4 style="background-color: #ccffcc; padding: 5px; text-align: center; margin: 20px 0 10px 0;">
                RÉSULTATS ANNUELS
            </h4>
            
            <table style="width: 70%; margin: 15px auto;">
                <tr>
                    <td style="width: 60%; font-weight: bold;">Moyenne générale annuelle:</td>
                    <td style="width: 40%;">' . ($moyenneAnnuelle !== null ? number_format($moyenneAnnuelle, 2) . '/20' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Crédits validés:</td>
                    <td>' . ($validationAnnuelle ? $validationAnnuelle['credits_valides'] . '/' . $validationAnnuelle['credits_total'] : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Pourcentage:</td>
                    <td>' . (($validationAnnuelle && $moyenneAnnuelle !== null) ? number_format($validationAnnuelle['pourcentage'], 1) . '%' : '-') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Décision finale:</td>
                    <td style="font-weight: bold;">' . $decision . '</td>
                </tr>
            </table>';
        }
        
        // Statistiques et signature comme dans generatePDFBulletins
        if ($statsGlobales) {
            $html .= '
            <h4 style="margin-top: 20px;">STATISTIQUES DE LA PROMOTION</h4>
            
            <table style="width: 60%;">
                <tr>
                    <td style="width: 70%;">Nombre total d\'étudiants:</td>
                    <td style="width: 30%;">' . $statsGlobales['total_etudiants'] . '</td>
                </tr>
                <tr>
                    <td>Nombre d\'étudiants admis:</td>
                    <td>' . $statsGlobales['etudiants_admis'] . '</td>
                </tr>
                <tr>
                    <td>Taux de réussite:</td>
                    <td>' . number_format($statsGlobales['taux_reussite'], 1) . '%</td>
                </tr>
                            </table>';
        }
        
        if ($inclureSignature) {
            $html .= '
            <div style="margin-top: 30px; text-align: right; padding-right: 50px;">
                <p>Le Président du Jury</p>
                <div style="height: 60px;"></div>
                <p><strong>' . ($bureau['president'] ?? 'Pr. ' . ($bureau['nomPresident'] ?? '')) . '</strong></p>
            </div>';
        }
        
        $html .= '</page>';
        
        // Générer le PDF pour cet étudiant
        $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
        $html2pdf->writeHTML($html);
        
        // Enregistrer le PDF dans le répertoire temporaire
        $pdfFilename = $matricule . '_' . $nomEtudiant . '.pdf';
        $pdfPath = $tempDir . '/' . $pdfFilename;
        $html2pdf->output($pdfPath, 'F');
        
        // Ajouter le PDF à l'archive ZIP
        $zip->addFile($pdfPath, $pdfFilename);
    }
    
    // Fermer l'archive ZIP
    $zip->close();
    
    // Envoyer l'archive ZIP au navigateur
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="bulletins.zip"');
    header('Content-Length: ' . filesize($zipFile));
    header('Cache-Control: max-age=0');
    readfile($zipFile);
    
    // Nettoyer les fichiers temporaires
    array_map('unlink', glob("$tempDir/*"));
    rmdir($tempDir);
    unlink($zipFile);
    
    exit;
}


