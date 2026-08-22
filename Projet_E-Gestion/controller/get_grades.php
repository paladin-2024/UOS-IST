<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si les paramètres requis sont fournis
if (!isset($_GET['evaluation']) || !isset($_GET['ecue'])) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$evaluationId = intval($_GET['evaluation']);
$ecueId = intval($_GET['ecue']);

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

try {
    // Récupérer la valeur de credit_heure depuis la configuration
    $stmtConfig = $pdo->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
    $creditHeure = $stmtConfig->fetchColumn();
    
    // Utiliser une valeur par défaut de 25 si non configurée
    $creditHeure = $creditHeure ?: 25;
    
    // Récupérer les informations sur l'évaluation
    $sql = "SELECT e.*, t.\"designationT\", t.categorie, s.\"designSession\", s.description as session_description
            FROM evaluations e
            JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
            JOIN session s ON e.session_idsession = s.idsession
            WHERE e.idevaluation = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$evaluationId]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evaluation) {
        echo json_encode(['error' => 'Évaluation non trouvée']);
        exit;
    }
    
    // Déterminer si c'est une deuxième session
    $isDeuxiemeSession = (stripos($evaluation['designSession'], 'deuxième') !== false || 
                          stripos($evaluation['session_description'], 'deuxieme') !== false);
    
    // Récupérer la configuration des pondérations
    $sqlConfig = "SELECT ponderation_cc, ponderation_ex
                 FROM configuration_moyenne
                 WHERE \"idECUE\" = ? AND session_idsession = ?
                 ORDER BY \"dateCreation\" DESC LIMIT 1";
                 
    $stmtConfig = $pdo->prepare($sqlConfig);
    $stmtConfig->execute([$ecueId, $evaluation['session_idsession']]);
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    
    // Pondérations par défaut si aucune configuration trouvée
    // Récupérer les pondérations depuis la configuration par défaut si pas de config spécifique
