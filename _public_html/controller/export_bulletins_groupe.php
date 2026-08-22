<?php
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/models/Universite.php';
require_once dirname(__DIR__).'/models/Deliberation.php';

ini_set('max_execution_time', 7200);
ini_set('memory_limit', '512M');

session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../connexion');
    exit();
}

// Activer le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Fonction de débogage
function debug_to_file($data, $label = '') {
    $output = date('Y-m-d H:i:s') . " - " . $label . ":\n";
    $output .= print_r($data, true) . "\n\n";
    file_put_contents('../debug_bulletins_groupe.log', $output, FILE_APPEND);
}

// Récupérer les paramètres
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

// Vérifier que tous les paramètres nécessaires sont fournis
if (!$bureauId || !$promotionId || !$sessionId || !$anneeId || (!$semestreId && !$afficherDeuxSemestres)) {
    die('Paramètres incomplets');
}

// Créer une instance de TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Initialiser les objets nécessaires
$universite = new Universite();
$deliberation = new Deliberation();

// Récupérer les informations de configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les informations du bureau, promotion, etc.
$bureau = $deliberation->getBureauJuryById($bureauId);
$promotion = $universite->getPromotionById($promotionId);
$session = $universite->getSessionById($sessionId);
$annee = $universite->getAnneeAcademiqueById($anneeId);

// Récupérer les informations de la section
$sectionInfo = null;
if ($promotion && isset($promotion['idPromotion'])) {
    try {
        $conn = Connexion::getInstance()->getPDO();
        $query = "SELECT s.* FROM section s 
                  INNER JOIN promotion p ON s.idsection = p.idsection 
                  WHERE p.idPromotion = :idPromotion";
        $stmt = $conn->prepare($query);
        $stmt->execute(['idPromotion' => $promotion['idPromotion']]);
        $sectionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        debug_to_file($sectionInfo, 'Informations de la section récupérées');
    } catch (Exception $e) {
        debug_to_file($e->getMessage(), 'Erreur lors de la récupération de la section');
    }
}

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Bulletins Individuels - ' . $promotion['designationPromotion']);
$pdf->SetSubject('Bulletins individuels officiels');
$pdf->SetKeywords('Étudiants, Bulletins, Notes, Officiels');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges (réduites pour plus d'espace)
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);

// Couleurs pour le design moderne
$primaryColor = array(44, 62, 80); // Bleu foncé
$secondaryColor = array(52, 73, 94); // Bleu-gris
$accentColor = array(0, 123, 194); // Bleu moyen

// Vérifier si c'est la deuxième session
$isDeuxiemeSession = $session && (stripos($session['designSession'], 'deuxième') !== false || 
                                  stripos($session['designSession'], 'deuxieme') !== false);

// Récupérer l'ID de la première session pour les comparaisons
$premiereSessionId = null;
if ($isDeuxiemeSession) {
    $premiereSessions = $universite->getSessions("Première session");
    if (!empty($premiereSessions)) {
        $premiereSession = $premiereSessions[0];
        $premiereSessionId = $premiereSession['idsession'];
    }
}

// Récupérer les semestres à afficher
$semestresAfficher = [];
if ($afficherDeuxSemestres) {
    $semestres = $universite->getSemestresByPromotion($promotionId);
    if (count($semestres) >= 2) {
        $semestresAfficher = array_slice($semestres, 0, 2);
    } else {
        $semestresAfficher = $semestres;
    }
} else {
    $semestresAfficher[] = ['idsemestre' => $semestreId];
}

// Récupérer les étudiants de la promotion
if ($isDeuxiemeSession) {
    $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresAfficher);
} else {
    $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
}

if (empty($etudiants)) {
    die('Aucun étudiant trouvé pour cette promotion');
}

$currentYear = $universite->getCurrentAcademicYear();

