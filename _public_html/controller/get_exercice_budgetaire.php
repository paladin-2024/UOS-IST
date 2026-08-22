<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer l'ID de l'exercice
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['error' => 'ID non fourni']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les données de l'exercice
    $stmt = $connexion->prepare("SELECT * FROM exercices_budgetaires WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exercice) {
        echo json_encode(['error' => 'Exercice budgétaire non trouvé']);
        exit;
    }
    
    // Renvoyer les données en JSON
    echo json_encode($exercice);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}