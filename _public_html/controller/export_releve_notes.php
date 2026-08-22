<?php
ob_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/models/Universite.php';
require_once dirname(__DIR__).'/models/Deliberation.php';
require_once dirname(__DIR__).'/models/NumeroReleve.php';

session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../connexion');
    exit();
}

// Activer le débogage
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Fonction de débogage
function debug_to_file($data, $label = '') {
    $output = date('Y-m-d H:i:s') . " - " . $label . ":\n";
    $output .= print_r($data, true) . "\n\n";
    file_put_contents('../debug_bulletin.log', $output, FILE_APPEND);
}

// Récupérer les paramètres
$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : null;
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

// Vérifier que tous les paramètres nécessaires sont fournis
if (!$matricule || !$bureauId || !$promotionId || !$sessionId || !$anneeId || (!$semestreId && !$afficherDeuxSemestres)) {
    die('Paramètres incomplets');
}

// Créer une instance de TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Initialiser les objets nécessaires
$universite = new Universite();
$deliberation = new Deliberation();
$numeroReleve = new NumeroReleve();

// Récupérer les informations de configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les informations de l'étudiant
$etudiant = $deliberation->getEtudiantByMatricule($matricule);
if (!$etudiant) {
    die('Étudiant non trouvé');
}

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Relevé de Notes - ' . $etudiant['noms']);
$pdf->SetSubject('Relevé de notes officiel');
$pdf->SetKeywords('Étudiant, Notes, Relevé, Officiel');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges (réduites pour plus d'espace)
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false); // Désactiver le saut de page automatique

// Couleurs pour le design
$primaryColor = array(44, 62, 80); // Bleu foncé
$secondaryColor = array(52, 73, 94); // Bleu-gris
$accentColor = array(0, 123, 194); // Bleu moyen

// Récupérer les informations du bureau, promotion, etc.
$bureau = $deliberation->getBureauJuryById($bureauId);
$promotion = $universite->getPromotionById($promotionId);
$session = $universite->getSessionById($sessionId);
$annee = $universite->getAnneeAcademiqueById($anneeId);

// L'orientation est déjà disponible dans $promotion (retournée par getPromotionById)
$orientationInfo = isset($promotion['designationOrientation']) ? $promotion['designationOrientation'] : null;

// Récupérer les informations de la section
$sectionInfo = null;
if ($promotion && isset($promotion['idpromotion'])) {
    try {
        $conn = Connexion::getInstance()->getPDO();
        $query = "SELECT s.* FROM section s 
                  INNER JOIN orientation o ON s.idsection = o.section_idsection
                  WHERE o.idorientation = :idorientation";
        $stmt = $conn->prepare($query);
        $stmt->execute(['idorientation' => $promotion['orientation_idorientation']]);
        $sectionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        debug_to_file($e->getMessage(), 'Erreur lors de la récupération de la section');
    }
}

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
    // Récupérer les semestres de la promotion
    $semestres = $universite->getSemestresByPromotion($promotionId);
    if (count($semestres) >= 2) {
        $semestresAfficher = array_slice($semestres, 0, 2);
    } else {
        $semestresAfficher = $semestres;
    }
} else {
    // Récupérer uniquement le semestre spécifié
    $semestresAfficher[] = ['idsemestre' => $semestreId];
}

// Tableau pour stocker les notes de chaque semestre
$notesEtudiant = [];

// Pour chaque semestre à afficher, récupérer les notes séparément
foreach ($semestresAfficher as $semestre) {
    $semestreId = $semestre['idsemestre'];
    
    // Récupérer les notes pour ce semestre spécifique
    $semestreNotes = $deliberation->getNotesEtudiant($matricule, $sessionId, $anneeId, $semestreId);
    
    // S'assurer que nous avons des données pour ce semestre
    if (!empty($semestreNotes)) {
        $notesEtudiant[] = $semestreNotes[0]; // Ajouter le premier élément du tableau
    }
}

