<?php
session_start();
include_once "../config/Connexion.php";

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_GET['etudiant_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID étudiant manquant']);
    exit;
}

$etudiantId = (int)$_GET['etudiant_id'];
$connexion = Connexion::getInstance()->getPDO();

// Vérifier les droits d'accès
$currentUserId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1;

// Fonctions utilitaires
function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

// Vérifier les permissions si pas admin
if (!$hasFullAccess) {
    $currentAcademicYear = getCurrentAcademicYear($connexion);
    $userSections = getUserSections($connexion, $currentUserId, $currentAcademicYear);
    
    if (empty($userSections)) {
        echo json_encode(['success' => false, 'message' => 'Aucune section autorisée']);
        exit;
    }
    
    // Vérifier si l'étudiant appartient aux sections de l'utilisateur
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $queryCheck = "SELECT COUNT(*) FROM etudiant e
                   JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                   JOIN orientation o ON p.orientation_idorientation = o.idorientation
                   JOIN section sec ON o.section_idsection = sec.idsection
                   WHERE e.idetudiant = ? AND sec.idsection IN ($sectionsParams)";
    
    $stmtCheck = $connexion->prepare($queryCheck);
    $stmtCheck->execute(array_merge([$etudiantId], $userSections));
    
    if ($stmtCheck->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
        exit;
    }
}

try {
    // Récupérer le sujet validé de l'étudiant (le plus récent)
    $query = "SELECT idsujets, intitule, statut_validation 
              FROM sujets 
              WHERE etudiant_idetudiant = ? 
              AND statut_validation = 'Validé'
              ORDER BY idsujets DESC 
              LIMIT 1";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute([$etudiantId]);
    $sujet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sujet) {
        echo json_encode([
            'success' => true,
            'sujet_id' => $sujet['idsujets'],
            'intitule' => $sujet['intitule'],
            'statut' => $sujet['statut_validation']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Aucun sujet validé trouvé pour cet étudiant'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur get_student_subject: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
