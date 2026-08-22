<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier que la méthode est bien GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer le terme de recherche
$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($term) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$db = Connexion::getInstance()->getPDO();

try {
    // Rechercher les étudiants par nom ou matricule
    $query = "SELECT idetudiant, noms, matricule
              FROM etudiant
              WHERE (noms LIKE :term OR matricule LIKE :term)
              ORDER BY noms ASC
              LIMIT 20";
              
    $stmt = $db->prepare($query);
    $searchTerm = '%' . $term . '%';
    $stmt->bindParam(':term', $searchTerm);
    $stmt->execute();
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($etudiants);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
