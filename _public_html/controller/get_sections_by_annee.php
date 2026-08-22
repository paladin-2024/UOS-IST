<?php
session_start();
include_once "../config/Connexion.php";

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_GET['annee_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID année manquant']);
    exit;
}

$anneeId = (int)$_GET['annee_id'];
$searchTerm = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$connexion = Connexion::getInstance()->getPDO();

// Vérifier les droits d'accès
$currentUserId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1;

// Fonctions utilitaires
function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE \"idUser\" = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

try {
    $whereConditions = [];
    $queryParams = [':annee_id' => $anneeId];
    
    // Si pas admin, filtrer par sections autorisées
    if (!$hasFullAccess) {
        $userSections = getUserSections($connexion, $currentUserId, $anneeId);
        if (!empty($userSections)) {
            $sectionPlaceholders = [];
            foreach ($userSections as $index => $sectionId) {
                $placeholder = ':section_' . $index;
                $sectionPlaceholders[] = $placeholder;
                $queryParams[$placeholder] = (int) $sectionId;
            }
            $whereConditions[] = "sec.idsection IN (" . implode(', ', $sectionPlaceholders) . ")";
        } else {
            echo json_encode(['success' => true, 'sections' => []]);
            exit;
        }
    }
    
    if ($searchTerm !== '') {
        $whereConditions[] = "sec.designationSection LIKE :search_term";
        $queryParams[':search_term'] = '%' . $searchTerm . '%';
    }

    $whereClause = !empty($whereConditions) ? ' AND ' . implode(' AND ', $whereConditions) : '';
    
    // Récupérer les sections qui ont des promotions dans cette année académique
    $query = "SELECT DISTINCT sec.idsection, sec.\"designationSection\"
              FROM section sec
              JOIN orientation o ON sec.idsection = o.section_idsection
              JOIN promotion p ON o.idorientation = p.orientation_idorientation
              WHERE p.annee_acad_idannee_acad = :annee_id
              $whereClause
              ORDER BY sec.\"designationSection\"";
    
    $stmt = $connexion->prepare($query);
    foreach ($queryParams as $placeholder => $value) {
        if ($placeholder === ':annee_id') {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
            continue;
        }

        $stmt->bindValue($placeholder, $value);
    }
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'sections' => $sections]);
    
} catch (Exception $e) {
    error_log("Erreur get_sections_by_annee: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