$currentYear = $universite->getCurrentAcademicYear();

// Récupérer le responsable de section (Chef de section ou Doyen) depuis responsable_section
$responsableSection = null;
if ($sectionInfo && isset($sectionInfo['idsection'])) {
    try {
        $conn = Connexion::getInstance()->getPDO();
        $query = "SELECT rs.* FROM responsable_section rs
                  WHERE rs.section_idsection = :sectionId
                  AND rs.annee_acad_idannee_acad = :anneeId
                  AND rs.est_chef = 1
                  ORDER BY rs.idresponsable_section DESC
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':sectionId' => $sectionInfo['idsection'],
            ':anneeId' => $currentYear['idannee_acad']
        ]);
        $responsableSection = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        debug_to_file($e->getMessage(), 'Erreur lors de la récupération du responsable');
    }
}

// Débogage des notes
debug_to_file($notesEtudiant, 'Notes récupérées par semestre');

// Variables pour les statistiques globales
$totalCredits = 0;
$totalCreditsValides = 0;
$totalMoyennePonderee = 0;
$totalCoefficients = 0;
$semestresStats = [];
$notesManquantes = false; // Indicateur pour les notes manquantes

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
        
        // Vérifier si l'UE a été validée en première session (si on est en deuxième session)
        $ueValideeEnPremiereSession = false;
        $uePremiereSession = null;
        
        if ($isDeuxiemeSession && $premiereSessionId) {
            $uePremiereSession = $deliberation->getUEValideePremiereSession($matricule, $ueId, $anneeId);
            $ueValideeEnPremiereSession = $uePremiereSession && isset($uePremiereSession['est_validee']) && $uePremiereSession['est_validee'] == 1;
        }
        
        // PHASE 1: Vérification de la complétude des notes pour toutes les ECUEs
        $ueNotesManquantes = false;
        $ecuesData = []; // Stocker les données des ECUEs pour le calcul ultérieur
        
        // Pour chaque ECUE de l'UE, vérifier d'abord si toutes les notes sont complètes
        foreach ($ueData['ecues'] as $ecueIdx => $ecue) {
            $ecueId = $ecue['idECUE'] ?? 0;
            $notes = null;
            $ecueNotesManquantes = false;
            
            // Si l'UE a été validée en première session et qu'on est en deuxième session,
            // on utilise toujours les notes de première session
            if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $premiereSessionId, $anneeId);
                
                // Vérifier si les notes sont complètes
                if (!$notes || !isset($notes['CC']) || $notes['CC'] === null || 
                    !isset($notes['EX']) || $notes['EX'] === null || 
                    !isset($notes['MF']) || $notes['MF'] === null) {
                    $ecueNotesManquantes = true;
                }
                    
            } 
            // Sinon, on doit déterminer quelle session utiliser pour cette ECUE
            else if ($isDeuxiemeSession) {
                $notePremiereSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $premiereSessionId, $anneeId);
                $noteDeuxiemeSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                
                // Si l'ECUE avait une note >= 10 en première session, utiliser cette note
                if ($notePremiereSession && isset($notePremiereSession['MF']) && 
                    $notePremiereSession['MF'] !== null && floatval($notePremiereSession['MF']) >= 10 ) {
                    $notes = $notePremiereSession;
                    
                    // Vérifier si les notes sont complètes
                    if (!isset($notes['CC']) || $notes['CC'] === null || 
                        !isset($notes['EX']) || $notes['EX'] === null || 
                        !isset($notes['MF']) || $notes['MF'] === null) {
                        $ecueNotesManquantes = true;
                    }
                } 
                // Sinon, utiliser la note de deuxième session
                else {
                    $notes = $noteDeuxiemeSession;
                    
                    // En deuxième session, vérifier au moins EX et MF
                    if (!$notes || !isset($notes['EX']) || $notes['EX'] === null || 
                        !isset($notes['MF']) || $notes['MF'] === null) {
                        $ecueNotesManquantes = true;
                    }
                }
            } 
            // En première session, utiliser simplement les notes de première session
            else {
                $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                
                // Vérifier si les notes sont complètes
                if (!$notes || !isset($notes['CC']) || $notes['CC'] === null || 
                    !isset($notes['EX']) || $notes['EX'] === null || 
                    !isset($notes['MF']) || $notes['MF'] === null) {
                    $ecueNotesManquantes = true;
                }
            }
            
            // Mettre à jour les données de l'ECUE pour l'affichage
            $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['ecues'][$ecueIdx]['cc'] = $notes['CC'] ?? null;
            $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['ecues'][$ecueIdx]['examen'] = $notes['EX'] ?? null;
            $notesEtudiant[$idxSemestre]['ues'][$ueIdx]['ecues'][$ecueIdx]['note'] = $notes['MF'] ?? null;
            
            // Stocker les données pour le calcul ultérieur
            $ecuesData[$ecueIdx] = [
                'notes' => $notes,
                'coefficient' => isset($ecue['coefficient']) ? floatval($ecue['coefficient']) : 0,
                'notesManquantes' => $ecueNotesManquantes
            ];
            
            // Si cette ECUE a des notes manquantes, marquer l'UE entière comme ayant des notes manquantes
            if ($ecueNotesManquantes) {
                $ueNotesManquantes = true;
            }
        }
        
        // PHASE 2: Calcul de la moyenne de l'UE seulement si toutes les ECUEs ont des notes complètes
        $moyenneUE = null;
        $estValidee = false;

        $toutesEcuesCompletes = true; // Vérifier que toutes les ECUEs sont complètes

        foreach ($ecuesData as $ecueData) {
            if ($ecueData['notesManquantes']) {
                $toutesEcuesCompletes = false;
                break;
            }
        }
        
        // Si l'UE était validée en première session et qu'on est en deuxième session
        if ($isDeuxiemeSession && $ueValideeEnPremiereSession && $toutesEcuesCompletes) {
            $moyenneUE = $uePremiereSession['moyenne'];
            $estValidee = true;
        } 
        // Sinon, calculer la moyenne seulement si aucune ECUE n'a de notes manquantes
        else if (!$ueNotesManquantes && $toutesEcuesCompletes) {
            $uePoints = 0;
            $ueCoefficients = 0;
            
            
            // Calculer la moyenne en utilisant les données stockées
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
        
        // Si des notes sont manquantes pour cette UE, marquer le semestre comme ayant des notes manquantes
        if ($ueNotesManquantes) {
            $semestreNotesManquantes = true;
            $notesManquantes = true;
        }
        
        // Vérifier si nous avons les données nécessaires pour les statistiques
        if (isset($ue['nombre_credits'])) {
            $credits = is_numeric($ue['nombre_credits']) ? 
                       floatval($ue['nombre_credits']) : 
                       floatval(str_replace(',', '.', $ue['nombre_credits']));
            
            // Accumuler pour le semestre
            $semestreCreditsTotal += $credits;
            if ($estValidee) {
                $semestreCreditsValides += $credits;
            }
            
            // Accumuler pour la moyenne du semestre seulement si l'UE a une moyenne valide
            if ($moyenneUE !== null) {
                $semestreMoyennePonderee += ($moyenneUE * $credits);
                $semestreCoefficients += $credits;
            }
            
            // Accumuler pour le global
            $totalCredits += $credits;
            if ($estValidee) {
                $totalCreditsValides += $credits;
            }
            
            // Accumuler pour la moyenne générale seulement si l'UE a une moyenne valide
            if ($moyenneUE !== null) {
                $totalMoyennePonderee += ($moyenneUE * $credits);
                $totalCoefficients += $credits;
            }
        }
    }
    
    // Calculer moyenne du semestre seulement s'il n'y a pas de notes manquantes
    $moyenneSemestre = null;
    if (!$semestreNotesManquantes && $semestreCoefficients > 0) {
        $moyenneSemestre = $semestreMoyennePonderee / $semestreCoefficients;
    }
    
    // Stocker les stats du semestre
    $semestresStats[$idxSemestre] = [
        'credits_total' => $semestreCreditsTotal,
        'credits_valides' => $semestreCreditsValides,
        'moyenne' => $moyenneSemestre,
        'notes_manquantes' => $semestreNotesManquantes,
        'est_valide' => (!$semestreNotesManquantes && $semestreCreditsValides == $semestreCreditsTotal && $moyenneSemestre >= 10)
    ];
    
    // Débogage des stats du semestre
    debug_to_file($semestresStats[$idxSemestre], 'Stats du semestre ' . $idxSemestre);
}

