<?php
// Nettoyer tout buffer de sortie existant
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// OPTIMISATION 0: Augmenter le timeout pour les calculs lourds
set_time_limit(300); // 5 minutes

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
if (!isset($_SESSION['id']) && !isset($_SESSION['student_id'])) {
    header('Location: ../connexion');
    exit();
}

// Récupération des paramètres
$matricule = $_GET['matricule'] ?? '';
$annee_id = isset($_GET['annee_id']) && !empty($_GET['annee_id']) ? $_GET['annee_id'] : null;
$promotion_id = isset($_GET['promotion_id']) && !empty($_GET['promotion_id']) ? $_GET['promotion_id'] : null;

if (empty($matricule)) {
    die("Matricule requis");
}

// Fonction de calcul des crédits (identique à la vue)
// Prend en compte les dettes du système ET des grilles importées
function calculerSyntheseCredits($resultatsSysteme, $resultatsImportes, $dettes)
{
    $creditsValides = 0;
    $creditsDettes = 0;
    $creditsTotal = 0;

    // Détails par source
    $systemeValides = 0;
    $systemeTotal = 0;
    $systemeDettes = 0;
    $importValides = 0;
    $importTotal = 0;
    $importDettes = 0;

    // Crédits du système actuel
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
                // UE non validée = dette du système
                $systemeDettes += $total;
            }
        }
    }

    // Crédits des imports (grilles anciennes)
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
                // UE non validée dans les imports = dette importée
                $importDettes += $total;
            }
        }
    }

    // Crédits en dette = UEs non validées du système + UEs non validées des imports
    $creditsDettesTable = 0;
    foreach ($dettes as $dette) {
        $creditsDettesTable += intval($dette['credits_ecue'] ?? 0);
    }

    // Total des dettes = dettes calculées (système + imports)
    $creditsDettesCalculees = $systemeDettes + $importDettes;
    $creditsDettes = max($creditsDettesCalculees, $creditsDettesTable);

    // Alternative: simplement crédits total - crédits validés si c'est plus fiable
    $creditsDettesSimple = $creditsTotal - $creditsValides;
    if ($creditsDettes == 0 && $creditsDettesSimple > 0) {
        $creditsDettes = $creditsDettesSimple;
    }

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
            'nb_imports' => count($resultatsImportes),
            'nb_dettes' => count($dettes)
        ]
    ];
}

