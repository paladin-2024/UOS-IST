<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérifier si c'est une requête AJAX
if ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' !== 'XMLHttpRequest') {
    echo json_encode(['success' => false, 'message' => 'Requête non autorisée']);
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id_user = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
        $id_depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        $peut_consulter = isset($_POST['peut_consulter']) ? intval($_POST['peut_consulter']) : 0;
        $peut_modifier = isset($_POST['peut_modifier']) ? intval($_POST['peut_modifier']) : 0;
        $peut_valider = isset($_POST['peut_valider']) ? intval($_POST['peut_valider']) : 0;
        $id_user_creation = $_SESSION['id'];
        
        // Validation
        if ($id_user <= 0 || $id_depot <= 0) {
            throw new Exception("Utilisateur ou dépôt invalide.");
        }
        
        // Vérifier si une autorisation existe déjà
        $stmt = $db->prepare("SELECT id_autorisation FROM autorisation_depot 
                            WHERE id_user = :id_user AND id_depot = :id_depot");
        $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Mise à jour
            $stmt = $db->prepare("UPDATE autorisation_depot 
                                SET peut_consulter = :peut_consulter,
                                    peut_modifier = :peut_modifier,
                                    peut_valider = :peut_valider
                                WHERE id_autorisation = :id_autorisation");
            $stmt->bindParam(':peut_consulter', $peut_consulter, PDO::PARAM_INT);
            $stmt->bindParam(':peut_modifier', $peut_modifier, PDO::PARAM_INT);
            $stmt->bindParam(':peut_valider', $peut_valider, PDO::PARAM_INT);
            $stmt->bindParam(':id_autorisation', $existing['id_autorisation'], PDO::PARAM_INT);
        } else {
            // Insertion
            $stmt = $db->prepare("INSERT INTO autorisation_depot
                                (id_user, id_depot, peut_consulter, peut_modifier, 
                                peut_valider, id_user_creation)
                                VALUES
                                (:id_user, :id_depot, :peut_consulter, :peut_modifier,
                                :peut_valider, :id_user_creation)");
            $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
            $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
            $stmt->bindParam(':peut_consulter', $peut_consulter, PDO::PARAM_INT);
            $stmt->bindParam(':peut_modifier', $peut_modifier, PDO::PARAM_INT);
            $stmt->bindParam(':peut_valider', $peut_valider, PDO::PARAM_INT);
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
