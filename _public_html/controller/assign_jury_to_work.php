<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier les droits
if ($_SESSION['idRole'] != 1) { // Administrateur uniquement
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
    exit;
}

try {
    require_once dirname(__DIR__) . "/config/Connexion.php";
    
    $sujetId = isset($_POST['sujet_id']) ? intval($_POST['sujet_id']) : 0;
    $juryId = isset($_POST['jury_id']) ? intval($_POST['jury_id']) : 0;

    if (!$sujetId || !$juryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }

    $connexion = Connexion::getInstance()->getPDO();

    // Vérifier que le jury existe
    $stmt = $connexion->prepare("SELECT idjury FROM jury WHERE idjury = ? LIMIT 1");
    $stmt->execute([$juryId]);
    $jury = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jury) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Jury introuvable']);
        exit;
    }

    // Vérifier que le sujet existe
    $stmt = $connexion->prepare("SELECT idsujets FROM sujets WHERE idsujets = ? LIMIT 1");
    $stmt->execute([$sujetId]);
    $sujet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sujet) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sujet introuvable']);
        exit;
    }

    // Récupérer ou créer une soutenance pour ce sujet
    $stmt = $connexion->prepare("
        SELECT idsoutenance FROM soutenance 
        WHERE sujets_idsujets = ? 
        LIMIT 1
    ");
    $stmt->execute([$sujetId]);
    $soutenance = $stmt->fetch(PDO::FETCH_ASSOC);

    $soutenanceId = null;

    if ($soutenance) {
        // La soutenance existe déjà
        $soutenanceId = $soutenance['idsoutenance'];
    } else {
        // Créer une nouvelle soutenance
        // Récupérer l'année académique active
        $stmt = $connexion->prepare("SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1");
        $stmt->execute();
        $anneeResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$anneeResult) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aucune année académique active']);
            exit;
        }

        $anneeId = $anneeResult['idannee_acad'];

        // Créer la soutenance
        $stmt = $connexion->prepare("
            INSERT INTO soutenance (sujets_idsujets, annee_acad_idannee_acad, statut, jury_id, date_creation)
            VALUES (?, ?, 'Non programmée', ?, NOW())
        ");
        $stmt->execute([$sujetId, $anneeId, $juryId]);
        $soutenanceId = $connexion->lastInsertId();
    }

    // Mettre à jour l'assignation du jury
    if ($soutenanceId) {
        $stmt = $connexion->prepare("
            UPDATE soutenance 
            SET jury_id = ?
            WHERE idsoutenance = ?
        ");
        $stmt->execute([$juryId, $soutenanceId]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Le jury a été assigné au travail avec succès.',
            'soutenance_id' => (int)$soutenanceId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de la soutenance']);
    }

} catch (Exception $e) {
    error_log("Erreur assign_jury_to_work: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}