// Calculer moyenne générale seulement s'il n'y a pas de notes manquantes
$moyenneGenerale = null;
$pourcentageCredits = null;
$pourcentageM = null;

if (!$notesManquantes && $totalCoefficients > 0) {
    $moyenneGenerale = $totalMoyennePonderee / $totalCoefficients;
    $pourcentageCredits = ($totalCredits > 0) ? 
                         (($totalCreditsValides / $totalCredits) * 100) : 0;
    $pourcentageM = ($moyenneGenerale > 0) ? (($moyenneGenerale/20)*100) : 0;
}

// Débogage des stats globales
debug_to_file([
    'totalCredits' => $totalCredits,
    'totalCreditsValides' => $totalCreditsValides,
    'moyenneGenerale' => $moyenneGenerale,
    'pourcentageCredits' => $pourcentageCredits,
    'notesManquantes' => $notesManquantes
], 'Stats globales calculées');

// Déterminer l'état global et la mention
$estValideGlobal = (!$notesManquantes && $totalCreditsValides == $totalCredits && $moyenneGenerale >= 10);

// Déterminer la mention seulement si pas de notes manquantes
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

// Pré-calculer la décision pour utiliser dans le QR code (avant la page PDF)
$decisionText = '';
$mentionEtatText = '-';
$decisionColor = array(50, 50, 50);

