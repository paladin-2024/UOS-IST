<?php
// Désactiver l'affichage des erreurs pour éviter qu'elles interfèrent avec le PDF
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Dette.php';
require_once dirname(__DIR__) . '/models/GrilleAncienne.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';

session_start();
if (!isset($_SESSION['id']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    header('Location: ../connexion');
    exit();
}

// Récupération des paramètres
$annee_id = $_GET['annee_id'] ?? '';
$promotion_id = $_GET['promotion_id'] ?? '';

if (empty($annee_id) || empty($promotion_id)) {
    die("Année et promotion requises");
}

// Fonction de calcul des crédits
function calculerSyntheseCredits($resultatsSysteme, $resultatsImportes, $dettes) {
    $creditsValides = 0;
    $creditsDettes = 0;
    $creditsTotal = 0;
    
    $systemeValides = 0;
    $systemeTotal = 0;
    $systemeDettes = 0;
    $importValides = 0;
    $importTotal = 0;
    $importDettes = 0;
    
    foreach ($resultatsSysteme as $semestre) {
        foreach ($semestre['ues'] as $ue) {
            $total = intval($ue['credits_total'] ?? 0);
            $valides = intval($ue['credits_valides'] ?? 0);
            
            $systemeTotal += $total;
            $creditsTotal += $total;
            
            if ($ue['est_valide'] ?? false) {
                $systemeValides += $valides;
                $creditsValides += $valides;
            } else {
                $systemeDettes += $total;
            }
        }
    }
    
    foreach ($resultatsImportes as $import) {
        foreach ($import['ues'] as $ue) {
            $total = intval($ue['credits_total'] ?? $ue['credits'] ?? 0);
            
            $importTotal += $total;
            $creditsTotal += $total;
            
            if ($ue['est_valide']) {
                $valides = intval($ue['credits_valides'] ?? $total);
                $importValides += $valides;
                $creditsValides += $valides;
            } else {
                $importDettes += $total;
            }
        }
    }
    
    $creditsDettesCalculees = $systemeDettes + $importDettes;
    $creditsDettes = max($creditsDettesCalculees, 0);
    
    $pourcentage = $creditsTotal > 0 ? round(($creditsValides / $creditsTotal) * 100, 1) : 0;
    
    return [
        'credits_valides' => $creditsValides,
        'credits_dettes' => $creditsDettes,
        'credits_total' => $creditsTotal,
        'pourcentage' => $pourcentage,
        'details' => [
            'systeme_valides' => $systemeValides,
            'systeme_total' => $systemeTotal,
            'systeme_dettes' => $systemeDettes,
            'import_valides' => $importValides,
            'import_total' => $importTotal,
            'import_dettes' => $importDettes,
            'nb_imports' => count($resultatsImportes)
        ]
    ];
}

try {
    $universite = new Universite();
    $grilleAncienne = new GrilleAncienne();
    $etudiantModel = new Etudiant();
    $deliberation = new Deliberation();
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations
    $annees = $universite->getAllAcademicYears();
    $promotions = $universite->getPromotionsByYear($annee_id);
    
    $anneeDesignation = '';
    $promotionDesignation = '';
    foreach ($annees as $a) {
        if ($a['idannee_acad'] == $annee_id) {
            $anneeDesignation = $a['designation'];
            break;
        }
    }
    
    foreach ($promotions as $p) {
        if ($p['idpromotion'] == $promotion_id) {
            $promotionDesignation = $p['designationPromotion'];
            break;
        }
    }
    
    // Récupérer tous les étudiants actifs
    $sql = "SELECT DISTINCT e.matricule, e.noms
            FROM etudiant e
            WHERE e.promotion_idpromotion = :promotion
                AND e.annee_acad_idannee_acad = :annee
                AND e.est_actif = 1
            ORDER BY e.noms ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':promotion' => $promotion_id,
        ':annee' => $annee_id
    ]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Collecter les dettes de tous les étudiants
    $dettesParEtudiant = [];
    $totalCreditsGlobal = 0;
    $totalDettesGlobal = 0;
    $totalEtudiantsAvecDettes = 0;
    
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        
        // Récupérer les résultats du système
        $resultatsSysteme = [];
        $toutes_sessions = $universite->getAllSessions();
        $notesParSession = [];
        
        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            $notesParSession[$sessionId] = $deliberation->getNotesEtudiant($matricule, $sessionId, $annee_id);
        }
        
        // Construire les résultats système - MEILLEURE NOTE
        $meilleuresUEs = [];
        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            if (!empty($notesParSession[$sessionId])) {
                foreach ($notesParSession[$sessionId] as $semData) {
                    $semestreKey = 'Semestre ' . $semData['info']['numeroSemestre'];
                    
                    if (!empty($semData['ues'])) {
                        foreach ($semData['ues'] as $ue) {
                            $codeUE = $ue['info']['codeUE'] ?? '';
                            $estValidee = $ue['info']['est_validee'] ?? false;
                            $moyenne = $ue['info']['moyenne'] ?? null;
                            $totalCredits = $ue['info']['nombre_credits'] ?? 0;
                            $creditsValides = 0;
                            
                            // Si moyenne est vide/null, calculer à partir des ECUE
                            if ($moyenne === null || $moyenne === '' || $moyenne === 0) {
                                $creditsValides = 0;
                                $totalCredits = 0;
                                
                                if (!empty($ue['ecues'])) {
                                    foreach ($ue['ecues'] as $ecue) {
                                        $ecueCredit = (floatval($ecue['CMI']) + floatval($ecue['TP']) + floatval($ecue['TD'])) / 22;
                                        $ecueCredit = round($ecueCredit, 1);
                                        $totalCredits += $ecueCredit;
                                        
                                        if ($ecue['note'] !== null && floatval($ecue['note']) >= 10) {
                                            $creditsValides += $ecueCredit;
                                        }
                                    }
                                }
                                
                                $estValidee = ($totalCredits > 0 && $creditsValides == $totalCredits);
                                // Si toujours pas de moyenne après calcul ECUE, c'est une UE sans notes
                                if ($totalCredits == 0) {
                                    $estValidee = false;
                                    $totalCredits = $ue['info']['nombre_credits'] ?? 0;
                                }
                            } else {
                                // Si moyenne est présente, utiliser les crédits de l'UE
                                $creditsValides = $estValidee ? ($ue['info']['nombre_credits'] ?? 0) : 0;
                            }
                            
                            if (!isset($meilleuresUEs[$codeUE]) || 
                                $estValidee || 
                                ($moyenne !== null && $moyenne !== '' && $moyenne > 0 && $moyenne > ($meilleuresUEs[$codeUE]['moyenne'] ?? 0))) {
                                
                                $meilleuresUEs[$codeUE] = [
                                    'code' => $codeUE,
                                    'designation' => $ue['info']['designationUE'] ?? '',
                                    'moyenne' => $moyenne ?? 0,
                                    'credits_total' => $totalCredits,
                                    'credits_valides' => $creditsValides,
                                    'est_valide' => $estValidee,
                                    'semestre' => $semestreKey,
                                    'info' => $semData['info']
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Organiser par semestre
        foreach ($meilleuresUEs as $ue) {
            $semestreKey = $ue['semestre'];
            
            if (!isset($resultatsSysteme[$semestreKey])) {
                $resultatsSysteme[$semestreKey] = [
                    'info' => $ue['info'],
                    'annee_id' => $annee_id,
                    'ues' => []
                ];
            }
            
            $resultatsSysteme[$semestreKey]['ues'][] = [
                'code' => $ue['code'],
                'designation' => $ue['designation'],
                'moyenne' => $ue['moyenne'],
                'credits_total' => $ue['credits_total'],
                'credits_valides' => $ue['credits_valides'],
                'est_valide' => $ue['est_valide']
            ];
        }
        
        // Récupérer les résultats importés
        $resultatsImportesOriginaux = $grilleAncienne->getResultatsEtudiantImportes($matricule);
        
        $uesParCode = [];
        foreach (array_reverse($resultatsImportesOriginaux) as $import) {
            foreach ($import['ues'] as $ue) {
                $codeUE = $ue['code_ue'];
                $estValidee = $ue['est_valide'] ?? false;
                $moyenne = $ue['moyenne'] ?? null;
                $totalCredits = $ue['credits_total'] ?? $ue['credits'] ?? 0;
                $creditsValides = 0;
                
                // Si moyenne est vide/null, la traiter comme non validée
                if ($moyenne === null || $moyenne === '' || $moyenne === 0) {
                    $estValidee = false;
                    $creditsValides = 0;
                } else {
                    $creditsValides = $estValidee ? $totalCredits : 0;
                }
                
                if (!isset($uesParCode[$codeUE]) || 
                    $estValidee || 
                    ($moyenne !== null && $moyenne !== '' && $uesParCode[$codeUE]['moyenne'] !== null && $moyenne > $uesParCode[$codeUE]['moyenne'])) {
                    
                    $uesParCode[$codeUE] = [
                        'code_ue' => $ue['code_ue'],
                        'designation_ue' => $ue['designation_ue'],
                        'credits' => $ue['credits'],
                        'credits_total' => $totalCredits,
                        'credits_valides' => $creditsValides,
                        'moyenne' => $moyenne ?? 0,
                        'est_valide' => $estValidee,
                        'mention' => $ue['mention'] ?? ''
                    ];
                }
            }
        }
        
        $resultatsImportesFusionnes = [];
        if (!empty($uesParCode)) {
            $resultatsImportesFusionnes[] = [
                'import_id' => 0,
                'annee_academique' => 'Consolidé',
                'session' => 'Tous les imports',
                'ues' => array_values($uesParCode),
                'is_consolidated' => true
            ];
        }
        
        // Calculer la synthèse
        $synthese = calculerSyntheseCredits($resultatsSysteme, $resultatsImportesFusionnes, []);
        
        // Ajouter aux dettes de l'étudiant
        if ($synthese['credits_dettes'] > 0) {
            $dettesParEtudiant[] = [
                'matricule' => $matricule,
                'noms' => $etudiant['noms'],
                'credits_dettes' => $synthese['credits_dettes'],
                'credits_total' => $synthese['credits_total'],
                'credits_valides' => $synthese['credits_valides'],
                'pourcentage' => $synthese['pourcentage'],
                'systeme_dettes' => $synthese['details']['systeme_dettes'],
                'import_dettes' => $synthese['details']['import_dettes'],
                'resultatsSysteme' => $resultatsSysteme,
                'resultatsImportes' => $resultatsImportesFusionnes
            ];
            
            $totalEtudiantsAvecDettes++;
        }
        
        $totalCreditsGlobal += $synthese['credits_total'];
        $totalDettesGlobal += $synthese['credits_dettes'];
    }
    
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Créer le PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Rapport Dettes - ' . $promotionDesignation);
    $pdf->SetSubject('Dettes par promotion');
    $pdf->SetKeywords('Dettes, Promotion, Académique, Officiel');
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);
    
    // Couleurs
    $primaryColor = array(44, 62, 80);
    $secondaryColor = array(52, 73, 94);
    $accentColor = array(0, 123, 194);
    $dangerColor = array(220, 53, 69);
    
    $pdf->AddPage();
    
    // Filigrane logo
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
    
    // En-tête
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
        $pdf->Cell(0, 3, strtoupper($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPÉRIEUR'), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 4, strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        
        $contactInfo = '';
        if (!empty($configUniversite['telephone'])) {
            $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
        }
        if (!empty($configUniversite['email'])) {
            $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
        }
        if (!empty($contactInfo)) {
            $pdf->Cell(0, 3, $contactInfo, 0, 1, 'C');
        }
        
        $pdf->Ln(4);
        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());
    }
    
    // Titre
    $pdf->Ln(3);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'RAPPORT DES DETTES PAR PROMOTION', 0, 1, 'C', 1);
    
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $promotionDesignation . ' - ' . $anneeDesignation, 0, 1, 'C');
    
    // Statistiques globales
    $pdf->Ln(2);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 7);
    
    $pdf->Cell(45, 3, 'Total étudiants actifs:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(0, 3, count($etudiants), 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(45, 3, 'Étudiants avec dettes:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(0, 3, $totalEtudiantsAvecDettes . ' (' . (count($etudiants) > 0 ? round(($totalEtudiantsAvecDettes / count($etudiants)) * 100, 1) : 0) . '%)', 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(45, 3, 'Total crédits en dette:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(0, 3, $totalDettesGlobal . ' / ' . $totalCreditsGlobal, 1, 1, 'L', 0);
    
    // Tableau des dettes par étudiant
    if (!empty($dettesParEtudiant)) {
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
        $pdf->Cell(0, 4, 'ÉTUDIANTS AVEC DETTES', 0, 1, 'L');
        
        // En-têtes tableau
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 6);
        
        $pdf->Cell(25, 3, 'Matricule', 1, 0, 'L', 1);
        $pdf->Cell(45, 3, 'Nom', 1, 0, 'L', 1);
        $pdf->Cell(18, 3, 'Dettes', 1, 0, 'C', 1);
        $pdf->Cell(18, 3, 'Total', 1, 0, 'C', 1);
        $pdf->Cell(18, 3, 'Validés', 1, 0, 'C', 1);
        $pdf->Cell(15, 3, '%', 1, 0, 'C', 1);
        $pdf->Cell(15, 3, 'Système', 1, 0, 'C', 1);
        $pdf->Cell(0, 3, 'Imports', 1, 1, 'C', 1);
        
        // Données
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetFillColor(255, 255, 255);
        
        foreach ($dettesParEtudiant as $etudiant) {
            if ($pdf->GetY() > 240) {
                $pdf->AddPage();
                // Répéter les en-têtes
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(60, 60, 60);
                $pdf->SetFont('helvetica', 'B', 6);
                
                $pdf->Cell(25, 3, 'Matricule', 1, 0, 'L', 1);
                $pdf->Cell(45, 3, 'Nom', 1, 0, 'L', 1);
                $pdf->Cell(18, 3, 'Dettes', 1, 0, 'C', 1);
                $pdf->Cell(18, 3, 'Total', 1, 0, 'C', 1);
                $pdf->Cell(18, 3, 'Validés', 1, 0, 'C', 1);
                $pdf->Cell(15, 3, '%', 1, 0, 'C', 1);
                $pdf->Cell(15, 3, 'Système', 1, 0, 'C', 1);
                $pdf->Cell(0, 3, 'Imports', 1, 1, 'C', 1);
                
                $pdf->SetFont('helvetica', '', 6);
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
            $pdf->Cell(25, 3, $etudiant['matricule'], 1, 0, 'L', 0);
            $pdf->Cell(45, 3, substr($etudiant['noms'], 0, 30), 1, 0, 'L', 0);
            $pdf->Cell(18, 3, $etudiant['credits_dettes'], 1, 0, 'C', 0);
            $pdf->Cell(18, 3, $etudiant['credits_total'], 1, 0, 'C', 0);
            $pdf->Cell(18, 3, $etudiant['credits_valides'], 1, 0, 'C', 0);
            $pdf->Cell(15, 3, $etudiant['pourcentage'] . '%', 1, 0, 'C', 0);
            $pdf->Cell(15, 3, $etudiant['systeme_dettes'], 1, 0, 'C', 0);
            $pdf->Cell(0, 3, $etudiant['import_dettes'], 1, 1, 'C', 0);
            
            // Détail UE en dette
            if ($etudiant['systeme_dettes'] > 0) {
                $pdf->SetFont('helvetica', '', 5);
                $pdf->SetTextColor(120, 120, 120);
                
                foreach ($etudiant['resultatsSysteme'] as $semestreNom => $semestre) {
                    foreach ($semestre['ues'] as $ue) {
                        if (!($ue['est_valide'] ?? false)) {
                            $pdf->Cell(25, 2.5, '', 0, 0, 'L');
                            $pdf->Cell(45, 2.5, substr($ue['designation'], 0, 30), 0, 0, 'L');
                            $pdf->Cell(18, 2.5, '-', 0, 0, 'C');
                            $pdf->Cell(18, 2.5, $ue['credits_total'], 0, 0, 'C');
                            $pdf->Cell(18, 2.5, '0', 0, 0, 'C');
                            $pdf->Cell(15, 2.5, '-', 0, 0, 'C');
                            $pdf->Cell(15, 2.5, number_format($ue['moyenne'], 2), 0, 0, 'C');
                            $pdf->Cell(0, 2.5, '', 0, 1, 'C');
                        }
                    }
                }
            }
            
            if ($etudiant['import_dettes'] > 0) {
                $pdf->SetFont('helvetica', '', 5);
                $pdf->SetTextColor(120, 120, 120);
                
                foreach ($etudiant['resultatsImportes'] as $import) {
                    foreach ($import['ues'] as $ue) {
                        if (!($ue['est_valide'] ?? false)) {
                            $pdf->Cell(25, 2.5, '', 0, 0, 'L');
                            $pdf->Cell(45, 2.5, substr($ue['designation_ue'], 0, 30), 0, 0, 'L');
                            $pdf->Cell(18, 2.5, '-', 0, 0, 'C');
                            $pdf->Cell(18, 2.5, $ue['credits_total'] ?? $ue['credits'], 0, 0, 'C');
                            $pdf->Cell(18, 2.5, '0', 0, 0, 'C');
                            $pdf->Cell(15, 2.5, '-', 0, 0, 'C');
                            $pdf->Cell(15, 2.5, '-', 0, 0, 'C');
                            $pdf->Cell(0, 2.5, number_format($ue['moyenne'], 2), 0, 1, 'C');
                        }
                    }
                }
            }
        }
    } else {
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Aucun étudiant avec dettes pour cette promotion.', 0, 1, 'C');
    }
    
    // Footer
    $pdf->SetXY(10, $pdf->getPageHeight() - 10);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(120, 120, 120);
    $footerText = 'Document généré par ' . ($_SESSION['nom'] ?? 'Système') . ', le ' . date('d/m/Y à H:i');
    $pdf->Cell(0, 3, $footerText, 0, 0, 'C');
    
    if (ob_get_length()) {
        ob_clean();
    }
    
    $filename = 'Dettes_' . str_replace(' ', '_', $promotionDesignation) . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    error_log("Erreur génération PDF dettes: " . $e->getMessage());
    die("Erreur lors de la génération du PDF: " . $e->getMessage());
}
?>
