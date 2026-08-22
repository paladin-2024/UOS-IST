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
    $stageIds = isset($_POST['stage_ids']) ? $_POST['stage_ids'] : [];
    $lecteurId = isset($_POST['lecteur_id']) ? intval($_POST['lecteur_id']) : 0;
    $replaceExisting = isset($_POST['replace_existing']) ? true : false;
    
    // Validation
    if (empty($stageIds) || !$lecteurId) {
        echo json_encode([
            'success' => false,
            'message' => 'Données invalides ou manquantes'
        ]);
        exit;
    }
    
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Vérifier que le lecteur existe
        $checkLecteur = "SELECT noms FROM agent WHERE idAgent = :id AND type_agent = 'Enseignant'";
        $stmtCheck = $db->prepare($checkLecteur);
        $stmtCheck->execute(['id' => $lecteurId]);
        $lecteur = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$lecteur) {
            echo json_encode([
                'success' => false,
                'message' => 'Lecteur invalide'
            ]);
            exit;
        }
        
        $db->beginTransaction();
        
        $affectedCount = 0;
        $skippedCount = 0;
        $errors = [];
        
        foreach ($stageIds as $stageId) {
            $stageId = intval($stageId);
            
            // Vérifier que le stage existe et a un rapport
            $checkStage = "SELECT idlecteur, rapport_path FROM stage_assignments WHERE idstage = :id";
            $stmtStage = $db->prepare($checkStage);
            $stmtStage->execute(['id' => $stageId]);
            $stage = $stmtStage->fetch(PDO::FETCH_ASSOC);
            
            if (!$stage) {
                $errors[] = "Stage #$stageId introuvable";
                continue;
            }
            
            if (empty($stage['rapport_path'])) {
                $errors[] = "Stage #$stageId n'a pas de rapport déposé";
                continue;
            }
            
            // Si le stage a déjà un lecteur et que replace_existing est false, sauter
            if (!empty($stage['idlecteur']) && !$replaceExisting) {
                $skippedCount++;
                continue;
            }
            
            // Affecter le lecteur
            $updateQuery = "UPDATE stage_assignments 
                           SET idlecteur = :lecteur_id
                           WHERE idstage = :stage_id";
            
            $stmtUpdate = $db->prepare($updateQuery);
            $result = $stmtUpdate->execute([
                'lecteur_id' => $lecteurId,
                'stage_id' => $stageId
            ]);
            
            if ($result) {
                $affectedCount++;
            } else {
                $errors[] = "Erreur lors de l'affectation au stage #$stageId";
            }
        }
        
        $db->commit();
        
        // Construire le message de résultat
        $message = "Lecteur affecté à <strong>$affectedCount</strong> stage(s)";
        if ($skippedCount > 0) {
            $message .= "<br><small>$skippedCount stage(s) ignoré(s) (lecteur déjà attribué)</small>";
        }
        if (!empty($errors)) {
            $message .= "<br><small class='text-danger'>" . implode(', ', $errors) . "</small>";
        }
        
        // Log de l'action
        error_log("Affectation multiple de lecteur $lecteurId à $affectedCount stages par " . $_SESSION['nom']);
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'affected' => $affectedCount,
            'skipped' => $skippedCount,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Erreur lors de l'affectation multiple de lecteurs: " . $e->getMessage());
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
