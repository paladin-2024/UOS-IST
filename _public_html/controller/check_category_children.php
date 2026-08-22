<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de catégorie est fourni
if (!isset($_GET['categorie_id']) || empty($_GET['categorie_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de catégorie non spécifié']);
    exit;
}

$categorie_id = intval($_GET['categorie_id']);
$connexion = Connexion::getInstance()->getPDO();

try {
    // Vérifier si la catégorie a des enfants
    $stmt = $connexion->prepare("
        SELECT COUNT(*) AS has_children 
        FROM categories_budget 
        WHERE parent_id = :categorie_id
    ");
    $stmt->bindParam(':categorie_id', $categorie_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'has_children' => $result['has_children'] > 0,
        'count' => $result['has_children']
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}