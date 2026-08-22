<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si les paramètres nécessaires sont spécifiés
if (!isset($_GET['annee_academique']) || empty($_GET['annee_academique']) || 
    !isset($_GET['section']) || empty($_GET['section']) ||
    !isset($_GET['promotion']) || empty($_GET['promotion'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$anneeAcademique = $_GET['annee_academique'];
$section = $_GET['section'];
$promotion = $_GET['promotion'];

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer les sessions disponibles pour cette année, section et promotion
    $query = "SELECT DISTINCT session FROM palmares_archives 
              WHERE annee_academique = :annee_academique 
              AND section = :section
              AND promotion = :promotion
              ORDER BY session ASC";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':annee_academique', $anneeAcademique);
    $stmt->bindParam(':section', $section);
    $stmt->bindParam(':promotion', $promotion);
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($sessions);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
}