<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/Connexion.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier que l'ID du recours est présent
if (!isset($_GET['id_recours']) || empty($_GET['id_recours'])) {
    echo json_encode(['error' => 'ID du recours manquant']);
    exit;
}

$id_recours = intval($_GET['id_recours']);

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations du recours
    $query = "SELECT r.id_ecue, r.id_session, r.matricule, r.id_annee_acad 
              FROM recours r 
              WHERE r.id_recours = :id_recours";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id_recours', $id_recours);
    $stmt->execute();
    
    $recours_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recours_info) {
        echo json_encode(['error' => 'Recours non trouvé']);
        exit;
    }
    
    // Récupérer les notes actuelles
    $query_notes = "SELECT CG.CC, CG.EX, CG.MF
                    FROM cotes_grille CG
                    WHERE CG.\"ECUE_idECUE\" = :id_ecue
                    AND CG.session_idsession = :id_session
                    AND CG.matricule = :matricule
                    AND CG.annee_acad_id = :id_annee_acad";
    
    $stmt_notes = $conn->prepare($query_notes);
    $stmt_notes->bindParam(':id_ecue', $recours_info['id_ecue']);
    $stmt_notes->bindParam(':id_session', $recours_info['id_session']);
    $stmt_notes->bindParam(':matricule', $recours_info['matricule']);
    $stmt_notes->bindParam(':id_annee_acad', $recours_info['id_annee_acad']);
    $stmt_notes->execute();
    
    $notes = $stmt_notes->fetch(PDO::FETCH_ASSOC);
    
    // Répondre avec les notes (même si aucune n'a été trouvée)
    echo json_encode([
        'success' => true,
        'notes' => $notes ?: [
            'CC' => null,
            'EX' => null,
            'MF' => null
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
