<?php
session_start();
header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Inclure la classe de connexion
require_once "../config/Connexion.php";

try {
    // Obtenir l'instance de connexion
    $connexion = Connexion::getInstance();
    $pdo = $connexion->getPDO();
    
    // Récupérer l'ID de la section
    $sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
    $anneeId = isset($_GET['annee_id']) ? (int)$_GET['annee_id'] : 0;
    $currentUserId = $_SESSION['id'];
    $hasFullAccess = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
    
    if (!$sectionId) {
        echo json_encode(['success' => false, 'message' => 'ID section manquant']);
        exit;
    }

    if (!$hasFullAccess && $anneeId > 0) {
        $queryAccess = "SELECT COUNT(*) 
                        FROM responsable_section
                        WHERE \"idUser\" = :user_id
                          AND section_idsection = :section_id
                          AND annee_acad_idannee_acad = :annee_id";
        $stmtAccess = $pdo->prepare($queryAccess);
        $stmtAccess->execute([
            ':user_id' => $currentUserId,
            ':section_id' => $sectionId,
            ':annee_id' => $anneeId
        ]);

        if ((int) $stmtAccess->fetchColumn() === 0) {
            echo json_encode(['success' => true, 'specialisations' => []]);
            exit;
        }
    }
    
    // Requête pour récupérer les spécialisations de la section
    $sql = "
        SELECT DISTINCT 
            sp.\"idSpecialisation\", 
            sp.designation,
            ur.\"designation_UR\"
        FROM specialisation sp
        LEFT JOIN unite_recherche ur ON sp.\"idUnite_recherche\" = ur.idunite_recherche
        LEFT JOIN orientation ori ON sp.idorientation = ori.idorientation
        LEFT JOIN section sec ON ori.section_idsection = sec.idsection
    ";

    $params = [':section_id' => $sectionId];
    $whereConditions = ["sec.idsection = :section_id"];

    if ($anneeId > 0) {
        $sql .= " INNER JOIN sujets sj ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\" ";
        $whereConditions[] = "sj.annee_acad_idannee_acad = :annee_id";
        $params[':annee_id'] = $anneeId;
    }

    $sql .= " WHERE " . implode(' AND ', $whereConditions) . " ORDER BY sp.designation";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $placeholder => $value) {
        $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    $specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'specialisations' => $specialisations]);
    
} catch (PDOException $e) {
    error_log("Erreur dans get_specialisations_by_section.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
} catch (Exception $e) {
    error_log("Erreur générale dans get_specialisations_by_section.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
