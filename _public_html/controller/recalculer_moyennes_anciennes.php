<?php
session_start();
header('Content-Type: application/json');

// Augmenter les limites pour les gros calculs
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '512M');

// Vérifier les droits d'accès
if (!isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

try {
    // Vérifier les données requises
    if (!isset($_POST['import_id']) || empty($_POST['import_id'])) {
        throw new Exception('ID d\'import requis.');
    }
    
    $importId = intval($_POST['import_id']);
    
    if ($importId <= 0) {
        throw new Exception('ID d\'import invalide.');
    }
    
    require_once dirname(__DIR__) . '/models/GrilleAncienne.php';
    require_once dirname(__DIR__) . '/config/Connexion.php';
    
    $grilleAncienne = new GrilleAncienne();
    
    // Vérifier que l'import existe
    $importInfo = $grilleAncienne->getImportById($importId);
    if (!$importInfo) {
        throw new Exception('Import non trouvé.');
    }
    
    error_log("=== RECALCUL MOYENNES ANCIENNES ===");
    error_log("Import ID: $importId");
    error_log("Promotion: " . $importInfo['promotion']);
    
    // Supprimer les anciens résultats pour recalculer
    $db = Connexion::getInstance()->getPDO();
    $stmt = $db->prepare("DELETE FROM grilles_anciennes_resultats WHERE import_id = ?");
    $stmt->execute([$importId]);
    error_log("Anciens résultats supprimés");
    
    // Recalculer toutes les moyennes avec la logique stricte
    $grilleAncienne->calculerMoyennes($importId);
    error_log("Nouvelles moyennes calculées");
    
    // Récupérer les statistiques finales
    $etudiants = $grilleAncienne->getEtudiantsByImport($importId);
    $admis = 0;
    $ajournes = 0;
    $rattrapage = 0;
    
    foreach ($etudiants as $etudiant) {
        if (isset($etudiant['moyenne_generale']) && $etudiant['moyenne_generale'] !== null) {
            $moyenne = floatval($etudiant['moyenne_generale']);
            if ($moyenne >= 10) {
                $admis++;
            } else {
                $rattrapage++;
            }
        } else {
            $ajournes++;
        }
    }
    
    $total = count($etudiants);
    
    echo json_encode([
        'success' => true,
        'message' => "Moyennes recalculées avec succès pour {$importInfo['promotion']}",
        'statistiques' => [
            'total_etudiants' => $total,
            'admis' => $admis,
            'rattrapage' => $rattrapage,
            'ajournes' => $ajournes
        ]
    ]);
    
} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log("Erreur recalcul moyennes anciennes: " . json_encode($errorDetails));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $errorDetails // Temporaire pour diagnostic
    ]);
}
?>
