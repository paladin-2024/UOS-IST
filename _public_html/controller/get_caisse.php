<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier que l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID non fourni']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    $id = intval($_GET['id']);
    
    $stmt = $connexion->prepare("SELECT * FROM caisses WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$caisse) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Caisse non trouvée']);
        exit;
    }
    
    // Renvoi des données
    header('Content-Type: application/json');
    echo json_encode($caisse);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}