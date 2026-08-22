<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID du compte non spécifié']);
    exit();
}

$id = intval($_GET['id']);

try {
    // Initialiser la connexion
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations du compte
    $stmt = $db->prepare("SELECT * FROM comptes_bancaires WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compte) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Compte bancaire non trouvé']);
        exit();
    }
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($compte);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
    exit();
}