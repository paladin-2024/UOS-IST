<?php
session_start();
header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Inclure les modèles
require_once __DIR__ . '/../config/Connexion.php';

try {
    $connexion = Connexion::getInstance()->getPDO();

    // Récupérer les données
    $soutenanceId = isset($_POST['soutenance_id']) ? intval($_POST['soutenance_id']) : null;
    $juryId = isset($_POST['jury_id']) ? intval($_POST['jury_id']) : null;

    // Validation
    if (!$soutenanceId || !$juryId) {
        echo json_encode([
            'success' => false,
            'message' => 'Données manquantes: soutenance_id et jury_id requis'
        ]);
        exit;
    }

    // Vérifier que la soutenance existe
    $querySoutenance = "SELECT idsoutenance FROM soutenance WHERE idsoutenance = :soutenanceId";
    $stmtSoutenance = $connexion->prepare($querySoutenance);
    $stmtSoutenance->execute(['soutenanceId' => $soutenanceId]);
    $soutenance = $stmtSoutenance->fetch(PDO::FETCH_ASSOC);

    if (!$soutenance) {
        echo json_encode([
            'success' => false,
            'message' => 'Soutenance non trouvée'
        ]);
        exit;
    }

    // Vérifier que le jury existe
    $queryJury = "SELECT idjury FROM jury WHERE idjury = :juryId";
    $stmtJury = $connexion->prepare($queryJury);
    $stmtJury->execute(['juryId' => $juryId]);
    $jury = $stmtJury->fetch(PDO::FETCH_ASSOC);

    if (!$jury) {
        echo json_encode([
            'success' => false,
            'message' => 'Jury non trouvé'
        ]);
        exit;
    }

    // Assigner le jury à la soutenance
    $queryUpdate = "UPDATE soutenance SET jury_id = :juryId WHERE idsoutenance = :soutenanceId";
    $stmtUpdate = $connexion->prepare($queryUpdate);
    $success = $stmtUpdate->execute([
        'juryId' => $juryId,
        'soutenanceId' => $soutenanceId
    ]);

    if ($success) {
        // Récupérer le nom du jury
        $queryJuryName = "SELECT designation FROM jury WHERE idjury = :juryId";
        $stmtJuryName = $connexion->prepare($queryJuryName);
        $stmtJuryName->execute(['juryId' => $juryId]);
        $juryName = $stmtJuryName->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => 'Le jury "' . htmlspecialchars($juryName) . '" a été assigné avec succès à la soutenance.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour de la soutenance'
        ]);
    }
} catch (Exception $e) {
    error_log("Erreur lors de l'assignation du jury: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
