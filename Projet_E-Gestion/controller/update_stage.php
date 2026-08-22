<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est administrateur
if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stageId = isset($_POST['stage_id']) ? intval($_POST['stage_id']) : 0;
    $lieuStage = isset($_POST['lieu_stage']) ? trim($_POST['lieu_stage']) : '';
    $idEncadreur = isset($_POST['idencadreur']) && !empty($_POST['idencadreur']) ? intval($_POST['idencadreur']) : null;
    $dateDebut = isset($_POST['date_debut']) ? $_POST['date_debut'] : '';
    $dateFin = isset($_POST['date_fin']) ? $_POST['date_fin'] : '';
    $coteEntreprise = isset($_POST['cote_entreprise']) && !empty($_POST['cote_entreprise']) ? floatval($_POST['cote_entreprise']) : null;
    
    // Validation
    if (!$stageId || !$lieuStage || !$dateDebut || !$dateFin) {
        echo json_encode([
            'success' => false,
            'message' => 'Données invalides ou manquantes'
        ]);
        exit;
    }
    
    // Validation des dates
    if (strtotime($dateFin) <= strtotime($dateDebut)) {
        echo json_encode([
            'success' => false,
            'message' => 'La date de fin doit être postérieure à la date de début'
        ]);
        exit;
    }
    
    // Validation de la cote
    if ($coteEntreprise !== null && ($coteEntreprise < 0 || $coteEntreprise > 20)) {
        echo json_encode([
            'success' => false,
            'message' => 'La cote doit être entre 0 et 20'
        ]);
        exit;
    }
    
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Mettre à jour le stage
        $updateQuery = "UPDATE stage_assignments 
                        SET lieu_stage = :lieu_stage,
                            idencadreur = :idencadreur,
                            date_debut = :date_debut,
                            date_fin = :date_fin,
                            cote_entreprise = :cote_entreprise
                        WHERE idstage = :stage_id";
        
        $stmt = $db->prepare($updateQuery);
        $result = $stmt->execute([
            'lieu_stage' => $lieuStage,
            'idencadreur' => $idEncadreur,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'cote_entreprise' => $coteEntreprise,
            'stage_id' => $stageId
        ]);
        
        if ($result) {
            error_log("Stage $stageId mis à jour par " . $_SESSION['nom']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Stage mis à jour avec succès'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du stage'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour du stage: " . $e->getMessage());
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
