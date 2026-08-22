<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérifier si l'ID du dépôt est fourni
if (!isset($_GET['depot_id']) || empty($_GET['depot_id'])) {
    echo json_encode(['error' => 'ID de dépôt manquant']);
    exit();
}

$depot_id = intval($_GET['depot_id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Requête pour récupérer les produits qui ont du stock dans le dépôt spécifié
    $query = "SELECT DISTINCT p.id_produit, p.code_produit, p.libelle_produit, 
                     u.symbole_unite, SUM(l.quantite_disponible) as stock_total
              FROM produit p
              JOIN lot_produit l ON p.id_produit = l.id_produit
              JOIN detail_entree_stock d ON l.id_detail_entree = d.id_detail_entree
              JOIN entree_stock e ON d.id_entree = e.id_entree
              JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
              WHERE e.id_depot = :depot_id
              AND e.etat = 'Validé'
              AND l.quantite_disponible > 0
              AND p.actif = 1
              GROUP BY p.id_produit
              ORDER BY p.libelle_produit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':depot_id', $depot_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