// Pour chaque étudiant, générer son bulletin
foreach ($etudiants as $etudiant) {
    $matricule = $etudiant['matricule'];
    
    // Tableau pour stocker les notes de chaque semestre
    $notesEtudiant = [];
    
    // Pour chaque semestre à afficher, récupérer les notes séparément
    foreach ($semestresAfficher as $semestre) {
        $semestreId = $semestre['idsemestre'];
        $semestreNotes = $deliberation->getNotesEtudiant($matricule, $sessionId, $anneeId, $semestreId);
        
        if (!empty($semestreNotes)) {
            $notesEtudiant[] = $semestreNotes[0];
        }
    }
    
    // Variables pour les statistiques globales
    $totalCredits = 0;
    $totalCreditsValides = 0;
    $totalMoyennePonderee = 0;
    $totalCoefficients = 0;
    $semestresStats = [];
    $notesManquantes = false;
    
    // Pour chaque semestre
    foreach ($notesEtudiant as $idxSemestre => $semestreData) {
        $semestreCreditsTotal = 0;
        $semestreCreditsValides = 0;
        $semestreMoyennePonderee = 0;
        $semestreCoefficients = 0;
        $semestreNotesManquantes = false;
        
        // Pour chaque UE
        foreach ($semestreData['ues'] as $ueIdx => $ueData) {
            $ue = $ueData['info'];
            $ueId = $ue['idUE'] ?? 0;
            
            // Vérifier si l'UE a été validée en première session
            $ueValideeEnPremiereSession = false;
            $uePremiereSession = null;
            
            if ($isDeuxiemeSession && $premiereSessionId) {
                $uePremiereSession = $deliberation->getUEValideePremiereSession($matricule, $ueId, $anneeId);
                $ueValideeEnPremiereSession = $uePremiereSession && isset($uePremiereSession['est_validee']) && $uePremiereSession['est_validee'] == 1;
            }
            
            // Vérification de la complétude des notes
            $ueNotesManquantes = false;
            $ecuesData = [];
            
            // Pour chaque ECUE de l'UE
            foreach ($ueData['ecues'] as $ecueIdx => $ecue) {
                $ecueId = $ecue['idECUE'] ?? 0;
                $notes = null;
                $ecueNotesManquantes = false;
                
                // Déterminer quelle session utiliser pour les notes
                if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                    $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $premiereSessionId, $anneeId);
                    if (!$notes || !isset($notes['CC']) || $notes['CC'] === null || 
                        !isset($notes['EX']) || $notes['EX'] === null || 
                        !isset($notes['MF']) || $notes['MF'] === null) {
                        $ecueNotesManquantes = true;
                    }
                } else if ($isDeuxiemeSession) {
                    $notePremiereSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $premiereSessionId, $anneeId);
                    $noteDeuxiemeSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                    
                    if ($notePremiereSession && isset($notePremiereSession['MF']) && 
                        $notePremiereSession['MF'] !== null && floatval($notePremiereSession['MF']) >= 10) {
                        $notes = $notePremiereSession;
                    } else {
                        $notes = $noteDeuxiemeSession;
                    }
                    
                    if (!$notes || !isset($notes['EX']) || $notes['EX'] === null || 
                        !isset($notes['MF']) || $notes['MF'] === null) {
                        $ecueNotesManquantes = true;
                    }
                } else {
                    $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                    if (!$notes || !isset($notes['CC']) || $notes['CC'] === null || 
                        !isset($notes['EX']) || $notes['EX'] === null || 
                        !isset($notes['MF']) || $notes['MF'] === null) {
                        $ecueNotesManquantes = true;
                    }
                }
                
                // Mettre à jour les données de l'ECUE
                $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['ecues'][$ecueIdx]['cc'] = $notes['CC'] ?? null;
                $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['ecues'][$ecueIdx]['examen'] = $notes['EX'] ?? null;
                $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['ecues'][$ecueIdx]['note'] = $notes['MF'] ?? null;
                
                $ecuesData[$ecueIdx] = [
                    'notes' => $notes,
                    'coefficient' => isset($ecue['coefficient']) ? floatval($ecue['coefficient']) : 0,
                    'notesManquantes' => $ecueNotesManquantes
                ];
                
                if ($ecueNotesManquantes) {
                    $ueNotesManquantes = true;
                }
            }
            
            // Calcul de la moyenne de l'UE
            $moyenneUE = null;
            $estValidee = false;
            $toutesEcuesCompletes = true;
            
            foreach ($ecuesData as $ecueData) {
                if ($ecueData['notesManquantes']) {
                    $toutesEcuesCompletes = false;
                    break;
                }
            }
            
            if ($isDeuxiemeSession && $ueValideeEnPremiereSession && $toutesEcuesCompletes) {
                $moyenneUE = $uePremiereSession['moyenne'];
                $estValidee = true;
            } else if (!$ueNotesManquantes && $toutesEcuesCompletes) {
                $uePoints = 0;
                $ueCoefficients = 0;
                
                foreach ($ecuesData as $ecueData) {
                    if (!$ecueData['notesManquantes']) {
                        $uePoints += floatval($ecueData['notes']['MF']) * $ecueData['coefficient'];
                        $ueCoefficients += $ecueData['coefficient'];
                    }
                }
                
                if ($ueCoefficients > 0) {
                    $moyenneUE = $uePoints / $ueCoefficients;
                    $estValidee = $moyenneUE >= 10;
                }
            }
            
            // Mettre à jour les informations de l'UE
            $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['info']['moyenne'] = $moyenneUE;
            $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['info']['est_validee'] = $estValidee ? 1 : 0;
            
            if ($ueNotesManquantes) {
                $semestreNotesManquantes = true;
                $notesManquantes = true;
            }
            
            // Statistiques
            if (isset($ue['nombre_credits'])) {
                $credits = is_numeric($ue['nombre_credits']) ? 
                           floatval($ue['nombre_credits']) : 
                           floatval(str_replace(',', '.', $ue['nombre_credits']));
                
                $semestreCreditsTotal += $credits;
                if ($estValidee) {
                    $semestreCreditsValides += $credits;
                }
                
                if ($moyenneUE !== null) {
                    $semestreMoyennePonderee += ($moyenneUE * $credits);
                    $semestreCoefficients += $credits;
                }
                
                $totalCredits += $credits;
                if ($estValidee) {
                    $totalCreditsValides += $credits;
                }
                
                if ($moyenneUE !== null) {
                    $totalMoyennePonderee += ($moyenneUE * $credits);
                    $totalCoefficients += $credits;
                }
            }
        }
        
        // Calculer moyenne du semestre
        $moyenneSemestre = null;
        if (!$semestreNotesManquantes && $semestreCoefficients > 0) {
            $moyenneSemestre = $semestreMoyennePonderee / $semestreCoefficients;
        }
        
        $semestresStats[$idxSemestre] = [
            'credits_total' => $semestreCreditsTotal,
            'credits_valides' => $semestreCreditsValides,
            'moyenne' => $moyenneSemestre,
            'notes_manquantes' => $semestreNotesManquantes,
            'est_valide' => (!$semestreNotesManquantes && $semestreCreditsValides == $semestreCreditsTotal && $moyenneSemestre >= 10)
        ];
    }
    
    // Calculer moyenne générale
    $moyenneGenerale = null;
    $pourcentageCredits = null;
    $pourcentageM = null;
    
    if (!$notesManquantes && $totalCoefficients > 0) {
        $moyenneGenerale = $totalMoyennePonderee / $totalCoefficients;
        $pourcentageCredits = ($totalCredits > 0) ? (($totalCreditsValides / $totalCredits) * 100) : 0;
        $pourcentageM = ($moyenneGenerale > 0) ? (($moyenneGenerale/20)*100) : 0;
    }
    
    // Déterminer l'état global et la mention
    $estValideGlobal = (!$notesManquantes && $totalCreditsValides == $totalCredits && $moyenneGenerale >= 10);
    
    $mention = '';
    if (!$notesManquantes && $moyenneGenerale !== null) {
        if ($moyenneGenerale >= 16) {
            $mention = 'Très Bien';
        } elseif ($moyenneGenerale >= 14) {
            $mention = 'Bien';
        } elseif ($moyenneGenerale >= 12) {
            $mention = 'Assez Bien';
        } elseif ($moyenneGenerale >= 10) {
            $mention = 'Satisfaction';
        }
    }
    
    // Générer le QR code data
    $qrData = "BULLETIN-" . $etudiant['matricule'] . "-" . $promotion['designationPromotion'] . "-" . date('dmY');
    
    // Ajouter une nouvelle page pour cet étudiant
    $pdf->AddPage();
    
    // Ajouter le logo en filigrane
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->SetAlpha(0.1);
            $centerX = ($pdf->getPageWidth() - 60) / 2;
            $centerY = ($pdf->getPageHeight() - 60) / 2;
            $pdf->Image($logoPath, $centerX, $centerY, 60, 0, '', '', '', false, 200, '', false, false, 0);
            $pdf->SetAlpha(1);
        }
    }
    
    // En-tête avec les informations de l'université
    if ($configUniversite) {
        $logoSize = 12;
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 10, 10, $logoSize, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }
        
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetY(10);
        $pdf->SetX(10 + $logoSize + 5);
        $pdf->Cell(0, 3, strtoupper($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPÉRIEUR ET UNIVERSITAIRE'), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 4, strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'), 0, 1, 'C');
        
        if ($sectionInfo && !empty($sectionInfo['designationSection'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 4, strtoupper($sectionInfo['designationSection']), 0, 1, 'C');
        }
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        
        $contactInfo = '';
        if (!empty($configUniversite['telephone'])) {
            $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
        }
        if (!empty($configUniversite['email'])) {
            $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
        }
        if (!empty($configUniversite['site_web'])) {
            $contactInfo .= 'Web: ' . $configUniversite['site_web'];
        }
        
        if (!empty($contactInfo)) {
            $pdf->Cell(0, 3, $contactInfo, 0, 1, 'C');
        }
        
        $pdf->Ln(7);
        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());
    }
    
    // Titre du document
    $pdf->Ln(4);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'FICHE DE VALIDATION DE CREDIT', 0, 1, 'C', 1);
    
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $promotion['designationPromotion'], 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 3, $session['description'] . ' - ' . $annee['designation'], 0, 1, 'C');
    
    // Informations étudiant
    $pdf->Ln(2);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->SetTextColor(60, 60, 60);
    
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 4, 'Matricule:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, $etudiant['matricule'], 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 4, 'Nom et Prénom:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, $etudiant['noms'], 1, 1, 'L', 0);
    
    // Pour chaque semestre
    foreach ($notesEtudiant as $idxSemestre => $semestreData) {
        $semestre = $semestreData['info'];
        $stats = $semestresStats[$idxSemestre];
        
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 4, 'Semestre ' . $semestre['numeroSemestre'], 0, 1, 'L');
        
        // En-têtes du tableau
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetDrawColor(200, 200, 200);
        
        $colUE = 110;
        $colCredit = 18;
        $colCC = 15;
        $colEX = 15;
        $colMoyenne = 17;
        $colValid = 15;
        
        $pdf->Cell($colUE, 4, 'UE/ECUE', 1, 0, 'L', 1);
        $pdf->Cell($colCredit, 4, 'Crédit', 1, 0, 'C', 1);
        $pdf->Cell($colCC, 4, 'CC', 1, 0, 'C', 1);
        $pdf->Cell($colEX, 4, 'EX', 1, 0, 'C', 1);
        $pdf->Cell($colMoyenne, 4, 'Moy.', 1, 0, 'C', 1);
        $pdf->Cell($colValid, 4, 'Valid.', 1, 1, 'C', 1);
        
        // Pour chaque UE du semestre
        foreach ($semestreData['ues'] as $ueData) {
            $ue = $ueData['info'];
            $estValidee = isset($ue['est_validee']) && $ue['est_validee'] == 1;
            $moyenneUE = isset($ue['moyenne']) ? $ue['moyenne'] : null;
            
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetTextColor(50, 50, 50);
            
            $nomUE = $ue['codeUE'] . ' - ' . $ue['designationUE'];
            $creditsUE = isset($ue['nombre_credits']) ? number_format(floatval($ue['nombre_credits']), 1) : '-';
            $moyenneText = ($moyenneUE !== null) ? number_format(floatval($moyenneUE), 2) : '-';
            $validationText = $estValidee ? 'V' : 'NV';
            
            if ($moyenneUE !== null && floatval($moyenneUE) < 10) {
                $pdf->SetTextColor(200, 50, 50);
            }
            
            $pdf->Cell($colUE, 4, $nomUE, 1, 0, 'L', 1);
            $pdf->Cell($colCredit, 4, $creditsUE, 1, 0, 'C', 1);
            $pdf->Cell($colCC + $colEX, 4, '', 1, 0, 'C', 1);
            $pdf->Cell($colMoyenne, 4, $moyenneText, 1, 0, 'C', 1);
            
            if ($estValidee) {
                $pdf->SetTextColor(50, 150, 50);
            } else {
                $pdf->SetTextColor(200, 50, 50);
            }
            $pdf->Cell($colValid, 4, $validationText, 1, 1, 'C', 1);
            
            // Pour chaque ECUE
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(80, 80, 80);
            
            foreach ($ueData['ecues'] as $ecue) {
                $cc = isset($ecue['cc']) ? $ecue['cc'] : null;
                $examen = isset($ecue['examen']) ? $ecue['examen'] : null;
                $note = isset($ecue['note']) ? $ecue['note'] : null;
                
                $coefficient = isset($ecue['coefficient']) ? number_format(floatval($ecue['coefficient']), 1) : '-';
                $ccText = ($cc !== null) ? number_format(floatval($cc), 2) : '-';
                $examenText = ($examen !== null) ? number_format(floatval($examen), 2) : '-';
                $noteText = ($note !== null) ? number_format(floatval($note), 2) : '-';
                
                $nomECUE = '    ' . $ecue['designationECUE'];
                
                $pdf->Cell($colUE, 3.5, $nomECUE, 1, 0, 'L', 0);
                $pdf->Cell($colCredit, 3.5, $coefficient, 1, 0, 'C', 0);
                $pdf->Cell($colCC, 3.5, $ccText, 1, 0, 'C', 0);
                $pdf->Cell($colEX, 3.5, $examenText, 1, 0, 'C', 0);
                
                if ($note !== null && floatval($note) < 10) {
                    $pdf->SetTextColor(200, 50, 50);
                } else {
                    $pdf->SetTextColor(80, 80, 80);
                }
                $pdf->Cell($colMoyenne, 3.5, $noteText, 1, 0, 'C', 0);
                $pdf->Cell($colValid, 3.5, '', 1, 1, 'C', 0);
                
                $pdf->SetTextColor(80, 80, 80);
            }
        }
        
        // Résumé du semestre
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor(100, 100, 100);
        
        $resumeText = '';
        if ($stats['notes_manquantes'] || $stats['moyenne'] === null) {
            $resumeText = 'Notes incomplètes | Crédits validés: ' . 
                $stats['credits_valides'] . '/' . $stats['credits_total'];
        } else {
            $resumeText = 'Moyenne: ' . number_format($stats['moyenne'], 2) . ' | Crédits validés: ' . 
                $stats['credits_valides'] . '/' . $stats['credits_total'] . ' (' . 
                number_format(($stats['moyenne'] / 20) * 100, 2) . '%)';
        }
        
        $pdf->Cell(0, 3, $resumeText, 0, 1, 'R');
        $pdf->Ln(1);
    }
    
    // Résultats globaux
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->Cell(0, 4, 'Résultats globaux', 0, 1, 'L');
    
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 7);
    
    $pageWidth = $pdf->getPageWidth() - 20;
    
    if (!$afficherDeuxSemestres) {
        $colMoy = $pageWidth * 0.18;
        $colCredits = $pageWidth * 0.25;
        $colPourcent = $pageWidth * 0.18;
        $colDecision = $pageWidth * 0.24;
        $colEtat = $pageWidth * 0.15;
        
        $pdf->Cell($colMoy, 4, 'Moyenne', 1, 0, 'C', 1);
        $pdf->Cell($colCredits, 4, 'Crédits validés', 1, 0, 'C', 1);
        $pdf->Cell($colPourcent, 4, 'Pourcentage', 1, 0, 'C', 1);
        $pdf->Cell($colDecision, 4, 'Décision', 1, 0, 'C', 1);
        $pdf->Cell($colEtat, 4, 'État', 1, 1, 'C', 1);
    } else {
        $colMoy = $pageWidth * 0.18;
        $colCredits = $pageWidth * 0.25;
        $colPourcent = $pageWidth * 0.18;
        $colDecision = $pageWidth * 0.24;
        $colMention = $pageWidth * 0.15;
        
        $pdf->Cell($colMoy, 4, 'Moyenne', 1, 0, 'C', 1);
        $pdf->Cell($colCredits, 4, 'Crédits validés', 1, 0, 'C', 1);
        $pdf->Cell($colPourcent, 4, 'Pourcentage', 1, 0, 'C', 1);
        $pdf->Cell($colDecision, 4, 'Décision', 1, 0, 'C', 1);
        $pdf->Cell($colMention, 4, 'Mention', 1, 1, 'C', 1);
    }
    
    // Données du tableau
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(50, 50, 50);
    
    $moyenneText = ($moyenneGenerale !== null) ? number_format($moyenneGenerale, 2) : 'N/A';
    $creditsText = $totalCreditsValides . '/' . $totalCredits;
    $pourcentageText = ($pourcentageM !== null) ? number_format($pourcentageM, 2) . '%' : 'N/A';
    
    // Déterminer la décision
    $decisionText = '';
    $mentionEtatText = '-';
    $decisionColor = array(50, 50, 50);
    
    if ($afficherDeuxSemestres) {
        if ($notesManquantes) {
            $decisionText = 'INCOMPLET';
            $decisionColor = array(200, 50, 50);
        } else if ($isDeuxiemeSession) {
            if ($estValideGlobal) {
                $decisionText = 'ADMIS SANS RACHAT';
                $decisionColor = array(50, 150, 50);
                $mentionEtatText = $mention;
            } else if ($pourcentageCredits !== null && $pourcentageCredits >= 75 && $moyenneGenerale >= 10) {
                $decisionText = 'ADMIS AVEC RACHAT';
                $decisionColor = array(50, 150, 50);
                $mentionEtatText = $mention;
            } else {
                $decisionText = 'AJOURNÉ';
                $decisionColor = array(200, 50, 50);
            }
        } else {
            if ($estValideGlobal) {
                $decisionText = 'ADMIS SANS RACHAT';
                $decisionColor = array(50, 150, 50);
                $mentionEtatText = $mention;
            } else {
                $decisionText = 'ADMIS AU RATTRAPAGE';
                $decisionColor = array(255, 165, 0);
            }
        }
    } else {
        if ($notesManquantes) {
            $decisionText = 'INCOMPLET';
            $decisionColor = array(200, 50, 50);
            $mentionEtatText = 'INCOMPLET';
        } else if ($totalCreditsValides == $totalCredits) {
            $decisionText = 'VALIDÉ TOTALEMENT';
            $decisionColor = array(50, 150, 50);
            $mentionEtatText = 'COMPLET';
        } else if ($totalCreditsValides > 0) {
            $decisionText = 'VALIDÉ PARTIELLEMENT';
            $decisionColor = array(255, 165, 0);
            $mentionEtatText = 'INCOMPLET';
        } else {
            $decisionText = 'NON VALIDÉ';
            $decisionColor = array(200, 50, 50);
            $mentionEtatText = 'INCOMPLET';
        }
    }
    
    if (!$afficherDeuxSemestres) {
        $pdf->Cell($colMoy, 5, $moyenneText, 1, 0, 'C', 0);
        $pdf->Cell($colCredits, 5, $creditsText, 1, 0, 'C', 0);
        $pdf->Cell($colPourcent, 5, $pourcentageText, 1, 0, 'C', 0);
        
        $pdf->SetTextColor($decisionColor[0], $decisionColor[1], $decisionColor[2]);
        $pdf->Cell($colDecision, 5, $decisionText, 1, 0, 'C', 0);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell($colEtat, 5, $mentionEtatText, 1, 1, 'C', 0);
    } else {
        $pdf->Cell($colMoy, 5, $moyenneText, 1, 0, 'C', 0);
        $pdf->Cell($colCredits, 5, $creditsText, 1, 0, 'C', 0);
        $pdf->Cell($colPourcent, 5, $pourcentageText, 1, 0, 'C', 0);
        
        $pdf->SetTextColor($decisionColor[0], $decisionColor[1], $decisionColor[2]);
        $pdf->Cell($colDecision, 5, $decisionText, 1, 0, 'C', 0);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell($colMention, 5, $mentionEtatText, 1, 1, 'C', 0);
    }
    
    // QR Code et signature
    $pdf->Ln(5);
    
    $qrX = 10;
    $qrY = $pdf->GetY();
    
    try {
        $pdf->write2DBarcode($qrData, 'QRCODE,L', $qrX, $qrY, 15, 15, array(), 'N');
    } catch (Exception $e) {
        $pdf->SetXY($qrX, $qrY);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(15, 15, 'Code QR\nindisponible', 1, 0, 'C');
    }
    
    $pdf->SetXY($qrX, $qrY + 16);
    $pdf->SetFont('helvetica', '', 5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(15, 2, 'Scannez pour vérifier', 0, 0, 'C');
    
    // Signature
    $signX = $pdf->getPageWidth() - 60;
    $signY = $qrY;
    
    $pdf->SetXY($signX, $signY);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(80, 80, 80);
    
    $pdf->Cell(50, 3, 'Doyen / Chef de Section', 0, 1, 'R');
    $pdf->SetXY($signX, $signY + 12);
    $pdf->Cell(50, 3, '___________________', 0, 1, 'R');
    
    // Footer
    $pdf->SetXY(10, $pdf->getPageHeight() - 10);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(120, 120, 120);
    $footerText = 'Imprimé par ' . $_SESSION['nom'] . ', le ' . date('d/m/Y');
    $pdf->Cell(0, 3, $footerText, 0, 0, 'C');
    
    // Filigrane texte
    $pdf->StartTransform();
    $pdf->SetFont('helvetica', 'B', 40);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->Rotate(45, $pdf->getPageWidth()/2, $pdf->getPageHeight()/2);
    $textWidth = $pdf->GetStringWidth("COPIE CERTIFIÉE");
    $pdf->SetAlpha(0.2);
    $pdf->Text($pdf->getPageWidth()/2 - $textWidth/2, $pdf->getPageHeight()/2, "COPIE CERTIFIÉE");
    $pdf->StopTransform();
    $pdf->SetAlpha(1);
}

// Générer le PDF
$filename = 'Bulletins_' . $promotionId . '_' . $sessionId . '_' . date('Ymd') . '.pdf';
$pdf->Output($filename, 'I');
?>