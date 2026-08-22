<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Récupérer les paramètres
$categoryId = $_POST['category_id'] ?? 'all';
$depotId = $_POST['depot_id'] ?? 'all';

// Se connecter à la base de données
$db = Connexion::getInstance()->getPDO();

// Requête de base
$sql = "SELECT DISTINCT p.id_produit, p.code_produit, p.libelle_produit 
        FROM produit p ";

// Jointures conditionnelles si filtrage par dépôt
if ($depotId !== 'all') {
    $sql .= "INNER JOIN lot_produit lp ON p.id_produit = lp.id_produit
             INNER JOIN detail_entree_stock des ON lp.id_detail_entree = des.id_detail_entree
             INNER JOIN entree_stock es ON des.id_entree = es.id_entree ";
}

$sql .= "WHERE p.actif = 1 ";

// Conditions supplémentaires
if ($categoryId !== 'all') {
    $sql .= "AND p.id_categorie = :categoryId ";
}

if ($depotId !== 'all') {
    $sql .= "AND es.id_depot = :depotId AND lp.quantite_disponible > 0 ";
}

// Tri et limite
$sql .= "ORDER BY p.code_produit LIMIT 200";

try {
    $stmt = $db->prepare($sql);
    
    // Binder les paramètres
    if ($categoryId !== 'all') {
        $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
    }
    
    if ($depotId !== 'all') {
        $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($products);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>
