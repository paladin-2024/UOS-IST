<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['rdvId']) || !isset($input['statut'])) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }
    
    $rdvId = intval($input['rdvId']);
    $nouveauStatut = $input['statut'];
    $userId = $_SESSION['id'];
    
    // Vérifier que le statut est valide
    $statutsValides = ['planifie', 'confirme', 'reporte', 'annule', 'termine'];
    if (!in_array($nouveauStatut, $statutsValides)) {
        echo json_encode(['success' => false, 'message' => 'Statut invalide']);
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier que l'agent connecté est bien l'agent concerné par le rendez-vous
    $checkQuery = $db->prepare("
        SELECT rv.Agent_idAgent, a.idAgent 
        FROM rendez_vous rv
        INNER JOIN agent a ON rv.Agent_idAgent = a.idAgent
        INNER JOIN t_users u ON a.idAgent = u.idAgent
        WHERE rv.idRendez_vous = ? AND u.idUser = ?
    ");
    $checkQuery->execute([$rdvId, $userId]);
    
    if (!$checkQuery->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas autorisé à modifier ce rendez-vous']);
        exit;
    }
    
    // Mettre à jour le statut
    $updateQuery = $db->prepare("
        UPDATE rendez_vous 
        SET statut_rendez_vous = ?, 
            date_modification = NOW(), 
            modifie_par = ?
        WHERE idRendez_vous = ?
    ");
    
    $result = $updateQuery->execute([$nouveauStatut, $userId, $rdvId]);
    
    if ($result) {
        // Log de l'action (optionnel)
        $logQuery = $db->prepare("
            INSERT INTO journal_activites (user_type, user_id, type_activite, id_element, description, date_activite)
            VALUES ('agent', ?, 'rendez_vous_statut', ?, ?, NOW())
        ");
        $logQuery->execute([
            $userId, 
            $rdvId, 
            "Changement de statut vers: {$nouveauStatut}"
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
    
} catch (Exception $e) {
    error_log("Erreur updateStatutRendezVous: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>