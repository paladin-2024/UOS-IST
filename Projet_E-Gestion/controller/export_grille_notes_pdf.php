<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

//use TCPDF;

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

if ($bureauId <= 0 || $promotionId <= 0 || $sessionId <= 0 || $anneeId <= 0 || (!$semestreId && !$afficherDeuxSemestres)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

// Initialiser les objets
$universite = new Universite();
$ecue = new Ecue();
$deliberation = new Deliberation();
$agentModel = new Agent();

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

// Récupérer les notes, moyennes et validations pour tous les étudiants
$notesByEtudiantEcue = [];
$moyennesUE = [];
$validationsUE = [];
$moyennesSemestre = [];
$validationsSemestre = [];
$moyennesAnnuelles = [];
$validationsAnnuelles = [];

// IMPORTANT: Utiliser exactement la même logique que dans grille_notes.php
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
            
            foreach ($ecuesByUE[$ueId] as $ecueItem) {
                $ecueId = $ecueItem['idECUE'];
                $ecueCount++;
                $totalEcueSemestre++;
                
                if ($isDeuxiemeSession) {
                    $notePremiereSession = $deliberation->getNotesEtudiantECUEPremiereSession($matricule, $ecueId, $anneeId);

                    if ($notePremiereSession && $notePremiereSession['MF'] !== null && $notePremiereSession['MF'] >= 10) {
                        $notes = $notePremiereSession;
                    } else {
                        //On vérifie si l'UE a été validé malgré que la note est inférieure à 10
                        if($ueValideeEnPremiereSession){
                            $notes = $notePremiereSession;
                        }else{
                            $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
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
                    
                    $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/15;
                    $totalCoeffUE += $coeffECUE;
                    
                    if ($notes['MF'] !== null) {
                        $totalPointsUE += $notes['MF'] * $coeffECUE;
                    }
                } else {
                    $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP'])/15;
                    $totalCoeffUE += $coeffECUE;
                }
            }
            
            // Calculer la moyenne de l'UE
            $configMoyenne = $deliberation->getDeliberationConfig($bureauId, $sessionId, $anneeId);
            $calculerAvecNotesVides = isset($configMoyenne['calculer_moyenne_avec_notes_vides']) ? 
                (bool)$configMoyenne['calculer_moyenne_avec_notes_vides'] : false;
            
            if ($ecueCount > 0) {
                if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                    $moyenneUE = $moyenneUEPremiereSession;
                    $moyennesUE[$matricule][$ueId] = $moyenneUE;
                    $validationsUE[$matricule][$ueId] = true;
                    $totalPointsSemestre += $totalPointsUE;
                    $ueAvecMoyenne++;
                    $creditsValidesSemestre += $totalCoeffUE;
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

// Créer une classe personnalisée de TCPDF pour gérer les sauts de page et en-têtes
class GrillePDF extends TCPDF {
    protected $headerData;
    protected $configUniversite;
    protected $tableHeader;
    protected $sessionInfo;
    protected $promotionInfo;
    protected $anneeInfo;
    protected $semestreInfo;

    public function setGrilleHeaderData($configUniversite, $sessionInfo, $promotionInfo, $anneeInfo, $semestreInfo) {
        $this->configUniversite = $configUniversite;
        $this->sessionInfo = $sessionInfo;
        $this->promotionInfo = $promotionInfo;
        $this->anneeInfo = $anneeInfo;
        $this->semestreInfo = $semestreInfo;
    }
    public function Header() {
        if ($this->configUniversite) {
            // Logo
            if (!empty($this->configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/uploads/config/' . $this->configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $this->Image($logoPath, 10, 5, 20, '', '', '', 'T', false, 300, '', false, false, 0);
                }
            }
            
            // Titre et informations de l'université
            $this->SetFont('dejavusans', 'B', 10);
            $this->Cell(0, 5, strtoupper($this->configUniversite['ministere_tutelle']), 0, 1, 'C');
            
            $this->SetFont('dejavusans', 'B', 12);
            $this->Cell(0, 5, strtoupper($this->configUniversite['nom']), 0, 1, 'C');
            
            if (!empty($this->configUniversite['sigle'])) {
                $this->SetFont('dejavusans', 'B', 10);
                $this->Cell(0, 4, $this->configUniversite['sigle'], 0, 1, 'C');
            }
            
            $this->SetFont('dejavusans', '', 8);
            $this->Cell(0, 4, 'GRILLE DE NOTES - ' . $this->sessionInfo['designSession'], 0, 1, 'C');
            $this->Cell(0, 4, $this->promotionInfo['designationPromotion'] . ' - ' . $this->anneeInfo['designation'], 0, 1, 'C');
            $this->Cell(0, 4, $this->semestreInfo, 0, 1, 'C');
            
            // Ligne de séparation
            $this->SetLineWidth(0.1);
            $this->Line(10, $this->GetY() + 2, $this->getPageWidth() - 10, $this->GetY() + 2);
            $this->Ln(4);
        }
        
        // Afficher l'en-tête du tableau si défini
        if (isset($this->tableHeader) && is_callable($this->tableHeader)) {
            call_user_func($this->tableHeader, $this);
        }
    }
    
    public function setGrilleTableHeader($callback) {
        $this->tableHeader = $callback;
    }
    
    public function Footer() {        // Position à 15mm du bas
        $this->SetY(-15);
        // Police
        $this->SetFont('dejavusans', 'I', 8);
        // Numéro de page
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Récupérer les informations sur la promotion
$promotionInfo = $universite->getPromotionById($promotionId);
// Récupérer les informations sur l'année académique
$anneeInfo = $universite->getAcademicYearById($anneeId);

// Créer le PDF
$pdf = new GrillePDF('L', 'mm', 'A3', true, 'UTF-8', false);

// Informations du document
$pdf->SetCreator('Système de Gestion Universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Université');
$pdf->SetTitle('Grille de Notes - ' . ($promotionInfo['designationPromotion'] ?? '') . ' - ' . ($sessionInfo['designSession'] ?? ''));
$pdf->SetSubject('Grille de Notes');

// Configuration du PDF
$pdf->SetMargins(10, 30, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetPrintHeader(true);
$pdf->SetPrintFooter(true);

// Informations pour l'en-tête
$semestreInfo = $afficherDeuxSemestres ? 'ANNÉE ACADÉMIQUE COMPLÈTE' : ('SEMESTRE ' . ($semestresToShow[0]['numeroSemestre'] ?? ''));
$pdf->setHeaderData($configUniversite, $sessionInfo, $promotionInfo, $anneeInfo, $semestreInfo);

// Décompte des colonnes pour dimensionner le tableau
$totalColumns = 3; // Numéro, Matricule, Nom
foreach ($semestresToShow as $semestre) {
    $semId = $semestre['idsemestre'];
    foreach ($uesBySemestre[$semId] as $ue) {
        $ueId = $ue['idUE'];
        $totalColumns += count($ecuesByUE[$ueId]) + 2; // +2 pour la moyenne UE et Décision
    }
    $totalColumns += 2; // Moyenne semestre + Décision semestre
}
if ($afficherDeuxSemestres) {
    $totalColumns += 2; // Moyenne annuelle + Décision annuelle
}

// Fonction pour définir la couleur de cellule en fonction de la note
function getCellColor($note) {
    if ($note === null) return [255, 255, 255]; // Blanc pour null
    if ($note >= 10) return [198, 239, 206]; // Vert clair pour >= 10
    if ($note >= 8) return [255, 235, 156]; // Jaune pour >= 8
    return [244, 199, 195]; // Rouge clair pour < 8
}

// Fonction pour afficher une note formatée
function formatNote($note) {
    if ($note === null) return '-';
    return number_format($note, 2, ',', ' ');
}

// Définir la fonction d'en-tête du tableau
// Définir l'en-tête du tableau
function setTableHeader($pdf, $semestresToShow, $uesBySemestre, $ecuesByUE, $afficherDeuxSemestres) {
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetFillColor(220, 220, 220);
    
    // Première ligne de l'en-tête avec les semestres et UE
    $cellWidth = 10; // Largeur des colonnes N°, Matricule, Noms
    $headerHeight = 7;
    
    $pdf->Cell($cellWidth, $headerHeight, 'N°', 1, 0, 'C', 1);
    $pdf->Cell(25, $headerHeight, 'Matricule', 1, 0, 'C', 1);
    $pdf->Cell(60, $headerHeight, 'Noms et Prénoms', 1, 0, 'C', 1);
    
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        $semUes = $uesBySemestre[$semId];
        
        $semColSpan = 0;
        foreach ($semUes as $ue) {
            $ueId = $ue['idUE'];
            $semColSpan += count($ecuesByUE[$ueId]) + 2; // ECUE + Moyenne + Décision
        }
        $semColSpan += 2; // Moyenne semestre + Décision
        
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell($semColSpan * $cellWidth, $headerHeight, 'SEMESTRE ' . $semestre['numeroSemestre'], 1, 0, 'C', 1);
    }
    
    if ($afficherDeuxSemestres) {
        $pdf->Cell(2 * $cellWidth, $headerHeight, 'ANNUEL', 1, 0, 'C', 1);
    }
    
    $pdf->Ln();
    
    // Deuxième ligne avec UE et ECUE
    $pdf->SetFont('dejavusans', 'B', 7);
    $pdf->Cell($cellWidth, $headerHeight, '', 1, 0, 'C', 1);
    $pdf->Cell(25, $headerHeight, '', 1, 0, 'C', 1);
    $pdf->Cell(60, $headerHeight, '', 1, 0, 'C', 1);
    
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $ecueCount = count($ecuesByUE[$ueId]);
            
            $ueColSpan = $ecueCount + 2; // ECUE + Moyenne + Décision
            $pdf->Cell($ueColSpan * $cellWidth, $headerHeight, $ue['codeUE'] . ' - ' . $ue['designationUE'], 1, 0, 'C', 1);
        }
        
        $pdf->Cell(2 * $cellWidth, $headerHeight, 'Moy. Sem.', 1, 0, 'C', 1);
    }
    
    if ($afficherDeuxSemestres) {
        $pdf->Cell(2 * $cellWidth, $headerHeight, 'Moy. Ann.', 1, 0, 'C', 1);
    }
    
    $pdf->Ln();
    
    // Troisième ligne avec les ECUE individuels
    $pdf->SetFont('dejavusans', 'B', 6);
    $pdf->Cell($cellWidth, $headerHeight, '', 1, 0, 'C', 1);
    $pdf->Cell(25, $headerHeight, '', 1, 0, 'C', 1);
    $pdf->Cell(60, $headerHeight, '', 1, 0, 'C', 1);
    
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            
            foreach ($ecuesByUE[$ueId] as $ecueItem) {
                $pdf->Cell($cellWidth, $headerHeight, $ecueItem['designationECUE'], 1, 0, 'C', 1);
            }
            
            $pdf->Cell($cellWidth, $headerHeight, 'Moy', 1, 0, 'C', 1);
            $pdf->Cell($cellWidth, $headerHeight, 'Déc', 1, 0, 'C', 1);
        }
        
        $pdf->Cell($cellWidth, $headerHeight, 'Moy', 1, 0, 'C', 1);
        $pdf->Cell($cellWidth, $headerHeight, 'Déc', 1, 0, 'C', 1);
    }
    
    if ($afficherDeuxSemestres) {
        $pdf->Cell($cellWidth, $headerHeight, 'Moy', 1, 0, 'C', 1);
        $pdf->Cell($cellWidth, $headerHeight, 'Déc', 1, 0, 'C', 1);
    }
    
    $pdf->Ln();
}

// Call the function
setTableHeader($pdf, $semestresToShow, $uesBySemestre, $ecuesByUE, $afficherDeuxSemestres);// Ajouter une page
$pdf->AddPage();

// Générer le tableau des notes
$pdf->SetFont('dejavusans', '', 8);
$cellWidth = 10;
$rowHeight = 6;

// Tableau avec les étudiants et leurs notes
foreach ($etudiants as $index => $etudiant) {
    $matricule = $etudiant['matricule'];
    
    $pdf->Cell($cellWidth, $rowHeight, $index + 1, 1, 0, 'C');
    $pdf->Cell(25, $rowHeight, $matricule, 1, 0, 'L');
    $pdf->Cell(60, $rowHeight, $etudiant['noms'], 1, 0, 'L');
    
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            
            foreach ($ecuesByUE[$ueId] as $ecueItem) {
                $ecueId = $ecueItem['idECUE'];
                $note = isset($notesByEtudiantEcue[$matricule][$ecueId]) ? $notesByEtudiantEcue[$matricule][$ecueId]['MF'] : null;
                
                $cellColor = getCellColor($note);
                $pdf->SetFillColor($cellColor[0], $cellColor[1], $cellColor[2]);
                $pdf->Cell($cellWidth, $rowHeight, formatNote($note), 1, 0, 'C', 1);
            }
            
            // Moyenne UE
            $moyenneUE = isset($moyennesUE[$matricule][$ueId]) ? $moyennesUE[$matricule][$ueId] : null;
            $cellColor = getCellColor($moyenneUE);
            $pdf->SetFillColor($cellColor[0], $cellColor[1], $cellColor[2]);
            $pdf->Cell($cellWidth, $rowHeight, formatNote($moyenneUE), 1, 0, 'C', 1);
            
                        // Décision UE
                        $validationUE = isset($validationsUE[$matricule][$ueId]) ? $validationsUE[$matricule][$ueId] : false;
                        $pdf->SetFillColor($validationUE ? 198 : 244, $validationUE ? 239 : 199, $validationUE ? 206 : 195);
                        $pdf->Cell($cellWidth, $rowHeight, $validationUE ? 'V' : 'NV', 1, 0, 'C', 1);
                    }
                    
                    // Moyenne semestre
                    $moyenneSem = isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : null;
                    $cellColor = getCellColor($moyenneSem);
                    $pdf->SetFillColor($cellColor[0], $cellColor[1], $cellColor[2]);
                    $pdf->Cell($cellWidth, $rowHeight, formatNote($moyenneSem), 1, 0, 'C', 1);
                    
                    // Décision semestre
                    $validationSem = isset($validationsSemestre[$matricule][$semId]) ? $validationsSemestre[$matricule][$semId]['est_valide'] : false;
                    $pdf->SetFillColor($validationSem ? 198 : 244, $validationSem ? 239 : 199, $validationSem ? 206 : 195);
                    $pdf->Cell($cellWidth, $rowHeight, $validationSem ? 'V' : 'NV', 1, 0, 'C', 1);
                }
                
                if ($afficherDeuxSemestres) {
                    // Moyenne annuelle
                    $moyenneAnn = isset($moyennesAnnuelles[$matricule]) ? $moyennesAnnuelles[$matricule] : null;
                    $cellColor = getCellColor($moyenneAnn);
                    $pdf->SetFillColor($cellColor[0], $cellColor[1], $cellColor[2]);
                    $pdf->Cell($cellWidth, $rowHeight, formatNote($moyenneAnn), 1, 0, 'C', 1);
                    
                    // Décision annuelle
                    $validationAnn = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule]['est_valide'] : false;
                    $pdf->SetFillColor($validationAnn ? 198 : 244, $validationAnn ? 239 : 199, $validationAnn ? 206 : 195);
                    $pdf->Cell($cellWidth, $rowHeight, $validationAnn ? 'V' : 'NV', 1, 0, 'C', 1);
                }
                
                $pdf->Ln();
                
                // Vérifier si on a besoin d'une nouvelle page
                if ($pdf->GetY() > $pdf->getPageHeight() - 25) {
                    $pdf->AddPage();
                }
            }
            
            // Ajouter une légende
            $pdf->Ln(10);
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->Cell(0, 5, 'LÉGENDE:', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 8);
            
            // Légende pour les couleurs
            $pdf->SetFillColor(198, 239, 206);
            $pdf->Cell(10, 5, '', 1, 0, 'C', 1);
            $pdf->Cell(40, 5, 'Note >= 10 (Validé)', 0, 0, 'L');
            
            $pdf->SetFillColor(255, 235, 156);
            $pdf->Cell(10, 5, '', 1, 0, 'C', 1);
            $pdf->Cell(40, 5, '8 <= Note < 10', 0, 0, 'L');
            
            $pdf->SetFillColor(244, 199, 195);
            $pdf->Cell(10, 5, '', 1, 0, 'C', 1);
            $pdf->Cell(40, 5, 'Note < 8 (Non validé)', 0, 1, 'L');
            
            // Ajouter des informations sur la délibération
            $pdf->Ln(5);
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->Cell(0, 5, 'INFORMATIONS SUR LA DÉLIBERATION', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 8);
            
            $pdf->Cell(40, 5, 'Session:', 0, 0, 'L');
            $pdf->Cell(100, 5, $sessionInfo['designSession'] . ' - ' . $sessionInfo['description'], 0, 1, 'L');
            
            $pdf->Cell(40, 5, 'Promotion:', 0, 0, 'L');
            $pdf->Cell(100, 5, $promotionInfo['designationPromotion'] . ' - Cycle: ' . $promotionInfo['cycle'], 0, 1, 'L');
            
            $pdf->Cell(40, 5, 'Année académique:', 0, 0, 'L');
            $pdf->Cell(100, 5, $anneeInfo['designation'], 0, 1, 'L');
            
            // Tableau des signatures
            $pdf->Ln(10);
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->Cell(0, 5, 'APPROBATION', 0, 1, 'C');
            $pdf->SetFont('dejavusans', '', 8);
            
            // Signatures
            $pdf->Cell($pdf->getPageWidth()/3, 5, 'Le Président du jury:', 0, 0, 'C');
            $pdf->Cell($pdf->getPageWidth()/3, 5, 'Le Secrétaire du jury:', 0, 0, 'C');
            $pdf->Cell($pdf->getPageWidth()/3, 5, 'Le Responsable académique:', 0, 1, 'C');
            
            // Espace pour signatures
            $pdf->Ln(15);
            
            // Récupérer les informations sur le jury si disponible
            if ($bureauId) {
                $juryInfo = $deliberation->getBureauJuryById($bureauId);
                
                if ($juryInfo) {
                    $presidentInfo = $juryInfo['president_id'] ? $agentModel->getAgentById($juryInfo['president_id']) : null;
                    $secretaireInfo = $juryInfo['secretaire_id'] ? $agentModel->getAgentById($juryInfo['secretaire_id']) : null;
                    
                    $pdf->Cell($pdf->getPageWidth()/3, 5, $presidentInfo ? $presidentInfo['noms'] : '', 0, 0, 'C');
                    $pdf->Cell($pdf->getPageWidth()/3, 5, $secretaireInfo ? $secretaireInfo['noms'] : '', 0, 0, 'C');
                    $pdf->Cell($pdf->getPageWidth()/3, 5, '', 0, 1, 'C');
                }
            }
            
            // Date d'édition
            $pdf->Ln(10);
            $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i'), 0, 1, 'R');
            
            // Output du PDF
            $filename = 'grille_notes_' . $promotionInfo['designationPromotion'] . '_' . $anneeInfo['designation'] . '_' 
                      . $sessionInfo['designSession'] . '_' . date('YmdHis') . '.pdf';
            $pdf->Output($filename, 'D');
            exit;
            
