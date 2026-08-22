<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/Universite.php';
require_once '../models/Deliberation.php';
require_once '../models/GrilleAncienne.php';
require_once '../models/Etudiant.php';

// Vérification des droits
if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Accès non autorisé']));
}

$annee_id = $_GET['annee_id'] ?? '';
$promotion_id = $_GET['promotion_id'] ?? '';
$offset = intval($_GET['offset'] ?? 0);
$limit = intval($_GET['limit'] ?? 20);

if (empty($annee_id) || empty($promotion_id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Paramètres manquants']));
}

try {
    $db = Connexion::getInstance()->getPDO();
    $universite = new Universite();
    $deliberation = new Deliberation();
    $grilleAncienne = new GrilleAncienne();
    
    // Récupérer les étudiants avec pagination
    $sql = "SELECT DISTINCT e.matricule, e.noms
            FROM etudiant e
            WHERE e.promotion_idpromotion = :promotion
                AND e.annee_acad_idannee_acad = :annee
                AND e.est_actif = 1
            ORDER BY e.noms ASC
            LIMIT :offset, :limit";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':promotion', $promotion_id, PDO::PARAM_INT);
    $stmt->bindValue(':annee', $annee_id, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter le total
    $sqlCount = "SELECT COUNT(DISTINCT e.matricule) as total
                 FROM etudiant e
                 WHERE e.promotion_idpromotion = :promotion
                     AND e.annee_acad_idannee_acad = :annee
                     AND e.est_actif = 1";
    
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->bindValue(':promotion', $promotion_id, PDO::PARAM_INT);
    $stmtCount->bindValue(':annee', $annee_id, PDO::PARAM_INT);
    $stmtCount->execute();
    $totalCount = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    $dettesEtudiants = [];
    $toutes_sessions = $universite->getAllSessions();
    
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        
        // Récupérer les notes par session
        $notesParSession = [];
        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            $notesParSession[$sessionId] = $deliberation->getNotesEtudiant($matricule, $sessionId, $annee_id);
        }
        
        // Construire les résultats système - MEILLEURE NOTE
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
        $synthese = calculerSyntheseCredits($resultatsSysteme, $resultatsImportesFusionnes, []);
        
        $dettesEtudiants[] = [
            'matricule' => $matricule,
            'noms' => $etudiant['noms'],
            'credits_dettes' => $synthese['credits_dettes'],
            'credits_total' => $synthese['credits_total'],
            'credits_valides' => $synthese['credits_valides'],
            'pourcentage_validation' => $synthese['pourcentage'],
            'details' => $synthese['details']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dettesEtudiants,
        'total' => $totalCount,
        'offset' => $offset,
        'limit' => $limit,
        'hasMore' => ($offset + $limit) < $totalCount
    ]);
    
} catch (Exception $e) {
    error_log("Erreur chargement pagination dettes: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}

/**
 * Calculer la synthèse des crédits pour un étudiant
 */
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
