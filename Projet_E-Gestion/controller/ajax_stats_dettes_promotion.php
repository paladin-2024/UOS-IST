<?php
/**
 * Calcul des statistiques de dettes pour une promotion
 * Appelé en AJAX avec timeout étendu
 */
header('Content-Type: application/json');
set_time_limit(300); // 5 minutes pour les gros volumes

require_once './config/Connexion.php';
require_once './models/Universite.php';
require_once './models/Deliberation.php';
require_once './models/GrilleAncienne.php';
require_once './models/Etudiant.php';

try {
    session_start();
    if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
        throw new Exception('Accès non autorisé');
    }

    $annee_id = $_GET['annee_id'] ?? null;
    $promotion_id = $_GET['promotion_id'] ?? null;

    if (!$annee_id || !$promotion_id) {
        throw new Exception('Paramètres manquants');
    }

    $db = Connexion::getInstance()->getPDO();
    $universite = new Universite();
    $deliberation = new Deliberation();
    $grilleAncienne = new GrilleAncienne();
    $toutes_sessions = $universite->getAllSessions();

    // Récupérer tous les étudiants
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
    $tousLesEtudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalDettesGlobal = 0;
    $totalCreditsGlobal = 0;
    $totalEtudiantsAvecDettes = 0;
    $stats_systeme_dettes = 0;
    $stats_import_dettes = 0;
    $etudiants_traites = 0;

    foreach ($tousLesEtudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        $etudiants_traites++;

        // Récupérer les notes
        $notesParSession = [];
        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            $notesParSession[$sessionId] = $deliberation->getNotesEtudiant($matricule, $sessionId, $annee_id);
        }

        // Construire les résultats système
        $resultatsSysteme = [];
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
                            $moyenne = $ue['info']['moyenne'] ?? 0;

                            if (!isset($meilleuresUEs[$codeUE]) ||
                                $estValidee ||
                                ($moyenne > 0 && $moyenne > ($meilleuresUEs[$codeUE]['moyenne'] ?? 0))) {

                                $meilleuresUEs[$codeUE] = [
                                    'code' => $codeUE,
                                    'designation' => $ue['info']['designationUE'] ?? '',
                                    'moyenne' => $moyenne,
                                    'credits_total' => $ue['info']['nombre_credits'] ?? 0,
                                    'credits_valides' => $estValidee ? ($ue['info']['nombre_credits'] ?? 0) : 0,
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

        // Fusionner les UE importées
        $uesParCode = [];
        foreach (array_reverse($resultatsImportesOriginaux) as $import) {
            foreach ($import['ues'] as $ue) {
                $codeUE = $ue['code_ue'];

                if (!isset($uesParCode[$codeUE]) ||
                    $ue['est_valide'] ||
                    ($ue['moyenne'] !== null && $uesParCode[$codeUE]['moyenne'] !== null && $ue['moyenne'] > $uesParCode[$codeUE]['moyenne'])) {

                    $uesParCode[$codeUE] = [
                        'code_ue' => $ue['code_ue'],
                        'designation_ue' => $ue['designation_ue'],
                        'credits' => $ue['credits'],
                        'credits_total' => $ue['credits_total'] ?? $ue['credits'] ?? 0,
                        'credits_valides' => $ue['est_valide'] ? ($ue['credits_total'] ?? $ue['credits'] ?? 0) : 0,
                        'moyenne' => $ue['moyenne'] ?? 0,
                        'est_valide' => $ue['est_valide'],
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
        $synthese = calculerSyntheseCreditsPHP($resultatsSysteme, $resultatsImportesFusionnes, []);

        // Accumuler les statistiques
        if ($synthese['credits_dettes'] > 0) {
            $totalEtudiantsAvecDettes++;
        }
        $totalDettesGlobal += $synthese['credits_dettes'];
        $totalCreditsGlobal += $synthese['credits_total'];
        $stats_systeme_dettes += $synthese['details']['systeme_dettes'];
        $stats_import_dettes += $synthese['details']['import_dettes'];
    }

    // Réponse JSON
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_etudiants' => count($tousLesEtudiants),
            'etudiants_avec_dettes' => $totalEtudiantsAvecDettes,
            'total_credits_dettes' => $totalDettesGlobal,
            'total_credits' => $totalCreditsGlobal,
            'systeme_dettes' => $stats_systeme_dettes,
            'import_dettes' => $stats_import_dettes,
            'taux_etudiants_dettes' => count($tousLesEtudiants) > 0
                ? round(($totalEtudiantsAvecDettes / count($tousLesEtudiants)) * 100, 1)
                : 0,
            'etudiants_traites' => $etudiants_traites
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Fonction locale de calcul synthèse
 */
function calculerSyntheseCreditsPHP($resultatsSysteme, $resultatsImportes, $dettes) {
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