// Afficher la décision en fonction du type de résultat
if ($afficherDeuxSemestres) {
    // Pour les résultats annuels
    if ($notesManquantes) {
        $decisionText = 'INCOMPLET';
        $decisionColor = array(200, 50, 50);
    } 
    // Logique différente selon la session
    else if ($isDeuxiemeSession) {
        // En deuxième session
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
    } 
    // En première session
    else {
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
    // Pour un semestre
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

// Générer le QR code avec informations complètes du relevé
// Format: RELEVE~MATRICULE~NOM~PROMOTION~MOYENNE~CREDITS_VALIDES~CREDITS_TOTAL~POURCENTAGE~DECISION
$moyenneQR = ($moyenneGenerale !== null) ? number_format($moyenneGenerale, 2) : 'N/A';
$pourcentageQR = ($pourcentageM !== null) ? number_format($pourcentageM, 2) : 'N/A';

// Déterminer la décision pour le QR (déjà calculée plus haut)
$decisionQR = $decisionText; // Utiliser la variable $decisionText déjà définie

// Construire les données du QR code
$qrData = "RELEVE~" . 
          $etudiant['matricule'] . "~" .
          $etudiant['noms'] . "~" .
          $promotion['designationPromotion'] . "~" .
          $moyenneQR . "~" .
          $totalCreditsValides . "~" .
          $totalCredits . "~" .
          $pourcentageQR . "~" .
          $decisionQR;

// Ajouter une page
$pdf->AddPage();

// Ajouter le logo en filigrane (watermark) au centre de la page
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $pdf->SetAlpha(0.1); // Transparence de 10%
        $centerX = ($pdf->getPageWidth() - 60) / 2;
        $centerY = ($pdf->getPageHeight() - 60) / 2;
        $pdf->Image($logoPath, $centerX, $centerY, 60, 0, '', '', '', false, 200, '', false, false, 0);
        $pdf->SetAlpha(1); // Remettre l'opacité normale
    }
}

