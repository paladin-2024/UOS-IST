<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si l'ID du produit est fourni
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de produit invalide']);
    exit();
}

try {
    // Initialiser la connexion
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du produit
    $query = "SELECT p.*, u.symbole_unite 
              FROM produit p 
              LEFT JOIN unite_mesure u ON p.id_unite_vente = u.id_unite 
              WHERE p.id_produit = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        exit();
    }
    
    // Retourner les détails du produit
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'product' => $product]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
