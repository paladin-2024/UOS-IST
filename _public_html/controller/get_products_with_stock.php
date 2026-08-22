<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Récupérer l'ID du dépôt
$depotId = isset($_GET['depot_id']) ? intval($_GET['depot_id']) : 0;

if ($depotId <= 0) {
    echo json_encode(['error' => 'ID de dépôt invalide']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Requête pour obtenir tous les produits ayant du stock dans ce dépôt
    $query = "
        SELECT DISTINCT p.id_produit, p.code_produit, p.libelle_produit 
        FROM produit p
        INNER JOIN lot_produit lp ON p.id_produit = lp.id_produit
        INNER JOIN detail_entree_stock de ON lp.id_detail_entree = de.id_detail_entree
        INNER JOIN entree_stock e ON de.id_entree = e.id_entree
        WHERE e.id_depot = :depot_id
        AND lp.quantite_disponible > 0
        AND p.actif = 1
        AND e.etat = 'Validé'
        ORDER BY p.libelle_produit
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':depot_id', $depotId, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retourner les produits au format JSON
    header('Content-Type: application/json');
    echo json_encode($products);
    
} catch (Exception $e) {
    // Retourner une erreur au format JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
