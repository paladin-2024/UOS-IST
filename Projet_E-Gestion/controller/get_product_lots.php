<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérifier si les paramètres requis sont fournis
if (!isset($_GET['depot_id']) || empty($_GET['depot_id']) || 
    !isset($_GET['product_id']) || empty($_GET['product_id'])) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit();
}

$depot_id = intval($_GET['depot_id']);
$product_id = intval($_GET['product_id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Requête pour récupérer les lots disponibles pour un produit dans un dépôt
    // Ajout du calcul des jours avant expiration
    $query = "SELECT l.id_lot, l.numero_lot, l.quantite_disponible,                     l.prix_unitaire_vente, l.date_peremption,
                     CASE 
                        WHEN l.date_peremption IS NULL THEN NULL
                        ELSE DATEDIFF(l.date_peremption, CURDATE())
                     END AS jours_avant_expiration,
                     CASE
                        WHEN l.date_peremption IS NULL THEN 'normal'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 15 THEN 'critique'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'attention'
                        ELSE 'normal'
                     END AS statut_expiration
              FROM lot_produit l
              JOIN detail_entree_stock d ON l.id_detail_entree = d.id_detail_entree
              JOIN entree_stock e ON d.id_entree = e.id_entree
              WHERE e.id_depot = :depot_id
              AND l.id_produit = :product_id
              AND e.etat = 'Validé'
              AND l.quantite_disponible > 0
              ORDER BY l.date_peremption IS NULL, l.date_peremption, l.numero_lot";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':depot_id', $depot_id, PDO::PARAM_INT);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($lots);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

