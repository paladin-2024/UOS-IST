<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'année académique est spécifiée
if (!isset($_GET['annee_academique']) || empty($_GET['annee_academique'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Année académique non spécifiée']);
    exit;
}

$anneeAcademique = $_GET['annee_academique'];

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer les sections disponibles pour cette année académique
    $query = "SELECT DISTINCT section FROM palmares_archives 
              WHERE annee_academique = :annee_academique 
              ORDER BY section ASC";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':annee_academique', $anneeAcademique);
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($sections);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
}