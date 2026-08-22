<?php
session_start();
header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    require_once dirname(__DIR__) . '/config/Connexion.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion: ' . $e->getMessage()]);
    exit;
}

$idpromotion = isset($_GET['idpromotion']) ? intval($_GET['idpromotion']) : 0;

if (!$idpromotion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID promotion invalide']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    $result = [
        'success' => true,
        'total_students' => 0,
        'inscriptions' => 0,
        'notes' => 0,
        'absences' => 0,
        'examinations' => 0,
        'fees' => 0,
        'internships' => 0,
        'dissertation' => 0
    ];
    
    // Compter les étudiants
    try {
        $stmt = $connexion->prepare("SELECT COUNT(*) as total FROM etudiant WHERE promotion_idpromotion = ?");
        $stmt->execute([$idpromotion]);
        $result['total_students'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        error_log("Erreur comptage étudiants: " . $e->getMessage());
    }
    
    // Compter les notes (cotes_grille)
    try {
        $stmt = $connexion->prepare("
            SELECT COUNT(*) as total FROM cotes_grille 
            WHERE matricule IN (
                SELECT matricule FROM etudiant WHERE promotion_idpromotion = ?
            )
        ");
        $stmt->execute([$idpromotion]);
        $result['notes'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        error_log("Erreur comptage notes: " . $e->getMessage());
    }
    
    // Compter les frais/paiements
    try {
        $stmt = $connexion->prepare("
            SELECT COUNT(*) as total FROM paiement 
            WHERE etudiant_idetudiant IN (
                SELECT idetudiant FROM etudiant WHERE promotion_idpromotion = ?
            )
        ");
        $stmt->execute([$idpromotion]);
        $result['fees'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        error_log("Erreur comptage paiements: " . $e->getMessage());
    }
    
    // Compter les stages (stage_assignments)
    try {
        $stmt = $connexion->prepare("
            SELECT COUNT(*) as total FROM stage_assignments 
            WHERE idetudiant IN (
                SELECT idetudiant FROM etudiant WHERE promotion_idpromotion = ?
            )
        ");
        $stmt->execute([$idpromotion]);
        $result['internships'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        // Table might not exist, ignore
    }
    
    // Compter les mémoires/soutenances
    try {
        $stmt = $connexion->prepare("
            SELECT COUNT(*) as total 
            FROM soutenance s
            JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
            JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
            WHERE e.promotion_idpromotion = ?
        ");
        $stmt->execute([$idpromotion]);
        $result['dissertation'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        error_log("Erreur comptage soutenances: " . $e->getMessage());
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Erreur get_promotion_dependencies: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
