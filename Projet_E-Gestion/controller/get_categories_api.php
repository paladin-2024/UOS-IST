<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer l'ID du dépôt si présent pour filtrer les catégories des produits disponibles
    $depotId = isset($_GET['depot_id']) ? intval($_GET['depot_id']) : 0;
    
    if ($depotId > 0) {
        // Si un dépôt est spécifié, récupérer uniquement les catégories des produits qui ont du stock dans ce dépôt
        $query = "
            SELECT DISTINCT p.id_categorie, cat.libelle_categorie
            FROM produit p
            INNER JOIN categorie_produit cat ON p.id_categorie = cat.id_categorie
            INNER JOIN lot_produit lp ON p.id_produit = lp.id_produit
            INNER JOIN detail_entree_stock de ON lp.id_detail_entree = de.id_detail_entree
            INNER JOIN entree_stock e ON de.id_entree = e.id_entree
            WHERE e.id_depot = :depot_id
            AND lp.quantite_disponible > 0
            AND p.actif = 1
            AND cat.actif = 1
            AND e.etat = 'Validé'
            ORDER BY cat.libelle_categorie
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':depot_id', $depotId, PDO::PARAM_INT);
    } else {
        // Sinon, récupérer toutes les catégories actives
        $query = "
            SELECT id_categorie, libelle_categorie
            FROM categorie_produit
            WHERE actif = 1
            ORDER BY libelle_categorie
        ";
        
        $stmt = $db->prepare($query);
    }
    
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retourner les catégories au format JSON
    header('Content-Type: application/json');
    echo json_encode($categories);
    
} catch (Exception $e) {
    // Retourner une erreur au format JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
