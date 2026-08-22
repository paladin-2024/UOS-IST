<?php
header('Content-Type: application/json');
session_start(); // Assurez-vous que la session est démarrée
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté. Veuillez vous connecter.']);
    exit;
}

// Récupérer l'ID de l'utilisateur connecté
$userId = intval($_SESSION['id']);

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
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : "Points supplémentaires";

// Validation des données
if ($pointsToAdd <= 0 || $pointsToAdd > 5) {
    echo json_encode(['success' => false, 'message' => 'Les points à ajouter doivent être entre 0.1 et 5.']);
    exit;
}

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

try {
    // Commencer une transaction
    $pdo->beginTransaction();
    
    // 1. Vérifier si un type d'évaluation de type "Bonus" existe, sinon le créer
    $sqlCheckType = "SELECT idType FROM typeevaluation WHERE designationT = 'Points bonus' AND categorie = 'CC'";
    $stmtCheckType = $pdo->query($sqlCheckType);
    $bonusType = $stmtCheckType->fetch(PDO::FETCH_ASSOC);
    
    if (!$bonusType) {
        // Créer un nouveau type d'évaluation pour les bonus
        $sqlCreateType = "INSERT INTO typeevaluation (designationT, categorie) VALUES ('Points bonus', 'CC')";
        $pdo->exec($sqlCreateType);
        $bonusTypeId = $pdo->lastInsertId();
    } else {
        $bonusTypeId = $bonusType['idType'];
    }
    
    // 2. Créer une nouvelle évaluation de type "Bonus"
    $currentDate = date('Y-m-d');
    $titre = "Bonus: $reason";
    
    $sqlAddEvaluation = "INSERT INTO evaluations (titre, description, date_evaluation, idECUE, 
                         idType, ponderation, note_max, session_idsession, est_visible, annee_acad_id, idUser) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtAddEvaluation = $pdo->prepare($sqlAddEvaluation);
    $stmtAddEvaluation->execute([
        $titre,
        "Points supplémentaires ajoutés: $pointsToAdd",
        $currentDate,
        $idECUE,
        $bonusTypeId,
        1.00, // Pondération standard
        $pointsToAdd, // La note maximale est égale aux points à ajouter
        $sessionId,
        1, // Visible pour les étudiants
        $anneeId,
        $userId
    ]);
    
    $evaluationId = $pdo->lastInsertId();
    
    // 3. Récupérer tous les étudiants pour cet ECUE
    $sqlEtudiants = "SELECT e.matricule, e.noms 
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
    
    // Si aucun étudiant n'est trouvé
    if (empty($etudiants)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Aucun étudiant trouvé pour cet ECUE.']);
        exit;
    }
    
    // 4. Récupérer les moyennes existantes pour vérifier les notes maximales
    $sqlMoyennes = "SELECT matricule, MF 
                   FROM cotes_grille 
                   WHERE ECUE_idECUE = ? AND session_idsession = ? AND annee_acad_id = ?";
    $stmtMoyennes = $pdo->prepare($sqlMoyennes);
    $stmtMoyennes->execute([$idECUE, $sessionId, $anneeId]);
    $moyennes = $stmtMoyennes->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Ajouter les points à tous les étudiants (sauf ceux ayant déjà 20/20)
    $studentsAffected = 0;
    $studentsWithMax = 0;
    $studentsData = [];
    
    // Préparer la requête pour ajouter les points bonus
    $sqlAddPoints = "INSERT INTO points (coteObtenu, typeEvaluation, ECUE_idECUE, session_idsession, matricule, annee_acad_id)
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmtAddPoints = $pdo->prepare($sqlAddPoints);
    
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        $currentNote = isset($moyennes[$matricule]) ? floatval($moyennes[$matricule]) : null;
        
        // Vérifier si l'étudiant a déjà une note maximale
        if ($currentNote !== null && $currentNote >= 20) {
            $studentsWithMax++;
            continue;
        }
        
        // Ajouter les points bonus à cet étudiant (qu'il ait des notes ou non)
        $stmtAddPoints->execute([
            $pointsToAdd, // Cote obtenue (points bonus)
            $bonusTypeId, // Type d'évaluation (bonus)
            $idECUE,
            $sessionId,
            $matricule,
            $anneeId
        ]);
        
        $studentsAffected++;
        
        // Collecter les données pour la réponse JSON
        $studentsData[] = [
            'matricule' => $matricule,
            'noms' => $etudiant['noms'],
            'current_note' => $currentNote,
            'points_added' => $pointsToAdd,
            'new_note' => $currentNote !== null ? min(20, $currentNote + $pointsToAdd) : $pointsToAdd
        ];
    }
    
    // Valider la transaction seulement si au moins un étudiant a été affecté
    if ($studentsAffected > 0) {
        $pdo->commit();
        
        // Préparer la réponse
        $response = [
            'success' => true,
            'message' => "Points ajoutés avec succès à {$studentsAffected} étudiant(s). Une nouvelle évaluation de type bonus a été créée.",
            'evaluation_id' => $evaluationId,
            'evaluation_title' => $titre,
            'stats' => [
                'total_students' => count($etudiants),
                'students_with_max' => $studentsWithMax,
                'students_affected' => $studentsAffected
            ],
            'students' => $studentsData
        ];
        
        echo json_encode($response);
    } else {
        // Si aucun étudiant n'a été affecté, annuler la transaction
        $pdo->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'Aucun point n\'a été ajouté. Tous les étudiants ont déjà la note maximale de 20/20.'
        ]);
    }
    
} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'ajout des points: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
