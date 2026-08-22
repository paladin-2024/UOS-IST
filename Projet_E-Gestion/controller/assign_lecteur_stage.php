<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stageId = isset($_POST['stage_id']) ? intval($_POST['stage_id']) : 0;
    $lecteurId = isset($_POST['lecteur_id']) ? intval($_POST['lecteur_id']) : 0;
    
    // Validation
    if (!$stageId || !$lecteurId) {
        echo json_encode([
            'success' => false,
            'message' => 'Données invalides'
        ]);
        exit;
    }
    
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Vérifier que le stage existe et qu'un rapport est déposé
        $checkQuery = "SELECT rapport_path FROM stage_assignments WHERE idstage = :stage_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute(['stage_id' => $stageId]);
        $stage = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stage) {
            echo json_encode([
                'success' => false,
                'message' => 'Stage introuvable'
            ]);
            exit;
        }
        
        if (empty($stage['rapport_path'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Aucun rapport déposé pour ce stage'
            ]);
            exit;
        }
        
        // Attribuer le lecteur
        $updateQuery = "UPDATE stage_assignments 
                        SET idlecteur = :lecteur_id
                        WHERE idstage = :stage_id";
        
        $updateStmt = $db->prepare($updateQuery);
        $result = $updateStmt->execute([
            'lecteur_id' => $lecteurId,
            'stage_id' => $stageId
        ]);
        
        if ($result) {
            // Log de l'action
            error_log("Lecteur $lecteurId attribué au stage $stageId par " . $_SESSION['nom']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Lecteur attribué avec succès'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'attribution du lecteur'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Erreur lors de l'attribution du lecteur: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erreur serveur: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
}
?>
