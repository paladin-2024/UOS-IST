<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

try {
    // Récupérer uniquement les produits actifs qui sont suivis en stock
    $query = "SELECT id_produit, code_produit, libelle_produit, id_unite_stockage, 
                     est_peremption_suivi, type_produit
              FROM produit 
              WHERE actif = 1 AND est_stock_suivi = 1
              ORDER BY libelle_produit";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retourner les résultats au format JSON
    header('Content-Type: application/json');
    echo json_encode($products);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