try {
    $universite = new Universite();
    $dette = new Dette();
    $grilleAncienne = new GrilleAncienne();
    $etudiantModel = new Etudiant();
    $deliberation = new Deliberation();

    // Récupération des données
    $etudiant = $etudiantModel->getEtudiantByMatricule($matricule);
    if (!$etudiant) {
        die("Étudiant non trouvé");
    }

    $resultatsSysteme = [];
    $dettes = [];

    // Récupérer TOUTES les années de l'étudiant
    $toutes_sessions = $universite->getAllSessions();
    $anneeEtudiant = $etudiant['annee_acad_idannee_acad'] ?? null;

    // Récupérer toutes les années pour cet étudiant
    $annees_etudiant = [];
    try {
        $conn = Connexion::getInstance()->getPDO();
        $query = "SELECT DISTINCT annee_acad_idannee_acad FROM etudiant WHERE matricule = :matricule ORDER BY annee_acad_idannee_acad DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([':matricule' => $matricule]);
        $annees_etudiant = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Erreur récupération années étudiant: " . $e->getMessage());
    }

    $resultatsSysteme = [];

    // OPTIMISATION 1: Charger TOUTES les années academiques UNE SEULE FOIS
    $allAnnees = $universite->getAllAcademicYears();
    $anneeParId = [];
    foreach ($allAnnees as $a) {
        $anneeParId[$a['idannee_acad']] = $a['designation'];
    }

    // OPTIMISATION 2: Charger toutes les notes des sessions en une seule boucle
    $notesParAnneeSession = [];
    $reverseSessionIds = array_reverse(array_column($toutes_sessions, 'idsession'));

    foreach ($annees_etudiant as $annee_acad_id) {
        $notesParAnneeSession[$annee_acad_id] = [];

        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            $notes = $deliberation->getNotesEtudiant($matricule, $sessionId, $annee_acad_id);
            if (!empty($notes)) {
                $notesParAnneeSession[$annee_acad_id][$sessionId] = $notes;
            }
        }
    }

    // OPTIMISATION 3: Traiter les années avec les notes en cache
    foreach ($annees_etudiant as $annee_acad_id) {
        $anneeDesignation = $anneeParId[$annee_acad_id] ?? 'N/A';

        // Traiter les sessions en ordre inverse (best first)
        foreach ($reverseSessionIds as $sessionId) {
            if (empty($notesParAnneeSession[$annee_acad_id][$sessionId])) {
                continue;
            }

            $notesSession = $notesParAnneeSession[$annee_acad_id][$sessionId];

            foreach ($notesSession as $semData) {
                $semestreKey = 'Semestre ' . $semData['info']['numeroSemestre'] . ' (' . $anneeDesignation . ')';

                if (!isset($resultatsSysteme[$semestreKey])) {
                    $resultatsSysteme[$semestreKey] = [
                        'info' => $semData['info'],
                        'annee_id' => $annee_acad_id,
                        'annee_designation' => $anneeDesignation,
                        'ues' => []
                    ];
                }

                // OPTIMISATION 4: Index des UE existantes par code pour recherche rapide
                $uesIndex = [];
                foreach ($resultatsSysteme[$semestreKey]['ues'] as $idx => $ue) {
                    $uesIndex[$ue['code']] = $idx;
                }

                foreach ($semData['ues'] as $ue) {
                    $codeUE = $ue['info']['codeUE'];
                    $estValidee = isset($ue['info']['est_validee']) ? $ue['info']['est_validee'] == 1 : false;
                    $moyenneUE = isset($ue['info']['moyenne']) ? $ue['info']['moyenne'] : null;
                    $totalCredits = $ue['info']['nombre_credits'] ?? 0;
                    $creditsValides = 0;

                    // Si moyenne est vide/null, calculer à partir des ECUE
                    if ($moyenneUE === null || $moyenneUE === '' || $moyenneUE === 0) {
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

                    // Vérifier si l'UE existe déjà
                    if (isset($uesIndex[$codeUE])) {
                        $idx = $uesIndex[$codeUE];
                        $ueExistante = &$resultatsSysteme[$semestreKey]['ues'][$idx];

                        // Mettre à jour seulement si meilleur résultat
                        if ($estValidee || ($moyenneUE !== null && $moyenneUE !== '' && $ueExistante['moyenne'] !== null && $moyenneUE > $ueExistante['moyenne'])) {
                            $ueExistante['code'] = $codeUE;
                            $ueExistante['designation'] = $ue['info']['designationUE'];
                            $ueExistante['credits_total'] = $totalCredits;
                            $ueExistante['credits_valides'] = $creditsValides;
                            $ueExistante['moyenne'] = $moyenneUE ?? 0;
                            $ueExistante['est_valide'] = $estValidee;
                        }
                    } else {
                        // Ajouter la nouvelle UE
                        $resultatsSysteme[$semestreKey]['ues'][] = [
                            'code' => $codeUE,
                            'designation' => $ue['info']['designationUE'],
                            'credits_total' => $totalCredits,
                            'credits_valides' => $creditsValides,
                            'moyenne' => $moyenneUE ?? 0,
                            'est_valide' => $estValidee
                        ];
                        $uesIndex[$codeUE] = count($resultatsSysteme[$semestreKey]['ues']) - 1;
                    }
                }
            }
        }
    }

    $dettes = $dette->getDettesEtudiant($matricule);

    // Récupération et fusion des résultats importés
    $resultatsImportesOriginaux = $grilleAncienne->getResultatsEtudiantImportes($matricule);

    // OPTIMISATION 5: Fusionner les UE importées sans array_reverse (O(n) au lieu de O(2n))
    $uesParCode = [];

    // Parcourir les imports du dernier au premier (inverse naturel pour priorité)
    for ($i = count($resultatsImportesOriginaux) - 1; $i >= 0; $i--) {
        $import = $resultatsImportesOriginaux[$i];

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

            // Garder le meilleur résultat : UE non validée existante doit être remplacée par validée
            if (!isset($uesParCode[$codeUE])) {
                // Première occurrence - toujours ajouter
                $uesParCode[$codeUE] = [
                    'code_ue' => $ue['code_ue'],
                    'designation_ue' => $ue['designation_ue'],
                    'credits' => $ue['credits'],
                    'credits_total' => $totalCredits,
                    'credits_valides' => $creditsValides,
                    'moyenne' => $moyenne ?? 0,
                    'est_valide' => $estValidee,
                    'mention' => $ue['mention'] ?? '',
                    'type_resultat' => $ue['type_resultat'] ?? 'Import',
                    'import_source' => [
                        'annee_academique' => $import['annee_academique'],
                        'session' => $import['session'],
                        'date_import' => $import['date_import'],
                        'fichier_origine' => $import['fichier_origine']
                    ]
                ];
            } elseif (
                // Remplacer seulement si nouveau résultat est meilleur
                $estValidee && !$uesParCode[$codeUE]['est_valide']
            ) {
                // Cas spécial: UE validée remplace UE non validée
                $uesParCode[$codeUE] = [
                    'code_ue' => $ue['code_ue'],
                    'designation_ue' => $ue['designation_ue'],
                    'credits' => $ue['credits'],
                    'credits_total' => $totalCredits,
                    'credits_valides' => $creditsValides,
                    'moyenne' => $moyenne ?? 0,
                    'est_valide' => $estValidee,
                    'mention' => $ue['mention'] ?? '',
                    'type_resultat' => $ue['type_resultat'] ?? 'Import',
                    'import_source' => [
                        'annee_academique' => $import['annee_academique'],
                        'session' => $import['session'],
                        'date_import' => $import['date_import'],
                        'fichier_origine' => $import['fichier_origine']
                    ]
                ];
            } elseif (
                // Comparaison de moyennes seulement si les deux sont non-validées
                !$estValidee &&
                !$uesParCode[$codeUE]['est_valide'] &&
                $moyenne !== null &&
                $moyenne !== '' &&
                $uesParCode[$codeUE]['moyenne'] !== null &&
                $moyenne > $uesParCode[$codeUE]['moyenne']
            ) {
                $uesParCode[$codeUE]['moyenne'] = $moyenne;
                $uesParCode[$codeUE]['import_source'] = [
                    'annee_academique' => $import['annee_academique'],
                    'session' => $import['session'],
                    'date_import' => $import['date_import'],
                    'fichier_origine' => $import['fichier_origine']
                ];
            }
        }
    }

    // Créer un import consolidé avec les UE fusionnées
    $resultatsImportes = [];
    if (!empty($uesParCode)) {
        $resultatsImportes[] = [
            'import_id' => 0,
            'annee_academique' => 'Consolidé',
            'session' => 'Tous les imports',
            'semestre' => '',
            'promotion' => '',
            'date_import' => date('Y-m-d H:i:s'),
            'fichier_origine' => 'Fusion de plusieurs grilles anciennes',
            'ues' => array_values($uesParCode)
        ];
    }
    $syntheseCredits = calculerSyntheseCredits($resultatsSysteme, $resultatsImportes, $dettes);
    $configUniversite = $universite->getConfigurationUniversite();

    // Créer une instance de TCPDF (modèle bulletin individuel)
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Définir les informations du document
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Parcours Étudiant - ' . $etudiant['noms']);
    $pdf->SetSubject('Parcours académique complet');
    $pdf->SetKeywords('Étudiant, Parcours, Crédits, Académique, Officiel');

    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Définir les marges
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);

    // Couleurs pour le design
    $primaryColor = array(44, 62, 80); // Bleu foncé
    $secondaryColor = array(52, 73, 94); // Bleu-gris
    $accentColor = array(0, 123, 194); // Bleu moyen

    $pdf->AddPage();

    // Ajouter le logo en filigrane au centre
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
        // Logo de l'université (visible, à gauche)
        $logoSize = 12;
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 10, 10, $logoSize, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }

        // Informations université
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetY(10);
        $pdf->SetX(10 + $logoSize + 5);
        $pdf->Cell(0, 3, strtoupper($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPÉRIEUR ET UNIVERSITAIRE'), 0, 1, 'C');

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
        if (!empty($configUniversite['site_web'])) {
            $contactInfo .= 'Web: ' . $configUniversite['site_web'];
        }

        if (!empty($contactInfo)) {
            $pdf->Cell(0, 3, $contactInfo, 0, 1, 'C');
        }

        // Ligne de séparation
        $pdf->Ln(7);
        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());
    }

    // Titre du document
    $pdf->Ln(4);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'PARCOURS ACADÉMIQUE COMPLET', 0, 1, 'C', 1);

    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $etudiant['promotion'] ?? 'N/A', 0, 1, 'C');

    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 3, 'Année Académique ' . ($etudiant['annee_academique'] ?? 'N/A'), 0, 1, 'C');

    // Informations étudiant
    $pdf->Ln(2);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->SetTextColor(60, 60, 60);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 4, 'Matricule:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(80, 4, $etudiant['matricule'], 1, 0, 'L', 0);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 4, 'Email:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, $etudiant['adressemail'] ?? 'Non renseigné', 1, 1, 'L', 0);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 4, 'Nom et Prénom:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(80, 4, $etudiant['noms'], 1, 0, 'L', 0);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 4, 'Téléphone:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, $etudiant['telephone'] ?? 'Non renseigné', 1, 1, 'L', 0);

    // Synthèse des crédits
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->Cell(0, 5, 'SYNTHÈSE DES CRÉDITS', 0, 1, 'L');

    // Tableau synthèse
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 8);

    $colLabel = 50;
    $colValue = 30;
    $colProgress = 70;

    // En-têtes
    $pdf->Cell($colLabel, 5, 'Indicateur', 1, 0, 'L', 1);
    $pdf->Cell($colValue, 5, 'Valeur', 1, 0, 'C', 1);
    $pdf->Cell($colProgress, 5, 'Progression', 1, 0, 'C', 1);
    $pdf->Cell(0, 5, 'Détails', 1, 1, 'L', 1);

    // Données
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(255, 255, 255);

    // Crédits validés
    $pdf->Cell($colLabel, 5, 'Crédits validés', 1, 0, 'L', 0);
    $pdf->Cell($colValue, 5, $syntheseCredits['credits_valides'], 1, 0, 'C', 0);
    $pdf->Cell($colProgress, 5, $syntheseCredits['pourcentage'] . '% du cursus validé', 1, 0, 'L', 0);
    $pdf->Cell(0, 5, 'Système: ' . $syntheseCredits['details']['systeme_valides'] . ' + Imports: ' . $syntheseCredits['details']['import_valides'], 1, 1, 'L', 0);

    // Crédits en dette
    $pdf->Cell($colLabel, 5, 'Crédits en dette', 1, 0, 'L', 0);
    $pdf->SetTextColor($syntheseCredits['credits_dettes'] > 0 ? 200 : 60, 60, 60);
    $pdf->Cell($colValue, 5, $syntheseCredits['credits_dettes'], 1, 0, 'C', 0);
    $pdf->SetTextColor(60, 60, 60);
    $detteProgress = $syntheseCredits['credits_total'] > 0 ?
        round(($syntheseCredits['credits_dettes'] / $syntheseCredits['credits_total']) * 100, 1) : 0;
    $pdf->Cell($colProgress, 5, $detteProgress . '% du total', 1, 0, 'L', 0);
    $pdf->Cell(0, 5, count($dettes) . ' ECUE(s) concerné(s)', 1, 1, 'L', 0);

    // Total crédits
    $pdf->Cell($colLabel, 5, 'Total crédits', 1, 0, 'L', 0);
    $pdf->Cell($colValue, 5, $syntheseCredits['credits_total'], 1, 0, 'C', 0);
    $pdf->Cell($colProgress, 5, '100% du cursus', 1, 0, 'L', 0);
    $pdf->Cell(0, 5, 'Système: ' . $syntheseCredits['details']['systeme_total'] . ' + Imports: ' . $syntheseCredits['details']['import_total'], 1, 1, 'L', 0);

    // Définir les largeurs des colonnes (une seule fois)
    $colUE = 80;
    $colCode = 25;
    $colMoy = 20;
    $colCredits = 25;
    $colValid = 20;

    $colECUE = 60;
    $colUEDette = 50;
    $colNote = 20;
    $colCred = 20;
    $colStatut = 20;

    // Résultats du système actuel
    if (!empty($resultatsSysteme)) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 5, 'RÉSULTATS DU SYSTÈME ACTUEL', 0, 1, 'L');

        foreach ($resultatsSysteme as $semestreNom => $semestre) {
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
            $pdf->Cell(0, 4, $semestreNom, 0, 1, 'L');

            // En-têtes tableau UE
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 7);

            $pdf->Cell($colUE, 4, 'UE', 1, 0, 'L', 1);
            $pdf->Cell($colCode, 4, 'Code', 1, 0, 'C', 1);
            $pdf->Cell($colMoy, 4, 'Moyenne', 1, 0, 'C', 1);
            $pdf->Cell($colCredits, 4, 'Crédits', 1, 0, 'C', 1);
            $pdf->Cell($colValid, 4, 'Validation', 1, 1, 'C', 1);

            // Données UE
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);

            foreach ($semestre['ues'] as $ue) {
                $pdf->SetTextColor(($ue['est_valide'] ?? false) ? 50 : 200, ($ue['est_valide'] ?? false) ? 150 : 50, 50);

                $pdf->Cell($colUE, 4, substr($ue['designation'] ?? 'N/A', 0, 40), 1, 0, 'L', 0);
                $pdf->Cell($colCode, 4, $ue['code'] ?? '', 1, 0, 'C', 0);
                $pdf->Cell($colMoy, 4, number_format($ue['moyenne'] ?? 0, 2), 1, 0, 'C', 0);
                $pdf->Cell($colCredits, 4, ($ue['credits_valides'] ?? 0) . '/' . ($ue['credits_total'] ?? 0), 1, 0, 'C', 0);
                $pdf->Cell($colValid, 4, ($ue['est_valide'] ?? false) ? 'VALIDÉ' : 'NON VALIDÉ', 1, 1, 'C', 0);
            }
        }
    }

    // Résultats importés
    if (!empty($resultatsImportes)) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 5, 'RÉSULTATS IMPORTÉS (GRILLES ANCIENNES)', 0, 1, 'L');

        foreach ($resultatsImportes as $import) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
            }

            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
            $pdf->Cell(0, 4, $import['session'] . ' - ' . $import['annee_academique'], 0, 1, 'L');

            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 3, 'Importé le: ' . date('d/m/Y', strtotime($import['date_import'])) . ' - ' . basename($import['fichier_origine']), 0, 1, 'L');

            // Tableau UE importées
            $pdf->SetFillColor(248, 249, 250);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 7);

            $pdf->Cell($colUE, 4, 'UE', 1, 0, 'L', 1);
            $pdf->Cell($colCode, 4, 'Code', 1, 0, 'C', 1);
            $pdf->Cell($colMoy, 4, 'Moyenne', 1, 0, 'C', 1);
            $pdf->Cell($colCredits, 4, 'Crédits', 1, 0, 'C', 1);
            $pdf->Cell($colValid, 4, 'Validation', 1, 1, 'C', 1);

            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);

            foreach ($import['ues'] as $ue) {
                $pdf->SetTextColor(($ue['est_valide'] ?? false) ? 50 : 200, ($ue['est_valide'] ?? false) ? 150 : 50, 50);

                // Afficher la désignation et l'année académique si disponible
                $designationWithYear = substr($ue['designation_ue'] ?? 'N/A', 0, 40);
                if (!empty($ue['import_source']['annee_academique'])) {
                    $designationWithYear .= ' (' . substr($ue['import_source']['annee_academique'], 0, 15) . ')';
                }

                $pdf->Cell($colUE, 4, $designationWithYear, 1, 0, 'L', 0);
                $pdf->Cell($colCode, 4, $ue['code_ue'] ?? '', 1, 0, 'C', 0);
                $pdf->Cell($colMoy, 4, number_format($ue['moyenne'] ?? 0, 2), 1, 0, 'C', 0);
                $pdf->Cell($colCredits, 4, ($ue['credits_valides'] ?? 0) . '/' . ($ue['credits_total'] ?? $ue['credits'] ?? 0), 1, 0, 'C', 0);
                $pdf->Cell($colValid, 4, ($ue['est_valide'] ?? false) ? 'VALIDÉ' : 'NON VALIDÉ', 1, 1, 'C', 0);
            }
        }
    }

    // Dettes en cours
    if (!empty($dettes)) {
        if ($pdf->GetY() > 230) {
            $pdf->AddPage();
        }

        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(200, 50, 50);
        $pdf->Cell(0, 5, 'DETTES EN COURS', 0, 1, 'L');

        // Tableau dettes
        $pdf->SetFillColor(252, 248, 227);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 7);

        $pdf->Cell($colECUE, 4, 'ECUE', 1, 0, 'L', 1);
        $pdf->Cell($colUEDette, 4, 'UE', 1, 0, 'L', 1);
        $pdf->Cell($colNote, 4, 'Note', 1, 0, 'C', 1);
        $pdf->Cell($colCred, 4, 'Crédits', 1, 0, 'C', 1);
        $pdf->Cell($colStatut, 4, 'Statut', 1, 1, 'C', 1);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);

        foreach ($dettes as $dette_item) {
            $pdf->SetTextColor(200, 50, 50);

            $pdf->Cell($colECUE, 4, substr($dette_item['designationECUE'] ?? 'N/A', 0, 30), 1, 0, 'L', 0);
            $pdf->Cell($colUEDette, 4, substr($dette_item['designationUE'] ?? 'N/A', 0, 25), 1, 0, 'L', 0);
            $pdf->Cell($colNote, 4, number_format($dette_item['note_obtenue'] ?? 0, 2), 1, 0, 'C', 0);
            $pdf->Cell($colCred, 4, $dette_item['credits_ecue'] ?? 0, 1, 0, 'C', 0);
            $pdf->Cell($colStatut, 4, substr($dette_item['statut'] ?? 'N/A', 0, 10), 1, 1, 'C', 0);
        }
    }

    // Signature et QR Code
    $pdf->Ln(5);

    // QR Code pour vérification
    $qrData = "PARCOURS-" . $etudiant['matricule'] . "-" . date('dmY');
    $qrX = 10;
    $qrY = $pdf->GetY();

    try {
        $pdf->write2DBarcode($qrData, 'QRCODE,L', $qrX, $qrY, 18, 18, array(), 'N');
    } catch (Exception $e) {
        $pdf->SetXY($qrX, $qrY);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(18, 18, 'Code QR\nindisponible', 1, 0, 'C');
    }

    // Texte sous le QR code
    $pdf->SetXY($qrX, $qrY + 19);
    $pdf->SetFont('helvetica', '', 5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(18, 2, 'Vérification', 0, 0, 'C');

    // Signature - Secrétaire Général Académique
    $titreSecretaire = $configUniversite['titre_secretaire_general'] ?? 'Le Secrétaire Général Académique';
    $nomSecretaire = $configUniversite['nom_secretaire_general'] ?? '';
    
    $signX = $pdf->getPageWidth() - 65;
    $signY = $qrY;

    $pdf->SetXY($signX, $signY);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(55, 4, 'Fait à ' . ($configUniversite['ville'] ?? '...') . ', le ' . date('d/m/Y'), 0, 1, 'R');

    $pdf->SetXY($signX, $signY + 5);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(55, 4, $titreSecretaire, 0, 1, 'R');
    
    if (!empty($nomSecretaire)) {
        $pdf->SetXY($signX, $signY + 16);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(55, 4, $nomSecretaire, 0, 1, 'R');
    } else {
        $pdf->SetXY($signX, $signY + 16);
        $pdf->Cell(55, 4, '_______________________', 0, 1, 'R');
    }

    // Footer avec date d'impression
    $pdf->SetXY(10, $pdf->getPageHeight() - 10);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(120, 120, 120);
    $footerText = 'Document généré par ' . ($_SESSION['nom'] ?? 'Système') . ', le ' . date('d/m/Y à H:i');
    $pdf->Cell(0, 3, $footerText, 0, 0, 'C');

    // Nettoyer tous les buffers de sortie avant de générer le PDF
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Générer le PDF
    $filename = 'Parcours_' . $etudiant['matricule'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    exit();
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log("Erreur génération PDF parcours: " . $e->getMessage());
    die("Erreur lors de la génération du PDF: " . $e->getMessage());
}
