<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non authentifié']);
    exit();
}

// Récupérer l'ID du frais
$frais_id = isset($_GET['frais_id']) ? intval($_GET['frais_id']) : 0;

if ($frais_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de frais invalide']);
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les tranches configurées pour ce frais
    $stmt = $connexion->prepare("
        SELECT * FROM tranches_paiement_config
        WHERE frais_id = :frais_id
        ORDER BY numero_tranche ASC
    ");
    $stmt->bindParam(':frais_id', $frais_id);
    $stmt->execute();
    
    $tranches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($tranches);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit();
}