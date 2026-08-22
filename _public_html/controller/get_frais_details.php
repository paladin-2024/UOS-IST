<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non authentifié']);
    exit();
}

// Récupérer l'ID du frais
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de frais invalide']);
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du frais
    $stmt = $connexion->prepare("
        SELECT f.*, cf.designation AS categorie_nom, aa.designation AS annee_academique
        FROM frais f
        LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
        LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        WHERE f.id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $frais = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$frais) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Frais non trouvé']);
        exit();
    }
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($frais);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit();
}
