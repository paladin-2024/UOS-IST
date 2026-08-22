<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer l'ID de la catégorie
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['error' => 'ID de catégorie manquant']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Requête pour récupérer les détails de la catégorie
    $sql = "SELECT * FROM categories_budget WHERE id = :id";
    
    $stmt = $connexion->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $categorie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$categorie) {
        echo json_encode(['error' => 'Catégorie non trouvée']);
        exit;
    }
    
    // Renvoyer les données en JSON
    echo json_encode($categorie);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}