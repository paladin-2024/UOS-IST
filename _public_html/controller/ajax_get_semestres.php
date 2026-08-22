<?php
session_start();
require_once '../config/Connexion.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer l'ID de la promotion
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;

if ($promotionId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $currentUserId = $_SESSION['id'];
    $hasFullAccess = $_SESSION['idRole'] == 1;
    
    // Récupérer l'année académique en cours
    $checkColumn = "SHOW COLUMNS FROM annee_acad LIKE 'est_active'";
    $stmtCheck = $pdo->prepare($checkColumn);
    $stmtCheck->execute();
    $columnExists = $stmtCheck->fetch();

    if ($columnExists) {
        $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
    } else {
        $queryAnnee = "SELECT * FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
    }

    $stmtAnnee = $pdo->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier si l'utilisateur a accès à cette promotion
    if (!$hasFullAccess) {
        // Récupérer les sections dont l'utilisateur est responsable
        $querySection = "SELECT section_idsection 
                        FROM responsable_section 
                        WHERE idUser = :userId 
                        AND annee_acad_idannee_acad = :anneeId";
        
        $stmtSection = $pdo->prepare($querySection);
        $stmtSection->bindParam(':userId', $currentUserId);
        $stmtSection->bindParam(':anneeId', $currentYear['idannee_acad']);
        $stmtSection->execute();
        $userSections = $stmtSection->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($userSections)) {
            // Vérifier que la promotion appartient à une des sections de l'utilisateur
            $placeholders = implode(',', array_fill(0, count($userSections), '?'));
            $queryCheck = "SELECT COUNT(*) 
                          FROM promotion p
                          JOIN orientation o ON p.orientation_idorientation = o.idorientation
                          WHERE p.idpromotion = ?
                          AND o.section_idsection IN ($placeholders)";
            
            $params = array_merge([$promotionId], $userSections);
            $stmtCheck = $pdo->prepare($queryCheck);
            $stmtCheck->execute($params);
            
            if ($stmtCheck->fetchColumn() == 0) {
                // L'utilisateur n'a pas accès à cette promotion
                echo json_encode([]);
                exit;
            }
        } else {
            // L'utilisateur n'est responsable d'aucune section
            echo json_encode([]);
            exit;
        }
    }
    
    // Récupérer les semestres de la promotion
    $query = "SELECT idsemestre, numeroSemestre 
              FROM semestre 
              WHERE promotion_idpromotion = :promotionId 
              ORDER BY numeroSemestre";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->execute();
    
    $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($semestres);
    
} catch (Exception $e) {
    error_log("Erreur dans ajax_get_semestres.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>