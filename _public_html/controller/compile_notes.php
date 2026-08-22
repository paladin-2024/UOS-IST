<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si les paramètres requis sont fournis
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['idECUE']) || !isset($_POST['annee_acad_id'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$idECUE = intval($_POST['idECUE']);
$anneeId = intval($_POST['annee_acad_id']);
$sessionId = isset($_POST['session_id']) ? ($_POST['session_id'] === 'all' ? null : intval($_POST['session_id'])) : null;

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

try {
    // Commencer une transaction
    $pdo->beginTransaction();
    
    // Récupérer les sessions à traiter
    if ($sessionId) {
        $sqlSessions = "SELECT idsession, description FROM session WHERE idsession = ?";
        $stmtSessions = $pdo->prepare($sqlSessions);
        $stmtSessions->execute([$sessionId]);
    } else {
        $sqlSessions = "SELECT idsession, description FROM session";
        $stmtSessions = $pdo->query($sqlSessions);
    }
    $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les sessions principale (pour savoir quelle est la première/deuxième session)
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
    
    // Pour chaque session, compiler les notes pour chaque étudiant
    foreach ($sessions as $session) {
        $currentSessionId = $session['idsession'];
        $isDeuxiemeSession = ($currentSessionId == $sessionDeuxieme);
        
        // Pour chaque étudiant, calculer et stocker les notes
        foreach ($etudiants as $etudiant) {
            $matricule = $etudiant['matricule'];
            
            // Pour la deuxième session, vérifier si l'étudiant a réussi en première session
            if ($isDeuxiemeSession) {
                $sqlCheckPremiere = "SELECT MF FROM cotes_grille 
                                    WHERE matricule = ? AND ECUE_idECUE = ? AND annee_acad_id = ? 
                                    AND session_idsession = ?";
                                    
                $stmtCheckPremiere = $pdo->prepare($sqlCheckPremiere);
                $stmtCheckPremiere->execute([$matricule, $idECUE, $anneeId, $sessionPremiere]);
                $premiereSession = $stmtCheckPremiere->fetch(PDO::FETCH_ASSOC);
                
                // Si l'étudiant a déjà réussi en première session (MF >= 10), on passe à l'étudiant suivant
                if ($premiereSession && $premiereSession['MF'] >= 10) {
                    continue;
                }
            }
            
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
            
            // Préparer un tableau pour marquer les évaluations avec note
            $evaluationsWithNote = [];
            
            // Préparer les notes à partir des données existantes
            foreach ($notes as $note) {
                foreach ($allEvaluations as $eval) {
                    if ($eval['idType'] == $note['typeEvaluation'] && $eval['idsession'] == $note['session_idsession']) {
                        $noteSur20 = ($note['coteObtenu'] / $eval['note_max']) * 20;
                        
                        // IMPORTANT: Considérer 0 comme une note manquante, comme dans preview
                        if (abs($noteSur20) < 0.001) { // proche de zéro
                            continue;
                        }
                        
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
            
            // Calculer pour la session actuelle uniquement
            if ($currentSessionId == $sessionPremiere) {
                // Calculer la moyenne des CC (première session) 
                $moyenneCC = null;
                if (!empty($evaluationsPremiereCC)) {
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
                
                // Récupérer la note d'examen (première session)
                $noteExamenSur20 = null;
                if (!empty($evaluationsPremiereEX)) {
                    $noteExamenSur20 = $evaluationsPremiereEX[0]['note']; // On prend la première note d'examen
                }
                
                // Calculer la moyenne finale (première session)
                $moyenneFinale = null;
                if ($moyenneCC !== null && $noteExamenSur20 !== null) {
                    $moyenneFinale = ($moyenneCC * $ponderationCC) + ($noteExamenSur20 * $ponderationEX);
                } else if ($moyenneCC !== null) {
                    $moyenneFinale = null;
                } else if ($noteExamenSur20 !== null) {
                    $moyenneFinale = null;
                }
                
                // Sauvegarder dans la base si au moins une note existe
                if ($moyenneCC !== null || $noteExamenSur20 !== null) {
                    // Vérifier si une entrée existe déjà
                    $sqlCheck = "SELECT COUNT(*) FROM cotes_grille 
                                WHERE matricule = ? AND ECUE_idECUE = ? 
                                AND session_idsession = ? AND annee_acad_id = ?";
                    $stmtCheck = $pdo->prepare($sqlCheck);
                    $stmtCheck->execute([$matricule, $idECUE, $currentSessionId, $anneeId]);
                    $exists = $stmtCheck->fetchColumn() > 0;
                    
                    if ($exists) {
                        $sqlUpdate = "UPDATE cotes_grille 
                                    SET CC = ?, EX = ?, MF = ?, date_compilation = NOW(), idUser = ?
                                    WHERE matricule = ? AND ECUE_idECUE = ? 
                                    AND session_idsession = ? AND annee_acad_id = ?";
                        $stmtUpdate = $pdo->prepare($sqlUpdate);
                        $stmtUpdate->execute([
                            $moyenneCC, $noteExamenSur20, $moyenneFinale, 
                            isset($_SESSION['id']) ? $_SESSION['id'] : null,
                            $matricule, $idECUE, $currentSessionId, $anneeId
                        ]);
                    } else {
                        $sqlInsert = "INSERT INTO cotes_grille 
                                    (CC, EX, MF, ECUE_idECUE, session_idsession, matricule, annee_acad_id, date_compilation, idUser)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                        $stmtInsert = $pdo->prepare($sqlInsert);
                        $stmtInsert->execute([
                            $moyenneCC, $noteExamenSur20, $moyenneFinale, 
                            $idECUE, $currentSessionId, $matricule, $anneeId, 
                            isset($_SESSION['id']) ? $_SESSION['id'] : null
                        ]);
                    }
                }
            } 
            else if ($currentSessionId == $sessionDeuxieme) {
                // En deuxième session, la note d'examen vaut 100%
                $noteExamenSur20 = null;
                if (!empty($evaluationsDeuxiemeEX)) {
                    $noteExamenSur20 = $evaluationsDeuxiemeEX[0]['note']; // On prend la première note d'examen
                }
                
                                // En deuxième session, la note d'examen vaut 100%
                                $noteExamenSur20 = null;
                                if (!empty($evaluationsDeuxiemeEX)) {
                                    $noteExamenSur20 = $evaluationsDeuxiemeEX[0]['note']; // On prend la première note d'examen
                                }
                                
                                // La moyenne finale en deuxième session est simplement la note d'examen
                                $moyenneFinale = $noteExamenSur20;
                                
                                // Pas de CC en deuxième session
                                $moyenneCC = null;
                                
                                // Sauvegarder dans la base si la note d'examen existe
                                if ($noteExamenSur20 !== null) {
                                    // Vérifier si une entrée existe déjà
                                    $sqlCheck = "SELECT COUNT(*) FROM cotes_grille 
                                                WHERE matricule = ? AND ECUE_idECUE = ? 
                                                AND session_idsession = ? AND annee_acad_id = ?";
                                    $stmtCheck = $pdo->prepare($sqlCheck);
                                    $stmtCheck->execute([$matricule, $idECUE, $currentSessionId, $anneeId]);
                                    $exists = $stmtCheck->fetchColumn() > 0;
                                    
                                    if ($exists) {
                                        $sqlUpdate = "UPDATE cotes_grille 
                                                    SET CC = ?, EX = ?, MF = ?, date_compilation = NOW(), idUser = ?
                                                    WHERE matricule = ? AND ECUE_idECUE = ? 
                                                    AND session_idsession = ? AND annee_acad_id = ?";
                                        $stmtUpdate = $pdo->prepare($sqlUpdate);
                                        $stmtUpdate->execute([
                                            $moyenneCC, $noteExamenSur20, $moyenneFinale, 
                                            isset($_SESSION['id']) ? $_SESSION['id'] : null,
                                            $matricule, $idECUE, $currentSessionId, $anneeId
                                        ]);
                                    } else {
                                        $sqlInsert = "INSERT INTO cotes_grille 
                                                    (CC, EX, MF, ECUE_idECUE, session_idsession, matricule, annee_acad_id, date_compilation, idUser)
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                                        $stmtInsert = $pdo->prepare($sqlInsert);
                                        $stmtInsert->execute([
                                            $moyenneCC, $noteExamenSur20, $moyenneFinale, 
                                            $idECUE, $currentSessionId, $matricule, $anneeId, 
                                            isset($_SESSION['id']) ? $_SESSION['id'] : null
                                        ]);
                                    }
                                }
                            }
                            else {
                                // Pour les autres sessions éventuelles (si elles existent)
                                // Récupérer toutes les évaluations avec leurs types et notes pour cet étudiant dans cette session
                                $sqlSessionEvals = "SELECT p.coteObtenu, e.note_max, e.ponderation, t.categorie
                                                   FROM points p
                                                   JOIN evaluations e ON p.typeEvaluation = e.idType AND p.session_idsession = e.session_idsession
                                                   JOIN typeevaluation t ON e.idType = t.idType
                                                   WHERE p.matricule = ? AND p.ECUE_idECUE = ? 
                                                   AND p.session_idsession = ? AND p.annee_acad_id = ?";
                                
                                $stmtSessionEvals = $pdo->prepare($sqlSessionEvals);
                                $stmtSessionEvals->execute([$matricule, $idECUE, $currentSessionId, $anneeId]);
                                $sessionEvaluations = $stmtSessionEvals->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Organiser les évaluations par type pour cette session
                                $evaluationsCC = [];
                                $evaluationsEX = [];
                                
                                foreach ($sessionEvaluations as $eval) {
                                    if ($eval['coteObtenu'] !== null) {
                                        $noteSur20 = ($eval['coteObtenu'] / $eval['note_max']) * 20;
                                        
                                        // IMPORTANT: Considérer 0 comme une note manquante
                                        if (abs($noteSur20) < 0.001) {
                                            continue;
                                        }
                                        
                                        if ($eval['categorie'] === 'CC') {
                                            $evaluationsCC[] = [
                                                'note' => $noteSur20,
                                                'ponderation' => floatval($eval['ponderation'])
                                            ];
                                        } else if ($eval['categorie'] === 'EX') {
                                            $evaluationsEX[] = [
                                                'note' => $noteSur20
                                            ];
                                        }
                                    }
                                }
                                
                                // Calculer la moyenne des CC
                                $moyenneCC = null;
                                if (!empty($evaluationsCC)) {
                                    $moyenneCC = 0;
                                    $totalPonderation = 0;
                                    
                                    foreach ($evaluationsCC as $cc) {
                                        $ponderation = $cc['ponderation'] ?: 1;
                                        $moyenneCC += $cc['note'] * $ponderation;
                                        $totalPonderation += $ponderation;
                                    }
                                    
                                    if ($totalPonderation > 0) {
                                        $moyenneCC = $moyenneCC / $totalPonderation;
                                    }
                                }
                                
                                // Récupérer la note d'examen
                                $noteExamenSur20 = null;
                                if (!empty($evaluationsEX)) {
                                    $noteExamenSur20 = $evaluationsEX[0]['note']; // On prend la première note d'examen
                                }
                                
                                // Récupérer la configuration spécifique à cette session ou utiliser des valeurs par défaut
                                $sqlSessionConfig = "SELECT ponderation_cc, ponderation_ex 
                                                     FROM configuration_moyenne 
                                                     WHERE idECUE = ? AND session_idsession = ? AND annee_acad_id = ?
                                                     ORDER BY dateCreation DESC LIMIT 1";
                                $stmtSessionConfig = $pdo->prepare($sqlSessionConfig);
                                $stmtSessionConfig->execute([$idECUE, $currentSessionId, $anneeId]);
                                $sessionConfig = $stmtSessionConfig->fetch(PDO::FETCH_ASSOC);
                                
                                $sessionPonderationCC = $sessionConfig ? floatval($sessionConfig['ponderation_cc']) : 0.4;
                                $sessionPonderationEX = $sessionConfig ? floatval($sessionConfig['ponderation_ex']) : 0.6;
                                
                                // Calculer la moyenne finale
                                $moyenneFinale = null;
                                if ($moyenneCC !== null && $noteExamenSur20 !== null) {
                                    $moyenneFinale = ($moyenneCC * $sessionPonderationCC) + ($noteExamenSur20 * $sessionPonderationEX);
                                } else if ($moyenneCC !== null) {
                                    $moyenneFinale = $moyenneCC;
                                } else if ($noteExamenSur20 !== null) {
                                    $moyenneFinale = $noteExamenSur20;
                                }
                                
                                // Sauvegarder dans la base si au moins une note existe
                                if ($moyenneCC !== null || $noteExamenSur20 !== null) {
                                    // Vérifier si une entrée existe déjà
                                    $sqlCheck = "SELECT COUNT(*) FROM cotes_grille 
                                                WHERE matricule = ? AND ECUE_idECUE = ? 
                                                AND session_idsession = ? AND annee_acad_id = ?";
                                    $stmtCheck = $pdo->prepare($sqlCheck);
                                    $stmtCheck->execute([$matricule, $idECUE, $currentSessionId, $anneeId]);
                                    $exists = $stmtCheck->fetchColumn() > 0;
                                    
                                    if ($exists) {
                                        $sqlUpdate = "UPDATE cotes_grille 
                                                    SET CC = ?, EX = ?, MF = ?, date_compilation = NOW(), idUser = ?
                                                    WHERE matricule = ? AND ECUE_idECUE = ? 
                                                    AND session_idsession = ? AND annee_acad_id = ?";
                                        $stmtUpdate = $pdo->prepare($sqlUpdate);
                                        $stmtUpdate->execute([
                                            $moyenneCC, $noteExamenSur20, $moyenneFinale, 
                                            isset($_SESSION['id']) ? $_SESSION['id'] : null,
                                            $matricule, $idECUE, $currentSessionId, $anneeId
                                        ]);
                                    } else {
                                        $sqlInsert = "INSERT INTO cotes_grille 
                                                    (CC, EX, MF, ECUE_idECUE, session_idsession, matricule, annee_acad_id, date_compilation, idUser)
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                                        $stmtInsert = $pdo->prepare($sqlInsert);
                                        $stmtInsert->execute([
                                            $moyenneCC, $noteExamenSur20, $moyenneFinale, 
                                            $idECUE, $currentSessionId, $matricule, $anneeId, 
                                            isset($_SESSION['id']) ? $_SESSION['id'] : null
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Valider la transaction
                    $pdo->commit();
                    
                    // Préparer la réponse
                    $response = [
                        'success' => true,
                        'message' => 'Compilation des notes terminée avec succès. Les moyennes ont été calculées et enregistrées dans la grille de notes.'
                    ];
                    
                    echo json_encode($response);
                    
                } catch (Exception $e) {
                    // En cas d'erreur, annuler la transaction
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erreur lors de la compilation des notes: ' . $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                ?>
                
