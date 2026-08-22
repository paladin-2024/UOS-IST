<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Non authentifié'
    ]);
    exit;
}

$stageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$stageId) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de stage manquant'
    ]);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du stage
    $query = "SELECT 
                s.idstage,
                s.lieu_stage,
                s.date_debut,
                s.date_fin,
                s.rapport_path,
                s.cote_lecteur,
                s.cote_entreprise,
                e.idetudiant,
                e.matricule,
                e.noms as nom_etudiant,
                p.designationPromotion,
                enc.noms as encadreur_nom,
                lect.noms as lecteur_nom
            FROM stage_assignments s
            JOIN etudiant e ON s.idetudiant = e.idetudiant
            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            LEFT JOIN agent enc ON s.idencadreur = enc.idAgent
            LEFT JOIN agent lect ON s.idlecteur = lect.idAgent
            WHERE s.idstage = :stage_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['stage_id' => $stageId]);
    $stage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stage) {
        echo json_encode([
            'success' => true,
            'stage' => $stage
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Stage non trouvé'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des détails du stage: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>
