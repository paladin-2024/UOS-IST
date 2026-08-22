<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer les paramètres
$exercice_id = isset($_GET['exercice_id']) ? intval($_GET['exercice_id']) : 0;
$categorie_id = isset($_GET['categorie_id']) ? intval($_GET['categorie_id']) : 0;

if (!$exercice_id || !$categorie_id) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Requête pour récupérer les détails du budget
    $sql = "SELECT * FROM budget WHERE exercice_id = :exercice_id AND categorie_id = :categorie_id";
    
    $stmt = $connexion->prepare($sql);
    $stmt->bindParam(':exercice_id', $exercice_id);
    $stmt->bindParam(':categorie_id', $categorie_id);
    $stmt->execute();
    
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Renvoyer les données en JSON
    echo json_encode(['budget' => $budget]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}