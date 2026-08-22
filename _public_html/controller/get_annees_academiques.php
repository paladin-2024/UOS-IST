<?php
session_start();
include_once "../config/Connexion.php";

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer toutes les années académiques
    $query = "SELECT idannee_acad, designation, est_active 
              FROM annee_acad 
              ORDER BY designation DESC";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute();
    $annees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($annees);
    
} catch (Exception $e) {
    error_log("Erreur get_annees_academiques: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
