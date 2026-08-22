<?php
ob_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/models/Universite.php';
require_once dirname(__DIR__).'/models/GrilleAncienne.php';
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
    file_put_contents('../debug_releve_ancien.log', $output, FILE_APPEND);
}

// Récupérer les paramètres
$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : null;
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

// Vérifier que tous les paramètres nécessaires sont fournis
if (!$matricule || !$importId) {
    die('Paramètres incomplets');
}

// Créer une instance de TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Récupérer d'abord les informations pour définir le titre
$universite = new Universite();
$grilleAncienne = new GrilleAncienne();
$numeroReleve = new NumeroReleve();
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les informations de l'import avec la section pour le titre
$importInfo = $grilleAncienne->getImportWithSection($importId);
$etudiant = $grilleAncienne->getEtudiantByMatricule($importId, $matricule);

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Relevé de Notes - ' . ($etudiant['noms'] ?? 'Étudiant'));
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

try {
    // Vérifier si les informations sont déjà récupérées
    if (!$importInfo) {
        die('Import non trouvé');
    }

    if (!$etudiant) {
        die('Étudiant non trouvé');
    }

    // Récupérer l'ID interne de l'étudiant
    $etudiantId = $etudiant['id'];
    
    // Récupérer les UEs avec leurs ECUEs pour affichage détaillé
    $uesAvecEcues = $grilleAncienne->getUEsAvecECUEs($importId, $matricule);
    
    // Organiser les UEs par semestre réel (logique identique à export_grille_ancienne.php)
    $uesBySemestre = [];
    foreach ($uesAvecEcues as $ue) {
        $semestre = $ue['semestre'] ?? 'S1';
        $uesBySemestre[$semestre][] = $ue;
    }
    
    // Trier les semestres
    ksort($uesBySemestre);
    $afficherDeuxSemestres = count($uesBySemestre) > 1;
    
    // Créer la structure des semestres pour correspondre au format original
    $notesEtudiant = [];
    $semestresStats = [];
    
    $totalCreditsGlobal = 0;
    $totalCreditsValidesGlobal = 0;
    $totalMoyennesPonderees = 0;
    $totalCoefficientsGlobal = 0;
    $notesManquantesGlobal = false;
    
    foreach ($uesBySemestre as $semestreKey => $uesInSemestre) {
        $totalCreditsSemestre = 0;
        $totalCreditsValidesSemestre = 0;
        $totalMoyennePondereeSemestre = 0;
        $totalCoefficientsSemestre = 0;
        $notesManquantesSemestre = false;
        
        $uesData = [];
        
        foreach ($uesInSemestre as $ue) {
            $credits = isset($ue['credits_ue']) ? intval($ue['credits_ue']) : 0;
            $totalCreditsSemestre += $credits;
            
            // Calculer la moyenne de l'UE à partir des ECUEs (logique identique à export_grille_ancienne.php)
            $totalPointsUE = 0;
            $totalCoeffUE = 0;
            $hasAllNotes = true;
            
            $ecuesData = [];
            
            // Vérifier d'abord si TOUTES les ECUEs ont des notes
            foreach ($ue['ecues'] as $ecue) {
                if (!isset($ecue['note_finale']) || $ecue['note_finale'] === null) {
                    $hasAllNotes = false;
                    // Ne pas break ici - on continue pour afficher toutes les ECUEs
                }
            }
            
            // Toujours afficher toutes les ECUEs même sans notes (logique identique export_grille_ancienne.php)
            foreach ($ue['ecues'] as $ecue) {
                $ecuesData[] = [
                    'designationECUE' => $ecue['nom_ecue'],
                    'coefficient' => isset($ecue['credits']) ? floatval($ecue['credits']) : 1,
                    'note' => isset($ecue['note_finale']) ? $ecue['note_finale'] : null // null sera affiché comme "-"
                ];
            }
            
            // Collecter les notes et crédits pour cette UE (logique EXACTE export_grille_ancienne.php)
            $notesUE = [];
            $creditsUEEcues = [];
            
            foreach ($ue['ecues'] as $ecue) {
                if (isset($ecue['note_finale']) && $ecue['note_finale'] !== null) {
                    $notesUE[] = floatval($ecue['note_finale']);
                    $creditsUEEcues[] = isset($ecue['credits']) ? floatval($ecue['credits']) : 1;
                }
            }
            
            // Calculer la moyenne UE (EXACTE logique export_grille_ancienne.php)
            if ($hasAllNotes && count($notesUE) > 0) {
                // Utiliser la fonction calculerMoyennePonderee comme dans export_grille_ancienne.php
                $sommeNotesPonderees = 0;
                $sommeCoefficients = 0;
                for ($i = 0; $i < count($notesUE); $i++) {
                    $sommeNotesPonderees += $notesUE[$i] * $creditsUEEcues[$i];
                    $sommeCoefficients += $creditsUEEcues[$i];
                }
                $moyenneUE = $sommeCoefficients > 0 ? $sommeNotesPonderees / $sommeCoefficients : 0;
                $estValidee = $moyenneUE >= 10 ? 1 : 0;
                
                // Capitalisation au niveau UE entière
                if ($moyenneUE >= 10) {
                    $totalCreditsValidesSemestre += $credits;
                }
                
                $totalMoyennePondereeSemestre += ($moyenneUE * $credits);
                $totalCoefficientsSemestre += $credits;
            } else {
                // Pas toutes les notes - UE non validée - 0 crédit validé
                $moyenneUE = null;
                $estValidee = 0;
            }
            
            // Ajouter l'UE à la structure
            $uesData[] = [
                'info' => [
                    'codeUE' => $ue['code_ue'] ?? 'UE',
                    'designationUE' => $ue['nom_ue'],
                    'nombre_credits' => $ue['credits_ue'],
                    'moyenne' => $moyenneUE,
                    'est_validee' => $estValidee
                ],
                'ecues' => $ecuesData
            ];
        }
        
        // Calculer la moyenne du semestre (EXACTE logique export_grille_ancienne.php)
        $moyenneSemestre = null;
        $uesAvecMoyenne = 0;
        foreach ($uesData as $ueData) {
            if ($ueData['info']['moyenne'] !== null) {
                $uesAvecMoyenne++;
            }
        }
        
        if ($uesAvecMoyenne === count($uesInSemestre)) {
            // Toutes les UE ont une moyenne - calculer moyenne semestre
            $moyenneSemestre = $totalCoefficientsSemestre > 0 ? $totalMoyennePondereeSemestre / $totalCoefficientsSemestre : 0;
        }
        // Sinon pas de moyenne semestre (affiché comme "-")
        
        // Ajouter le semestre à la structure
        $notesEtudiant[] = [
            'info' => [
                'numeroSemestre' => $semestreKey
            ],
            'ues' => $uesData
        ];
        
        // Stats du semestre (EXACTE logique export_grille_ancienne.php)
        $semestresStats[] = [
            'credits_total' => $totalCreditsSemestre,
            'credits_valides' => $totalCreditsValidesSemestre,
            'moyenne' => $moyenneSemestre,
            'notes_manquantes' => ($moyenneSemestre === null)
        ];
        
        // Cumul global (EXACTE logique export_grille_ancienne.php)
        $totalCreditsGlobal += $totalCreditsSemestre;
        $totalCreditsValidesGlobal += $totalCreditsValidesSemestre;
        
        if ($moyenneSemestre !== null) {
            $totalMoyennesPonderees += $moyenneSemestre * $totalCreditsSemestre;
            $totalCoefficientsGlobal += $totalCreditsSemestre;
        }
    }
    
    // Calculer la moyenne générale (EXACTE logique export_grille_ancienne.php)
    $pourcentageCredits = null;
    $pourcentageM = null;
    $moyenneGenerale = null;
    
    // Toujours calculer les pourcentages de crédits
    $pourcentageCredits = ($totalCreditsGlobal > 0) ? (($totalCreditsValidesGlobal / $totalCreditsGlobal) * 100) : 0;
    
    // Compter les semestres avec moyenne
    $semestresAvecMoyenne = 0;
    foreach ($semestresStats as $stats) {
        if ($stats['moyenne'] !== null) {
            $semestresAvecMoyenne++;
        }
    }
    
    // Calculer la moyenne annuelle seulement si TOUS les semestres ont des moyennes
    if ($semestresAvecMoyenne === count($uesBySemestre) && $totalCoefficientsGlobal > 0) {
        $moyenneGenerale = $totalMoyennesPonderees / $totalCoefficientsGlobal;
        $pourcentageM = ($moyenneGenerale > 0) ? (($moyenneGenerale/20)*100) : 0;
        $notesManquantesGlobal = false;
    } else {
        // Pas tous les semestres avec moyennes
        $notesManquantesGlobal = true;
    }
    
    // Déterminer l'état global et la mention
    $estValideGlobal = (!$notesManquantesGlobal && $totalCreditsValidesGlobal == $totalCreditsGlobal && $moyenneGenerale >= 10);
    
    // Déterminer la mention seulement si pas de notes manquantes
    $mention = '';
    if (!$notesManquantesGlobal && $moyenneGenerale !== null) {
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
    
    // Variables pour correspondre au format original
    $isDeuxiemeSession = stripos($importInfo['session'], 'rattrapage') !== false;

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
        if (!empty($importInfo['designationSection'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 4, strtoupper($importInfo['designationSection']), 0, 1, 'C');
        }
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        
        // Utiliser les contacts de la section si disponibles, sinon ceux de l'université
        $contactInfo = '';
        if (!empty($importInfo['section_telephone'])) {
            $contactInfo .= 'Tél: ' . $importInfo['section_telephone'] . ' ';
        } elseif (!empty($configUniversite['telephone'])) {
            $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
        }
        
        if (!empty($importInfo['section_email'])) {
            $contactInfo .= 'Email: ' . $importInfo['section_email'] . ' ';
        } elseif (!empty($configUniversite['email'])) {
            $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
        }
        
        if (!empty($importInfo['section_site_web'])) {
            $contactInfo .= 'Web: ' . $importInfo['section_site_web'];
        } elseif (!empty($configUniversite['site_web'])) {
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

    // Récupérer ou créer le numéro permanent du relevé (ancienne grille)
    $numReleve = $numeroReleve->getOrCreateNumeroReleveAncienne($etudiant['idetudiant'] ?? $etudiantId, $importId);

    // Titre du document (compacté)
    $pdf->Ln(4);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'RELEVÉ DE NOTES N°' . $numReleve, 0, 1, 'C', 1);

    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $importInfo['promotion'], 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 3, ucfirst($importInfo['session']) . ' - Année Académique ' . $importInfo['annee_academique'], 0, 1, 'C');

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

    // Générer le QR code pour vérification
    $qrData = "RELEVE~" . 
              $etudiant['matricule'] . "~" .
              $etudiant['noms'] . "~" .
              $importInfo['promotion'] . "~" .
              (($moyenneGenerale !== null) ? number_format($moyenneGenerale, 2) : 'N/A') . "~" .
              $totalCreditsValidesGlobal . "~" .
              $totalCreditsGlobal . "~" .
              (($pourcentageM !== null) ? number_format($pourcentageM, 2) : 'N/A');

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
    $creditsText = $totalCreditsValidesGlobal . '/' . $totalCreditsGlobal;
    $pourcentageText = ($pourcentageM !== null) ? number_format($pourcentageM, 2) . '%' : 'N/A';
    
    // Déterminer la décision et la mention/état
    $decisionText = '';
    $mentionEtatText = '-';
    $decisionColor = array(50, 50, 50);
    
    if ($afficherDeuxSemestres) {
        // Pour les résultats annuels
        if ($notesManquantesGlobal) {
            $decisionText = 'INCOMPLET';
            $decisionColor = array(200, 50, 50);
        } 
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
        else {
            // En première session
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
        if ($notesManquantesGlobal) {
            $decisionText = 'INCOMPLET';
            $decisionColor = array(200, 50, 50);
            $mentionEtatText = 'INCOMPLET';
        } else if ($totalCreditsValidesGlobal == $totalCreditsGlobal) {
            $decisionText = 'VALIDÉ TOTALEMENT';
            $decisionColor = array(50, 150, 50);
            $mentionEtatText = 'COMPLET';
        } else if ($totalCreditsValidesGlobal > 0) {
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
    
    // Responsable Académique (centre-gauche)
    $acadX = 35;
    $acadY = $qrY;
    
    $pdf->SetXY($acadX, $acadY);
    $pdf->SetFont('helvetica', '', 5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(40, 2, 'Responsable Académique', 0, 1, 'C');
    
    $pdf->SetXY($acadX, $acadY + 8);
    if (!empty($configUniversite['nom_responsable_academique'])) {
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->Cell(40, 3, $configUniversite['nom_responsable_academique'], 0, 1, 'C');
        $pdf->SetXY($acadX, $acadY + 12);
        $pdf->SetFont('helvetica', '', 5);
        $pdf->Cell(40, 2, $configUniversite['titre_responsable_academique'] ?? '', 0, 1, 'C');
    } else {
        $pdf->Cell(40, 3, '___________________', 0, 1, 'C');
    }

    // Signature (bas droite, plus compacte)
    $signX = $pdf->getPageWidth() - 60;
    $signY = $qrY;
    
    $pdf->SetXY($signX, $signY);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(80, 80, 80);
    
    // Toujours afficher "Doyen / Chef de Section"
    $pdf->Cell(50, 3, 'Doyen / Chef de Section', 0, 1, 'R');
    $pdf->SetXY($signX, $signY + 12);
    
    if (!empty($importInfo['chef_section_nom'])) {
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->Cell(50, 3, $importInfo['chef_section_nom'], 0, 1, 'R');
    } else {
        $pdf->Cell(50, 3, '___________________', 0, 1, 'R');
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

} catch (Exception $e) {
    debug_to_file($e->getMessage(), 'Erreur générale');
    die('Erreur: ' . $e->getMessage());
}
?>
