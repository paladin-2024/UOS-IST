<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID du palmarès est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID du palmarès manquant']);
    exit;
}

$idPalmares = intval($_GET['id']);
$highlightedStudent = isset($_GET['student_id']) ? $_GET['student_id'] : null;

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du palmarès
    $queryPalmares = "SELECT * FROM palmares_archives WHERE idpalmares = :id";
    $stmt = $pdo->prepare($queryPalmares);
    $stmt->bindParam(':id', $idPalmares, PDO::PARAM_INT);
    $stmt->execute();
    $palmares = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$palmares) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Palmarès non trouvé']);
        exit;
    }
    
    // Récupérer les étudiants du palmarès
    $queryEtudiants = "SELECT * FROM etudiants_palmares_archives 
                      WHERE idpalmares = :idpalmares 
                      ORDER BY pourcentage DESC";
    $stmt = $pdo->prepare($queryEtudiants);
    $stmt->bindParam(':idpalmares', $idPalmares, PDO::PARAM_INT);
    $stmt->execute();
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'palmares' => $palmares,
        'etudiants' => $etudiants,
        'highlighted_student' => $highlightedStudent
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
}
