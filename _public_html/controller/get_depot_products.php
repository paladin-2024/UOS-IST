<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérifier si le paramètre depot_id est fourni
if (!isset($_GET['depot_id']) || empty($_GET['depot_id'])) {
    echo json_encode(['error' => 'ID du dépôt manquant']);
    exit();
}

$depot_id = intval($_GET['depot_id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Requête pour récupérer les produits disponibles dans un dépôt spécifique
    // Nous sélectionnons uniquement les produits qui ont un stock positif
    $query = "
        SELECT DISTINCT p.id_produit, p.code_produit, p.libelle_produit, 
               p.id_categorie, p.famille,
               SUM(l.quantite_disponible) as stock_total
        FROM produit p
        INNER JOIN lot_produit l ON p.id_produit = l.id_produit
        WHERE l.quantite_disponible > 0
        AND EXISTS (
            SELECT 1 FROM detail_entree_stock de
            INNER JOIN entree_stock e ON de.id_entree = e.id_entree
            WHERE de.id_detail_entree = l.id_detail_entree
            AND e.id_depot = :depot_id
            AND e.etat = 'Validé'
        )
        AND p.actif = 1
        GROUP BY p.id_produit, p.code_produit, p.libelle_produit, p.id_categorie, p.famille
        ORDER BY p.libelle_produit
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':depot_id', $depot_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
