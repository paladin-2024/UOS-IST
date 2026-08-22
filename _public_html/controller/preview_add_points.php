<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si les paramètres requis sont fournis
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['idECUE']) || 
    !isset($_POST['session_id']) || 
    !isset($_POST['points_to_add'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$idECUE = intval($_POST['idECUE']);
$sessionId = intval($_POST['session_id']);
$anneeId = intval($_POST['annee_acad_id']);
$pointsToAdd = floatval($_POST['points_to_add']);

// Validation des données
if ($pointsToAdd <= 0 || $pointsToAdd > 5) {
    echo json_encode(['success' => false, 'message' => 'Les points à ajouter doivent être entre 0.1 et 5.']);
    exit;
}

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

try {
    // Récupérer le nom de la session
    $sqlSession = "SELECT description FROM session WHERE idsession = ?";
    $stmtSession = $pdo->prepare($sqlSession);
    $stmtSession->execute([$sessionId]);
    $session = $stmtSession->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Session non trouvée']);
        exit;
    }
    
    // Récupérer la configuration des pondérations (pour la première session)
    $sqlConfig = "SELECT ponderation_cc, ponderation_ex 
                 FROM configuration_moyenne 
                 WHERE \"idECUE\" = ? AND session_idsession = ? AND annee_acad_id = ?
                 ORDER BY \"dateCreation\" DESC LIMIT 1";
                 
    $stmtConfig = $pdo->prepare($sqlConfig);
    $stmtConfig->execute([$idECUE, $sessionId, $anneeId]);
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    
    // Pondérations par défaut si aucune configuration trouvée
    $ponderationCC = $config ? floatval($config['ponderation_cc']) : 0.4;
    $ponderationEX = $config ? floatval($config['ponderation_ex']) : 0.6;
    
    // Récupérer tous les étudiants pour cet ECUE
    $sqlEtudiants = "SELECT e.matricule, e.noms 
                    FROM etudiant e
                    WHERE e.promotion_idpromotion IN (
                        SELECT promotion_idpromotion 
                        FROM semestre s 
                        JOIN ue u ON s.idsemestre = u.semestre_idsemestre
                        WHERE u.\"idUE\" IN (SELECT \"UE_idUE\" FROM ecue WHERE \"idECUE\" = ?)
                    )
                    ORDER BY e.noms";
    
    $sqlSessionsPremiere = "SELECT idsession FROM session WHERE \"designSession\" LIKE '%Première%' OR \"designSession\" LIKE '%Premiere%' LIMIT 1";
    $stmtSessionsPremiere = $pdo->query($sqlSessionsPremiere);
    $sessionPremiere = $stmtSessionsPremiere->fetchColumn();
    
    $stmtEtudiants = $pdo->prepare($sqlEtudiants);
    $stmtEtudiants->execute([$idECUE]);
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer toutes les évaluations définies pour cet ECUE
    $sqlAllEvals = "SELECT e.idevaluation, e.titre, e.note_max, e.ponderation, 
                   t.\"idType\", t.\"designationT\", t.categorie, s.idsession, s.description
                   FROM evaluations e
                   JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
                   JOIN session s ON e.session_idsession = s.idsession
                   WHERE e.\"idECUE\" = ? AND e.annee_acad_id = ? AND s.idsession = ?
                   ORDER BY e.date_evaluation";
    
    $stmtAllEvals = $pdo->prepare($sqlAllEvals);
    $stmtAllEvals->execute([$idECUE, $anneeId, $sessionId]);
    $allEvaluations = $stmtAllEvals->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les évaluations par catégorie
    $totalEvalsCC = 0;
    $totalEvalsEX = 0;
    
    foreach ($allEvaluations as $eval) {
        if ($eval['categorie'] === 'CC') {
            $totalEvalsCC++;
        } else if ($eval['categorie'] === 'EX') {
            $totalEvalsEX++;
        }
    }
    
    // Si aucun étudiant n'est trouvé
    if (empty($etudiants)) {
        echo json_encode(['success' => false, 'message' => 'Aucun étudiant trouvé pour cet ECUE.']);
        exit;
    }
    
    // Calculer les nouvelles notes pour chaque étudiant
    $studentsData = [];
    $totalStudents = count($etudiants);
    $studentsWithMax = 0;
    $studentsAffected = 0;
    $studentsWithoutNotes = 0;
    
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        
        // Récupérer toutes les notes de l'étudiant pour cet ECUE
        $sqlNotes = "SELECT p.\"coteObtenu\", p.typeEvaluation
                    FROM points p
                    WHERE p.matricule = ? AND p.\"ECUE_idECUE\" = ? AND p.annee_acad_id = ? AND p.session_idsession = ?";
        
        $stmtNotes = $pdo->prepare($sqlNotes);
        $stmtNotes->execute([$matricule, $idECUE, $anneeId, $sessionId]);
        $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les évaluations par catégorie
        $evaluationsCC = [];
        $evaluationsEX = [];
        
        // Préparer un tableau pour les évaluations avec note
        $evaluationsWithNote = [];
        
        // Vérifier la complétude des évaluations
        $ccComplete = true;
        $exComplete = true;
        
        // Préparer les notes à partir des données existantes
        foreach ($notes as $note) {
            foreach ($allEvaluations as $eval) {
                if ($eval['idType'] == $note['typeEvaluation']) {
                    $noteSur20 = ($note['coteObtenu'] / $eval['note_max']) * 20;
                    
                    // Considérer 0 comme une note manquante
                    if (abs($noteSur20) < 0.001) {
                        // Marquer la catégorie comme incomplète
                        if ($eval['categorie'] === 'CC') {
                            $ccComplete = false;
                        } else if ($eval['categorie'] === 'EX') {
                            $exComplete = false;
                        }
                        continue;
                    }
                    
                    // Marquer cette évaluation comme ayant une note
                    $evaluationsWithNote[$eval['idevaluation']] = true;
                    
                    // Classer l'évaluation selon sa catégorie
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
                    
                    break;
                }
            }
        }
        
        // Ajouter les évaluations sans note
        foreach ($allEvaluations as $eval) {
            if (!isset($evaluationsWithNote[$eval['idevaluation']])) {
                // Marquer la catégorie comme incomplète
                if ($eval['categorie'] === 'CC') {
                    $ccComplete = false;
                } else if ($eval['categorie'] === 'EX') {
                    $exComplete = false;
                }
            }
        }
        
        // Vérifier la complétude en comparant le nombre d'évaluations
        if (count($evaluationsCC) < $totalEvalsCC) {
            $ccComplete = false;
        }
        if (count($evaluationsEX) < $totalEvalsEX) {
            $exComplete = false;
        }
        
        // Calculer la moyenne selon la session
        $currentNote = null;
        
        // Pour la première session (CC + EX)
        if ($sessionId === $sessionPremiere) {
            // Calculer la moyenne des CC
            $moyenneCC = null;
            if ($ccComplete && !empty($evaluationsCC)) {
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
            $noteExamen = null;
            if ($exComplete && !empty($evaluationsEX)) {
                $noteExamen = $evaluationsEX[0]['note'];
            }
            
            // Calculer la moyenne finale seulement si CC et EX sont disponibles
            if ($moyenneCC !== null && $noteExamen !== null) {
                $currentNote = ($moyenneCC * $ponderationCC) + ($noteExamen * $ponderationEX);
            }
        } 
        // Pour la deuxième session (EX uniquement)
        else {
            if ($exComplete && !empty($evaluationsEX)) {
                $currentNote = $evaluationsEX[0]['note']; // La note d'examen est la moyenne finale
            }
        }
        
        // Si l'étudiant n'a pas de note complète, ne pas l'ajouter
        if ($currentNote === null) {
            $studentsWithoutNotes++;
            continue;
        }
        
        // Calculer la nouvelle note
        $newNote = min(20, $currentNote + $pointsToAdd);
        
        // Compter les étudiants déjà à la note maximale
        if ($currentNote >= 20) {
            $studentsWithMax++;
            $newNote = 20;
        } else {
            // Compter les étudiants qui vont recevoir des points
            $studentsAffected++;
        }
        
        $studentsData[] = [
            'matricule' => $etudiant['matricule'],
            'noms' => $etudiant['noms'],
            'current_note' => $currentNote,
            'new_note' => $newNote
        ];
    }
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'session_name' => $session['description'],
        'points_to_add' => $pointsToAdd,
        'students' => $studentsData,
        'summary' => [
            'total_students' => $totalStudents,
            'students_with_notes' => count($studentsData),
            'students_without_notes' => $studentsWithoutNotes,
            'students_with_max' => $studentsWithMax,
            'students_affected' => $studentsAffected
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de la prévisualisation: ' . $e->getMessage()
    ]);
}
?>