require_once '../models/Universite.php';
$universite = new Universite();
$ponderationsDefaut = $universite->getPonderationsDefaut();
$ponderationCC = $config ? floatval($config['ponderation_cc']) : $ponderationsDefaut['ponderation_cc'];
$ponderationEX = $config ? floatval($config['ponderation_ex']) : $ponderationsDefaut['ponderation_ex'];
    
    // Liste d'étudiants
    $students = [];
    
    // Requête pour récupérer les étudiants et leurs notes éventuelles
    if ($isDeuxiemeSession && $evaluation['categorie'] === 'EX') {
        // 1. Get the UE associated with this ECUE
        $sqlUE = 'SELECT "UE_idUE" FROM ecue WHERE "idECUE" = ?';
        $stmtUE = $pdo->prepare($sqlUE);
        $stmtUE->execute([$ecueId]);
        $idUE = $stmtUE->fetchColumn();
        
        if (!$idUE) {
            echo json_encode(['error' => 'Impossible de récupérer l\'UE associée à cet ECUE']);
            exit;
        }
        
        // 2. Get the first session ID
        $sqlSession = "SELECT idsession FROM session
                     WHERE LOWER(\"designSession\") LIKE 'premi%re session'
                     OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmtSession = $pdo->prepare($sqlSession);
        $stmtSession->execute();
        $session1Id = $stmtSession->fetchColumn();
        
        if (!$session1Id) {
            echo json_encode(['error' => 'Impossible de déterminer la première session']);
            exit;
        }
        
        // 3. First get all students who failed this specific ECUE
        $sqlFailed = "SELECT e.idetudiant, e.matricule, e.noms, p.\"coteObtenu\" as note
                    FROM etudiant e
                    LEFT JOIN points p ON e.matricule = p.matricule
                                AND p.\"ECUE_idECUE\" = ?
                                AND p.typeEvaluation = ?
                                AND p.session_idsession = ?
                    LEFT JOIN cotes_grille cg ON e.matricule = cg.matricule
                                AND cg.\"ECUE_idECUE\" = ?
                                AND cg.session_idsession = ?
                    WHERE e.promotion_idpromotion IN (
                        SELECT promotion_idpromotion
                        FROM semestre s
                        JOIN ue u ON s.idsemestre = u.semestre_idsemestre
                        WHERE u.\"idUE\" IN (SELECT \"UE_idUE\" FROM ecue WHERE \"idECUE\" = ?)
                    )
                    AND (cg.\"MF\" IS NULL OR cg.\"MF\" < 10)
                    ORDER BY e.noms";
                      
        $stmtFailed = $pdo->prepare($sqlFailed);
        $stmtFailed->execute([
            $ecueId, 
            $evaluation['idType'], 
            $evaluation['session_idsession'], 
            $ecueId, 
            $session1Id,
            $ecueId
        ]);
        
        $failedStudents = $stmtFailed->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Filter out students who validated the UE as a whole
        $students = [];
        foreach ($failedStudents as $student) {
            $matricule = $student['matricule'];
            
            // Check if the UE was validated in first session
            // Using configurable credit_heure instead of hardcoded 15
            $sqlUEValidation = "SELECT
                              SUM(cg.\"MF\" * ROUND((ec.\"CMI\" + ec.\"TD\" + ec.\"TP\")/{$creditHeure}, 2)) /
                              SUM(ROUND((ec.\"CMI\" + ec.\"TD\" + ec.\"TP\")/{$creditHeure}, 2)) AS moyenne_ponderee,
                              COUNT(cg.\"MF\") AS notes_count,
                              (SELECT COUNT(*) FROM ecue WHERE \"UE_idUE\" = ?) AS total_ecues
                            FROM cotes_grille cg
                            JOIN ecue ec ON cg.\"ECUE_idECUE\" = ec.\"idECUE\"
                            WHERE ec.\"UE_idUE\" = ?
                            AND cg.matricule = ?
                            AND cg.session_idsession = ?
                            AND cg.\"MF\" IS NOT NULL";
            $stmtUEValidation = $pdo->prepare($sqlUEValidation);
            $stmtUEValidation->execute([$idUE, $idUE, $matricule, $session1Id]);
            $ueResult = $stmtUEValidation->fetch(PDO::FETCH_ASSOC);
            
            // The UE is validated if the weighted average is >= 10 AND all ECUEs have grades
            $ueValidated = false;
            if ($ueResult &&
                $ueResult['moyenne_ponderee'] !== null &&
                $ueResult['moyenne_ponderee'] >= 10 &&
                $ueResult['notes_count'] == $ueResult['total_ecues']) {
                $ueValidated = true;
            }
            
            // If the UE is not validated, the student is eligible for the second session
            if (!$ueValidated) {
                $students[] = $student;
            }
        }
        
        // Check if we have eligible students
        if (count($students) == 0) {
            echo json_encode(['error' => 'Aucun étudiant n\'est éligible pour cette évaluation en deuxième session. Tous les étudiants ont validé l\'UE en première session.']);
            exit;
        }
    } else {
        // Première session ou contrôle continu - tous les étudiants
        $sqlStudents = "SELECT e.idetudiant, e.matricule, e.noms, p.\"coteObtenu\" as note
                      FROM etudiant e
                      LEFT JOIN points p ON e.matricule = p.matricule AND p.\"ECUE_idECUE\" = ?
                                       AND p.typeEvaluation = ? AND p.session_idsession = ?
                      WHERE e.promotion_idpromotion IN (
                          SELECT promotion_idpromotion
                          FROM semestre s
                          JOIN ue u ON s.idsemestre = u.semestre_idsemestre
                          WHERE u.\"idUE\" IN (SELECT \"UE_idUE\" FROM ecue WHERE \"idECUE\" = ?)
                      )
                      ORDER BY e.noms";
                      
        $stmtStudents = $pdo->prepare($sqlStudents);
        $stmtStudents->execute([$ecueId, $evaluation['idType'], $evaluation['session_idsession'], $ecueId]);
        
        $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'evaluation' => $evaluation['titre'],
        'evaluation_category' => $evaluation['categorie'],
        'note_max' => $evaluation['note_max'],
        'is_deuxieme_session' => $isDeuxiemeSession,
        'ponderation_cc' => $ponderationCC * 100,
        'ponderation_examen' => $ponderationEX * 100,
        'session_id' => $evaluation['session_idsession'],
        'session_name' => $evaluation['session_description'],
        'students' => $students
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>