// En-tête avec les informations de l'université
if ($configUniversite) {
    // Logo de l'université (bien visible, positionné à gauche)
    $logoSize = 12;
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, $logoSize, 0, '', '', '', false, 200, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université (ajustés pour éviter le logo)
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetY(10);
    $pdf->SetX(10 + $logoSize + 5); // Décaler le texte à droite du logo
    $pdf->Cell(0, 3, strtoupper($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPÉRIEUR ET UNIVERSITAIRE'), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 4, strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'), 0, 1, 'C');
    
    // Afficher le nom de la section si elle existe
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
    
    // Ligne de séparation (bien en dessous du logo)
    $pdf->Ln(7);
    $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
    $pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());
}

// Récupérer ou créer le numéro permanent du relevé
$numReleve = $numeroReleve->getOrCreateNumeroReleve($etudiant['idetudiant'], $promotionId, $sessionId, $anneeId);

// Titre du document (compacté)
$pdf->Ln(4);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'RELEVÉ DE NOTES N°' . $numReleve, 0, 1, 'C', 1);

$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('helvetica', 'B', 9);
$promotionText = $promotion['designationPromotion'];
if ($orientationInfo) {
    $promotionText .= ' - ' . $orientationInfo;
}
$pdf->Cell(0, 4, $promotionText, 0, 1, 'C');

$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 3, $session['description'] . ' - ' . $annee['designation'], 0, 1, 'C');

// Informations étudiant (compactées)
$pdf->Ln(2);
$pdf->SetFillColor(248, 249, 250);
$pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
$pdf->SetTextColor(60, 60, 60);

$dateNaissance = isset($etudiant['dateNaissance']) && $etudiant['dateNaissance'] ? 
                 date('d/m/Y', strtotime($etudiant['dateNaissance'])) : '-';
$lieuNaissance = isset($etudiant['lieuNaissance']) ? $etudiant['lieuNaissance'] : '-';

// Première ligne: Matricule et Date Naissance
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(30, 4, 'Matricule:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(75, 4, $etudiant['matricule'], 1, 0, 'L', 0);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(40, 4, 'Date Naissance:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 4, $dateNaissance, 1, 1, 'L', 0);

// Deuxième ligne: Nom et Prénom et Lieu Naissance
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(30, 4, 'Nom et Prénom:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(75, 4, $etudiant['noms'], 1, 0, 'L', 0);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(40, 4, 'Lieu Naissance:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 4, $lieuNaissance, 1, 1, 'L', 0);

