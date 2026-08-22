<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    require_once "./config/Connexion.php";

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit;
    }

    $db = Connexion::getInstance()->getPDO();

    $query = "DELETE FROM frais_soutenance WHERE idfrais_soutenance = :id";
    $stmt = $db->prepare($query);
    $result = $stmt->execute(['id' => $id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Le frais a été supprimé']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
    }
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
