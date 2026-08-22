<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Récupération des paramètres
$idECUE = intval($_POST['idECUE']);
$anneeId = intval($_POST['annee_acad_id']);
$sessionId = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

try {
    // Récupérer les détails de l'ECUE
    $sqlEcue = "SELECT e.*, u.designationUE, s.numeroSemestre, p.designationPromotion
                FROM ecue e
                JOIN ue u ON e.UE_idUE = u.idUE
                JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                WHERE e.idECUE = ?";
                
    $stmtEcue = $pdo->prepare($sqlEcue);
    $stmtEcue->execute([$idECUE]);
    $ecue = $stmtEcue->fetch(PDO::FETCH_ASSOC);
    
    if (!$ecue) {
        echo json_encode(['success' => false, 'message' => 'ECUE non trouvé']);
        exit;
    }
    
    // Récupérer les sessions
    $sqlSessionsPremiere = "SELECT idsession FROM session WHERE designSession LIKE '%Première%' LIMIT 1";
    $stmtSessionsPremiere = $pdo->query($sqlSessionsPremiere);
    $sessionPremiere = $stmtSessionsPremiere->fetchColumn();
    
    $sqlSessionsDeuxieme = "SELECT idsession FROM session WHERE designSession LIKE '%Deuxième%' LIMIT 1";
    $stmtSessionsDeuxieme = $pdo->query($sqlSessionsDeuxieme);
    $sessionDeuxieme = $stmtSessionsDeuxieme->fetchColumn();
    
    // Récupérer la configuration des pondérations
    $sqlConfig = "SELECT ponderation_cc, ponderation_ex 
                 FROM configuration_moyenne 
                 WHERE idECUE = ? AND session_idsession = ? AND annee_acad_id = ?
                 ORDER BY dateCreation DESC LIMIT 1";
                 
    $stmtConfig = $pdo->prepare($sqlConfig);
    $stmtConfig->execute([$idECUE, $sessionPremiere, $anneeId]);
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    
    // Pondérations par défaut si aucune configuration trouvée
    $ponderationCC = $config ? floatval($config['ponderation_cc']) : 0.4;
    $ponderationEX = $config ? floatval($config['ponderation_ex']) : 0.6;
    
    // Récupérer tous les étudiants pour cet ECUE
    $sqlEtudiants = "SELECT e.idetudiant, e.matricule, e.noms 
                    FROM etudiant e
                    WHERE e.promotion_idpromotion IN (
                        SELECT promotion_idpromotion 
                        FROM semestre s 
                        JOIN ue u ON s.idsemestre = u.semestre_idsemestre
                        WHERE u.idUE IN (SELECT UE_idUE FROM ecue WHERE idECUE = ?)
                    )
                    ORDER BY e.noms";
                    
    $stmtEtudiants = $pdo->prepare($sqlEtudiants);
    $stmtEtudiants->execute([$idECUE]);
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer toutes les évaluations définies pour cet ECUE
    $sqlAllEvals = "SELECT e.idevaluation, e.titre, e.note_max, e.ponderation, 
                   t.idType, t.designationT, t.categorie, s.idsession, s.description
                   FROM evaluations e
                   JOIN typeevaluation t ON e.idType = t.idType
                   JOIN session s ON e.session_idsession = s.idsession
                   WHERE e.idECUE = ? AND e.annee_acad_id = ?
                   ORDER BY s.idsession, e.date_evaluation";
    
    $stmtAllEvals = $pdo->prepare($sqlAllEvals);
    $stmtAllEvals->execute([$idECUE, $anneeId]);
    $allEvaluations = $stmtAllEvals->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les évaluations par catégorie et session
    $totalEvalsCCPremiere = 0;
    $totalEvalsEXPremiere = 0;
    $totalEvalsEXDeuxieme = 0;
    
    foreach ($allEvaluations as $eval) {
        if ($eval['idsession'] == $sessionPremiere) {
            if ($eval['categorie'] === 'CC') {
                $totalEvalsCCPremiere++;
            } else if ($eval['categorie'] === 'EX') {
                $totalEvalsEXPremiere++;
            }
        } else if ($eval['idsession'] == $sessionDeuxieme) {
            if ($eval['categorie'] === 'EX') {
                $totalEvalsEXDeuxieme++;
            }
        }
    }
    
    // Prévisualisation des notes pour chaque étudiant
    $studentsData = [];
    
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        
        // Récupérer toutes les notes de l'étudiant pour cet ECUE
        $sqlNotes = "SELECT p.coteObtenu, p.typeEvaluation, p.session_idsession
                    FROM points p
                    WHERE p.matricule = ? AND p.ECUE_idECUE = ? AND p.annee_acad_id = ?";
        
        $stmtNotes = $pdo->prepare($sqlNotes);
        $stmtNotes->execute([$matricule, $idECUE, $anneeId]);
        $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les évaluations par session et catégorie
        $evaluationsPremiereCC = [];
        $evaluationsPremiereEX = [];
        $evaluationsDeuxiemeEX = [];
        $notesDetail = [];
        
        // Vérifier la complétude des évaluations
        $ccPremiereComplete = true;
        $exPremiereComplete = true;
        $exDeuxiemeComplete = true;
        
        // Préparer un tableau pour marquer les évaluations avec note
        $evaluationsWithNote = [];
        
        // Préparer les notes détaillées à partir des données existantes
        foreach ($notes as $note) {
            foreach ($allEvaluations as $eval) {
                if ($eval['idType'] == $note['typeEvaluation'] && $eval['idsession'] == $note['session_idsession']) {
                    $noteSur20 = ($note['coteObtenu'] / $eval['note_max']) * 20;
                    
                    // MODIFICATION: Considérer 0 comme une note manquante
                    if (abs($noteSur20) < 0.001) { // proche de zéro
                        // Marquer la catégorie correspondante comme incomplète
                        if ($eval['idsession'] == $sessionPremiere) {
                            if ($eval['categorie'] === 'CC') {
                                $ccPremiereComplete = false;
                            } else if ($eval['categorie'] === 'EX') {
                                $exPremiereComplete = false;
                            }
                        } else if ($eval['idsession'] == $sessionDeuxieme) {
                            if ($eval['categorie'] === 'EX') {
                                $exDeuxiemeComplete = false;
                            }
                        }
                        
                        // Ajouter aux détails des notes avec note null
                        $notesDetail[] = [
                            'titre' => $eval['titre'],
                            'type' => $eval['designationT'],
                            'categorie' => $eval['categorie'],
                            'session' => $eval['description'],
                            'note' => null, // Convertir 0 en null
                            'evaluation_id' => $eval['idevaluation']
                        ];
                        
                        // Ne pas ajouter cette évaluation aux tableaux de notes
                        continue;
                    }
                    
                    // Ajouter aux détails des notes
                    $notesDetail[] = [
                        'titre' => $eval['titre'],
                        'type' => $eval['designationT'],
                        'categorie' => $eval['categorie'],
                        'session' => $eval['description'],
                        'note' => $noteSur20,
                        'evaluation_id' => $eval['idevaluation']
                    ];
                    
                    // Marquer cette évaluation comme ayant une note
                    $evaluationsWithNote[$eval['idevaluation']] = true;
                    
                    // Classer l'évaluation selon sa session et sa catégorie
                    if ($eval['idsession'] == $sessionPremiere) {
                        if ($eval['categorie'] === 'CC') {
                            $evaluationsPremiereCC[] = [
                                'note' => $noteSur20,
                                'ponderation' => floatval($eval['ponderation'])
                            ];
                        } else if ($eval['categorie'] === 'EX') {
                            $evaluationsPremiereEX[] = [
                                'note' => $noteSur20
                            ];
                        }
                    } else if ($eval['idsession'] == $sessionDeuxieme) {
                        if ($eval['categorie'] === 'EX') {
                            $evaluationsDeuxiemeEX[] = [
                                'note' => $noteSur20
                            ];
                        }
                    }
                    
                    break;
                }
            }
        }
        
        // Ajouter les évaluations sans note au détail
        foreach ($allEvaluations as $eval) {
            if (!isset($evaluationsWithNote[$eval['idevaluation']])) {
                // Marquer la catégorie correspondante comme incomplète
                if ($eval['idsession'] == $sessionPremiere) {
                    if ($eval['categorie'] === 'CC') {
                        $ccPremiereComplete = false;
                    } else if ($eval['categorie'] === 'EX') {
                        $exPremiereComplete = false;
                    }
                } else if ($eval['idsession'] == $sessionDeuxieme) {
                    if ($eval['categorie'] === 'EX') {
                        $exDeuxiemeComplete = false;
                    }
                }
                
                // Ajouter aux détails des notes avec note null
                $notesDetail[] = [
                    'titre' => $eval['titre'],
                    'type' => $eval['designationT'],
                    'categorie' => $eval['categorie'],
                    'session' => $eval['description'],
                    'note' => null,
                    'evaluation_id' => $eval['idevaluation']
                ];
            }
        }
        
        // Vérifier la complétude en comparant le nombre d'évaluations avec des notes
        if (count($evaluationsPremiereCC) < $totalEvalsCCPremiere) {
            $ccPremiereComplete = false;
        }
        if (count($evaluationsPremiereEX) < $totalEvalsEXPremiere) {
            $exPremiereComplete = false;
        }
        if (count($evaluationsDeuxiemeEX) < $totalEvalsEXDeuxieme) {
            $exDeuxiemeComplete = false;
        }
        
        // Calculer la moyenne des CC (première session) seulement si toutes les notes sont présentes
        $moyenneCC = null;
        if ($ccPremiereComplete && !empty($evaluationsPremiereCC)) {
            $moyenneCC = 0;
            $totalPonderation = 0;
            
            foreach ($evaluationsPremiereCC as $cc) {
                $ponderation = $cc['ponderation'] ?: 1;
                $moyenneCC += $cc['note'] * $ponderation;
                $totalPonderation += $ponderation;
            }
            
            if ($totalPonderation > 0) {
                $moyenneCC = $moyenneCC / $totalPonderation;
            }
        }
        
        // Récupérer la note d'examen (première session) seulement si complète
        $noteExamenSur20 = null;
        if ($exPremiereComplete && !empty($evaluationsPremiereEX)) {
            $noteExamenSur20 = $evaluationsPremiereEX[0]['note']; // On prend la première note d'examen
        }
        
        // Récupérer la note d'examen (deuxième session) seulement si complète
        $noteExamenSur20Session2 = null;
        if ($exDeuxiemeComplete && !empty($evaluationsDeuxiemeEX)) {
            $noteExamenSur20Session2 = $evaluationsDeuxiemeEX[0]['note']; // On prend la première note d'examen de 2e session
        }
        
        // Calculer la moyenne finale (première session) seulement si CC et EX sont disponibles
        $moyenneFinale = null;
        if ($moyenneCC !== null && $noteExamenSur20 !== null) {
            $moyenneFinale = ($moyenneCC * $ponderationCC) + ($noteExamenSur20 * $ponderationEX);
        }
        
        // Calculer la moyenne finale (deuxième session) seulement si EX est disponible
        $moyenneFinaleSession2 = $noteExamenSur20Session2; // En deuxième session, la note d'examen vaut 100%
        
        // Construction de la structure de données pour cet étudiant
        $studentData = [
            'matricule' => $matricule,
            'noms' => $etudiant['noms'],
            'premiere_session' => [
                'cc' => $moyenneCC,
                'ex' => $noteExamenSur20,
                'mf' => $moyenneFinale,
                'evaluations' => [
                    'cc' => $evaluationsPremiereCC,
                    'ex' => $evaluationsPremiereEX
                ]
            ],
            'deuxieme_session' => [
                'ex' => $noteExamenSur20Session2,
                'mf' => $moyenneFinaleSession2,
                'evaluations' => [
                    'ex' => $evaluationsDeuxiemeEX
                ]
            ],
            'notes_detail' => $notesDetail,
            'meta' => [
                'cc_premiere_complete' => $ccPremiereComplete,
                'ex_premiere_complete' => $exPremiereComplete,
                'ex_deuxieme_complete' => $exDeuxiemeComplete,
                'total_evals_cc_premiere' => $totalEvalsCCPremiere,
                'total_evals_ex_premiere' => $totalEvalsEXPremiere,
                'total_evals_ex_deuxieme' => $totalEvalsEXDeuxieme,
                'has_cc_premiere' => count($evaluationsPremiereCC),
                'has_ex_premiere' => count($evaluationsPremiereEX),
                'has_ex_deuxieme' => count($evaluationsDeuxiemeEX)
            ]
        ];
        
        $studentsData[] = $studentData;
    }
    
    // Préparer des statistiques globales
    $statsCCPremiere = [];
    $statsEXPremiere = [];
    $statsMFPremiere = [];
    $statsEXDeuxieme = [];
    $statsMFDeuxieme = [];
    
    foreach ($studentsData as $student) {
        // Utiliser les valeurs calculées à partir des points, pas des cotes_grille
        if ($student['premiere_session']['cc'] !== null) {
            $statsCCPremiere[] = $student['premiere_session']['cc'];
        }
        if ($student['premiere_session']['ex'] !== null) {
            $statsEXPremiere[] = $student['premiere_session']['ex'];
        }
        if ($student['premiere_session']['mf'] !== null) {
            $statsMFPremiere[] = $student['premiere_session']['mf'];
        }
        if ($student['deuxieme_session']['ex'] !== null) {
            $statsEXDeuxieme[] = $student['deuxieme_session']['ex'];
        }
        if ($student['deuxieme_session']['mf'] !== null) {
            $statsMFDeuxieme[] = $student['deuxieme_session']['mf'];
        }
    }
    
    // Calculer les moyennes globales
    $stats = [
        'cc_premiere' => [
            'count' => count($statsCCPremiere),
            'avg' => count($statsCCPremiere) > 0 ? array_sum($statsCCPremiere) / count($statsCCPremiere) : null,
            'min' => count($statsCCPremiere) > 0 ? min($statsCCPremiere) : null,
            'max' => count($statsCCPremiere) > 0 ? max($statsCCPremiere) : null
        ],
        'ex_premiere' => [
            'count' => count($statsEXPremiere),
            'avg' => count($statsEXPremiere) > 0 ? array_sum($statsEXPremiere) / count($statsEXPremiere) : null,
            'min' => count($statsEXPremiere) > 0 ? min($statsEXPremiere) : null,
            'max' => count($statsEXPremiere) > 0 ? max($statsEXPremiere) : null
        ],
        'mf_premiere' => [
            'count' => count($statsMFPremiere),
            'avg' => count($statsMFPremiere) > 0 ? array_sum($statsMFPremiere) / count($statsMFPremiere) : null,
            'min' => count($statsMFPremiere) > 0 ? min($statsMFPremiere) : null,
            'max' => count($statsMFPremiere) > 0 ? max($statsMFPremiere) : null
        ],
        'ex_deuxieme' => [
            'count' => count($statsEXDeuxieme),
            'avg' => count($statsEXDeuxieme) > 0 ? array_sum($statsEXDeuxieme) / count($statsEXDeuxieme) : null,
            'min' => count($statsEXDeuxieme) > 0 ? min($statsEXDeuxieme) : null,
            'max' => count($statsEXDeuxieme) > 0 ? max($statsEXDeuxieme) : null
        ],
        'mf_deuxieme' => [
            'count' => count($statsMFDeuxieme),
            'avg' => count($statsMFDeuxieme) > 0 ? array_sum($statsMFDeuxieme) / count($statsMFDeuxieme) : null,
            'min' => count($statsMFDeuxieme) > 0 ? min($statsMFDeuxieme) : null,
            'max' => count($statsMFDeuxieme) > 0 ? max($statsMFDeuxieme) : null
        ],
        'missing_data' => [
            'cc_premiere' => $totalEvalsCCPremiere > 0 ? count($etudiants) - count($statsCCPremiere) : 0,
            'ex_premiere' => $totalEvalsEXPremiere > 0 ? count($etudiants) - count($statsEXPremiere) : 0,
            'mf_premiere' => count($etudiants) - count($statsMFPremiere),
            'ex_deuxieme' => $totalEvalsEXDeuxieme > 0 ? count($etudiants) - count($statsEXDeuxieme) : 0,
            'mf_deuxieme' => count($etudiants) - count($statsMFDeuxieme)
        ],
        'total_students' => count($etudiants)
    ];
    
    // Préparer la réponse JSON
    $response = [
        'success' => true,
        'ecue' => $ecue,
        'config' => [
            'ponderation_cc' => $ponderationCC,
            'ponderation_ex' => $ponderationEX
        ],
        'students' => $studentsData,
        'stats' => $stats
    ];
    
    // Si un session_id spécifique est fourni, filtrer les résultats
    if ($sessionId !== null && $sessionId > 0) {
        // Filtrer les données pour n'afficher que la session demandée
        // (On garde toutes les données pour simplifier)
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la prévisualisation: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