// Pour chaque semestre dans les notes avec TCPDF
foreach ($notesEtudiant as $idxSemestre => $semestreData) {
    $semestre = $semestreData['info'];
    $stats = $semestresStats[$idxSemestre];
    
    // Titre du semestre (compacté)
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->Cell(0, 4, 'Semestre ' . $semestre['numeroSemestre'], 0, 1, 'L');
    
    // En-têtes du tableau (compactés)
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetDrawColor(200, 200, 200);
    
    // Colonnes du tableau ajustées pour plus d'espace
    $colUE = 135;
    $colCredit = 18;
    $colMoyenne = 22;
    $colValid = 15;
    
    $pdf->Cell($colUE, 4, 'UE/ECUE', 1, 0, 'L', 1);
    $pdf->Cell($colCredit, 4, 'Crédit', 1, 0, 'C', 1);
    $pdf->Cell($colMoyenne, 4, 'Moy.', 1, 0, 'C', 1);
    $pdf->Cell($colValid, 4, 'Valid.', 1, 1, 'C', 1);
    
    // Pour chaque UE du semestre
    foreach ($semestreData['ues'] as $ueData) {
        $ue = $ueData['info'];
        $estValidee = isset($ue['est_validee']) && $ue['est_validee'] == 1;
        $moyenneUE = isset($ue['moyenne']) ? $ue['moyenne'] : null;
        
        // Ligne UE avec fond gris clair (plus compacte)
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(50, 50, 50);
        
        $nomUE = $ue['codeUE'] . ' - ' . $ue['designationUE'];
        $creditsUE = isset($ue['nombre_credits']) ? number_format(floatval($ue['nombre_credits']), 1) : '-';
        $moyenneText = ($moyenneUE !== null) ? number_format(floatval($moyenneUE), 2) : '-';
        $validationText = $estValidee ? 'V' : 'NV';
        
        // Couleur spéciale pour moyenne < 10
        if ($moyenneUE !== null && floatval($moyenneUE) < 10) {
            $pdf->SetTextColor(200, 50, 50);
        }
        
        $pdf->Cell($colUE, 4, $nomUE, 1, 0, 'L', 1);
        $pdf->Cell($colCredit, 4, $creditsUE, 1, 0, 'C', 1);
        
        // Remettre couleur pour moyenne
        $pdf->Cell($colMoyenne, 4, $moyenneText, 1, 0, 'C', 1);
        
        // Couleur pour validation
        if ($estValidee) {
            $pdf->SetTextColor(50, 150, 50);
        } else {
            $pdf->SetTextColor(200, 50, 50);
        }
        $pdf->Cell($colValid, 4, $validationText, 1, 1, 'C', 1);
        
        // Pour chaque ECUE de l'UE (plus compact)
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetTextColor(80, 80, 80);
        
        foreach ($ueData['ecues'] as $ecue) {
            $note = isset($ecue['note']) ? $ecue['note'] : null;
            $coefficient = isset($ecue['coefficient']) ? number_format(floatval($ecue['coefficient']), 1) : '-';
            $noteText = ($note !== null) ? number_format(floatval($note), 2) : '-';
            
            // Indentation pour les ECUEs
            $nomECUE = '    ' . $ecue['designationECUE'];
            
            $pdf->Cell($colUE, 3.5, $nomECUE, 1, 0, 'L', 0);
            $pdf->Cell($colCredit, 3.5, $coefficient, 1, 0, 'C', 0);
            
            // Couleur pour note < 10
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
    
    // Résumé du semestre (plus compact)
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

// Résultats globaux (plus compact)
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->Cell(0, 4, 'Résultats globaux', 0, 1, 'L');

// En-têtes du tableau des résultats (compactés)
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 7);

// Calculer les largeurs responsives des colonnes
$pageWidth = $pdf->getPageWidth() - 20; // Largeur utilisable (marges de 10 de chaque côté)

if (!$afficherDeuxSemestres) {
    // Tableau pour un semestre: Moyenne | Crédits validés | Pourcentage | Décision | État
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
    // Tableau pour deux semestres: Moyenne | Crédits validés | Pourcentage | Décision | Mention
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

// Données du tableau (compactées)
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(50, 50, 50);

$moyenneText = ($moyenneGenerale !== null) ? number_format($moyenneGenerale, 2) : 'N/A';
$creditsText = $totalCreditsValides . '/' . $totalCredits;
$pourcentageText = ($pourcentageM !== null) ? number_format($pourcentageM, 2) . '%' : 'N/A';



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

// QR Code et signatures (plus compact)
$pdf->Ln(5);

// Position pour QR code (plus petit)
$qrX = 10;
$qrY = $pdf->GetY();

// Générer et positionner le QR code (plus petit)
try {
    $pdf->write2DBarcode($qrData, 'QRCODE,L', $qrX, $qrY, 15, 15, array(), 'N');
} catch (Exception $e) {
    // Si erreur QR code, afficher juste le texte
    $pdf->SetXY($qrX, $qrY);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(15, 15, 'Code QR\nindisponible', 1, 0, 'C');
}

// Texte sous le QR code (plus petit)
$pdf->SetXY($qrX, $qrY + 16);
$pdf->SetFont('helvetica', '', 5);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(15, 2, 'Scannez pour vérifier', 0, 0, 'C');

// Premier signataire: Doyen de la Faculté / Chef de Section (centre-gauche, après QR code)
$doyenX = 35;
$doyenY = $qrY;

$pdf->SetXY($doyenX, $doyenY);
$pdf->SetFont('helvetica', '', 5);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(45, 2, 'Doyen de la Faculté / Chef de Section', 0, 1, 'C');

$pdf->SetXY($doyenX, $doyenY + 8);
if ($responsableSection && !empty($responsableSection['noms'])) {
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->Cell(45, 3, $responsableSection['noms'], 0, 1, 'C');
} else {
    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(45, 3, 'A configurer', 0, 1, 'C');
}

// Deuxième signataire: Le Secrétaire Général Académique (à droite)
$rightColWidth = 50;
$rightMargin = 10;
$signX = $pdf->getPageWidth() - $rightColWidth - $rightMargin;
$signY = $qrY;

$pdf->SetXY($signX, $signY);
$pdf->SetFont('helvetica', '', 5);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell($rightColWidth, 2, 'Le Secrétaire Général Académique', 0, 1, 'C');

$pdf->SetXY($signX, $signY + 8);
if (!empty($configUniversite['nom_secretaire_general'])) {
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->Cell($rightColWidth, 3, $configUniversite['nom_secretaire_general'], 0, 1, 'C');
} else {
    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell($rightColWidth, 3, 'A configurer', 0, 1, 'C');
}

// Ligne de séparation avant le footer
$pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
$pdf->Line(10, $pdf->getPageHeight() - 10, $pdf->getPageWidth() - 10, $pdf->getPageHeight() - 10);

// Footer avec informations de l'université (en bas de page fixe)
$pdf->SetXY(10, $pdf->getPageHeight() - 8);
$pdf->SetFont('helvetica', '', 5);
$pdf->SetTextColor(100, 100, 100);

$footerParts = [];

if (!empty($configUniversite['adresse'])) {
    $footer_addr = $configUniversite['adresse'];
    if (!empty($configUniversite['ville'])) {
        $footer_addr .= ', ' . $configUniversite['ville'];
    }
    $footerParts[] = $footer_addr;
}

if (!empty($configUniversite['telephone'])) {
    $footerParts[] = 'Tél: ' . $configUniversite['telephone'];
}

if (!empty($configUniversite['email'])) {
    $footerParts[] = $configUniversite['email'];
}

if (!empty($configUniversite['site_web'])) {
    $footerParts[] = 'Web: ' . $configUniversite['site_web'];
}

$footerParts[] = 'Imprimé par ' . $_SESSION['nom'] . ', le ' . date('d/m/Y');

$footerText = implode(' | ', $footerParts);
$pdf->Cell(0, 3, $footerText, 0, 0, 'C');

// Ajouter le filigrane texte "COPIE ORIGINALE"
$pdf->StartTransform();
$pdf->SetFont('helvetica', 'B', 40);
$pdf->SetTextColor(200, 200, 200);
$pdf->Rotate(45, $pdf->getPageWidth()/2, $pdf->getPageHeight()/2);
$textWidth = $pdf->GetStringWidth("COPIE ORIGINALE");
$pdf->SetAlpha(0.2);
$pdf->Text($pdf->getPageWidth()/2 - $textWidth/2, $pdf->getPageHeight()/2, "COPIE ORIGINALE");
$pdf->StopTransform();
$pdf->SetAlpha(1);

// Générer le PDF
$filename = 'Releve_' . $matricule . '.pdf';
while (ob_get_level() > 0) { @ob_end_clean(); }
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
@ini_set('zlib.output_compression', 'Off');
$pdf->Output($filename, 'I');
