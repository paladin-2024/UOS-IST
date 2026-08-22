<?php
// Activer l'affichage des erreurs pour le debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ne pas afficher les erreurs dans la sortie
ini_set('log_errors', 1);

// Fonction pour envoyer une réponse JSON et arrêter l'exécution
function sendJsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Fonction pour gérer les erreurs
function handleError($message, $debugInfo = []) {
    sendJsonResponse([
        'error' => $message,
        'debug_info' => $debugInfo,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

session_start();
require_once '../config/Connexion.php';

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_matricule'])) {
    handleError('Non autorisé - Session invalide', [
        'has_student_id' => isset($_SESSION['student_id']),
        'has_student_matricule' => isset($_SESSION['student_matricule']),
        'session_keys' => array_keys($_SESSION)
    ]);
}

// Vérifier si l'étudiant est chef de promotion
$connexion = Connexion::getInstance()->getPDO();

try {
    // Récupérer l'ID du chef de promotion pour cet étudiant avec plus d'informations
    $queryChef = "SELECT cp.id_chef, cp.promotion_idpromotion, cp.annee_acad_idannee_acad,
                         e.noms as nom_etudiant, p.\"designationPromotion\", aa.designation as annee_acad
                  FROM chef_promotion cp 
                  INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant 
                  INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                  INNER JOIN annee_acad aa ON cp.annee_acad_idannee_acad = aa.idannee_acad
                  WHERE e.idetudiant = :student_id 
                  AND cp.est_actif = 1
                  ORDER BY cp.date_nomination DESC
                  LIMIT 1";

    $stmtChef = $connexion->prepare($queryChef);
    $stmtChef->bindParam(':student_id', $_SESSION['student_id'], PDO::PARAM_INT);
    $stmtChef->execute();

    $chefPromotion = $stmtChef->fetch(PDO::FETCH_ASSOC);

    if (!$chefPromotion) {
        // Vérifier si l'étudiant existe
        $queryEtudiant = "SELECT e.*, p.\"designationPromotion\" 
                          FROM etudiant e 
                          LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                          WHERE e.idetudiant = :student_id";
        $stmtEtudiant = $connexion->prepare($queryEtudiant);
        $stmtEtudiant->bindParam(':student_id', $_SESSION['student_id'], PDO::PARAM_INT);
        $stmtEtudiant->execute();
        $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
        
        $errorMessage = 'Vous n\'êtes pas autorisé à consulter ces données.';
        if ($etudiant) {
            $errorMessage .= ' Étudiant trouvé: ' . $etudiant['noms'] . ' (' . $etudiant['designationPromotion'] . '), mais pas de chef de promotion actif.';
        } else {
            $errorMessage .= ' Étudiant non trouvé dans la base de données.';
        }
        
        handleError($errorMessage, [
            'student_id' => $_SESSION['student_id'],
            'annee_acad' => $_SESSION['annee_acad'] ?? 'Non défini',
            'etudiant_existe' => $etudiant ? true : false,
            'etudiant_data' => $etudiant
        ]);
    }
} catch (Exception $e) {
    handleError('Erreur lors de la vérification des autorisations: ' . $e->getMessage(), [
        'student_id' => $_SESSION['student_id'],
        'annee_acad' => $_SESSION['annee_acad'] ?? 'Non défini'
    ]);
}

try {
    // Récupérer la promotion de l'étudiant chef de promotion actuel
    $queryPromotion = "SELECT e.promotion_idpromotion 
                       FROM etudiant e 
                       WHERE e.idetudiant = :student_id";
    
    $stmtPromotion = $connexion->prepare($queryPromotion);
    $stmtPromotion->bindParam(':student_id', $_SESSION['student_id']);
    $stmtPromotion->execute();
    $promotionData = $stmtPromotion->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotionData) {
        handleError('Promotion non trouvée');
    }
    
    $promotionId = $promotionData['promotion_idpromotion'];

    // Récupérer tous les suivis d'enseignements de la promotion
    // Vérifier d'abord si la table utilise chef_promotion_id ou idUser
    $checkColumnQuery = "SHOW COLUMNS FROM suivi_enseignements LIKE 'chef_promotion_id'";
    $checkStmt = $connexion->prepare($checkColumnQuery);
    $checkStmt->execute();
    $hasChefPromotionId = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hasChefPromotionId) {
        // Structure avec chef_promotion_id
        $query = "SELECT 
                    se.id_suivi,
                    se.date_cours,
                    se.heure_debut,
                    se.heure_fin,
                    se.type_cours,
                    se.salle,
                    se.commentaire,
                    se.date_encodage,
                    se.chef_promotion_id,
                    e.\"designationECUE\",
                    ue.\"designationUE\",
                    CONCAT(a.noms) as nom_enseignant,
                    g.designation as grade_enseignant,
                    et_chef.noms as nom_chef_promotion,
                    cp.date_nomination as date_nomination_chef
                  FROM suivi_enseignements se
                  INNER JOIN ecue e ON se.\"idECUE\" = e.\"idECUE\"
                  INNER JOIN ue ue ON e.\"UE_idUE\" = ue.\"idUE\"
                  LEFT JOIN agent a ON se.enseignant_id = a.\"idAgent\"
                  LEFT JOIN grade g ON a.grade_id = g.idgrade
                  INNER JOIN chef_promotion cp ON se.chef_promotion_id = cp.id_chef
                  INNER JOIN etudiant et_chef ON cp.idetudiant = et_chef.idetudiant
                  WHERE cp.promotion_idpromotion = :promotion_id
                  AND se.annee_acad_idannee_acad = :annee_acad
                  ORDER BY se.date_cours DESC, se.heure_debut DESC";
    } else {
        // Structure avec idUser (selon votre description)
        $query = "SELECT 
                    se.id_suivi,
                    se.date_cours,
                    se.heure_debut,
                    se.heure_fin,
                    se.type_cours,
                    se.salle,
                    se.commentaire,
                    se.date_encodage,
                    se.\"idUser\" as chef_promotion_id,
                    e.\"designationECUE\",
                    ue.\"designationUE\",
                    CONCAT(a.noms) as nom_enseignant,
                    g.designation as grade_enseignant,
                    et_chef.noms as nom_chef_promotion,
                    cp.date_nomination as date_nomination_chef
                  FROM suivi_enseignements se
                  INNER JOIN ecue e ON se.\"idECUE\" = e.\"idECUE\"
                  INNER JOIN ue ue ON e.\"UE_idUE\" = ue.\"idUE\"
                  LEFT JOIN agent a ON se.enseignant_id = a.\"idAgent\"
                  LEFT JOIN grade g ON a.grade_id = g.idgrade
                  INNER JOIN chef_promotion cp ON se.\"idUser\" = cp.idetudiant
                  INNER JOIN etudiant et_chef ON cp.idetudiant = et_chef.idetudiant
                  WHERE cp.promotion_idpromotion = :promotion_id
                  AND se.annee_acad_idannee_acad = :annee_acad
                  ORDER BY se.date_cours DESC, se.heure_debut DESC";
    }

    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':promotion_id', $promotionId);
    $stmt->bindParam(':annee_acad', $_SESSION['annee_acad']);
    $stmt->execute();

    $suivis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les statistiques pour toute la promotion
    if ($hasChefPromotionId) {
        $queryStats = "SELECT 
                         COUNT(*) as total_seances,
                         COUNT(DISTINCT se.\"idECUE\") as matieres_enseignees,
                         COUNT(DISTINCT DATE(se.date_cours)) as jours_cours,
                         SUM(TIME_TO_SEC(TIMEDIFF(se.heure_fin, se.heure_debut))/3600) as total_heures
                       FROM suivi_enseignements se
                       INNER JOIN chef_promotion cp ON se.chef_promotion_id = cp.id_chef
                       WHERE cp.promotion_idpromotion = :promotion_id
                       AND se.annee_acad_idannee_acad = :annee_acad";
                       
        $queryStatsType = "SELECT 
                             se.type_cours,
                             COUNT(*) as nombre_seances,
                             SUM(TIME_TO_SEC(TIMEDIFF(se.heure_fin, se.heure_debut))/3600) as total_heures
                           FROM suivi_enseignements se
                           INNER JOIN chef_promotion cp ON se.chef_promotion_id = cp.id_chef
                           WHERE cp.promotion_idpromotion = :promotion_id
                           AND se.annee_acad_idannee_acad = :annee_acad
                           GROUP BY se.type_cours";
    } else {
        $queryStats = "SELECT 
                         COUNT(*) as total_seances,
                         COUNT(DISTINCT se.\"idECUE\") as matieres_enseignees,
                         COUNT(DISTINCT DATE(se.date_cours)) as jours_cours,
                         SUM(TIME_TO_SEC(TIMEDIFF(se.heure_fin, se.heure_debut))/3600) as total_heures
                       FROM suivi_enseignements se
                       INNER JOIN chef_promotion cp ON se.\"idUser\" = cp.idetudiant
                       WHERE cp.promotion_idpromotion = :promotion_id
                       AND se.annee_acad_idannee_acad = :annee_acad";
                       
        $queryStatsType = "SELECT 
                             se.type_cours,
                             COUNT(*) as nombre_seances,
                             SUM(TIME_TO_SEC(TIMEDIFF(se.heure_fin, se.heure_debut))/3600) as total_heures
                           FROM suivi_enseignements se
                           INNER JOIN chef_promotion cp ON se.\"idUser\" = cp.idetudiant
                           WHERE cp.promotion_idpromotion = :promotion_id
                           AND se.annee_acad_idannee_acad = :annee_acad
                           GROUP BY se.type_cours";
    }

    $stmtStats = $connexion->prepare($queryStats);
    $stmtStats->bindParam(':promotion_id', $promotionId);
    $stmtStats->bindParam(':annee_acad', $_SESSION['annee_acad']);
    $stmtStats->execute();

    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Récupérer les statistiques par type de cours pour toute la promotion
    $stmtStatsType = $connexion->prepare($queryStatsType);
    $stmtStatsType->bindParam(':promotion_id', $promotionId);
    $stmtStatsType->bindParam(':annee_acad', $_SESSION['annee_acad']);
    $stmtStatsType->execute();

    $statsParType = $stmtStatsType->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter une information pour identifier les enregistrements du chef actuel
    foreach ($suivis as &$suivi) {
        if ($hasChefPromotionId) {
            $suivi['est_chef_actuel'] = ($suivi['chef_promotion_id'] == $chefPromotion['id_chef']);
        } else {
            $suivi['est_chef_actuel'] = ($suivi['chef_promotion_id'] == $_SESSION['student_id']);
        }
    }

    sendJsonResponse([
        'success' => true,
        'suivis' => $suivis,
        'stats' => $stats,
        'stats_par_type' => $statsParType,
        'chef_actuel' => [
            'id' => $chefPromotion['id_chef'],
            'promotion_id' => $promotionId
        ],
        'debug_info' => [
            'has_chef_promotion_id_column' => $hasChefPromotionId ? true : false,
            'promotion_id' => $promotionId,
            'annee_acad' => $_SESSION['annee_acad'],
            'chef_promotion_data' => $chefPromotion
        ]
    ]);

} catch (Exception $e) {
    handleError('Erreur lors de la récup��ration des données: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>