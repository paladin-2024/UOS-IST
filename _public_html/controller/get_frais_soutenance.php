<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    require_once "./config/Connexion.php";

    $fraisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$fraisId) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit;
    }

    $db = Connexion::getInstance()->getPDO();

    $query = "SELECT * FROM frais_soutenance WHERE idfrais_soutenance = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $fraisId]);
    $frais = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$frais) {
        echo json_encode(['success' => false, 'message' => 'Frais non trouvé']);
        exit;
    }

    echo json_encode(['success' => true, 'frais' => $frais]);
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
