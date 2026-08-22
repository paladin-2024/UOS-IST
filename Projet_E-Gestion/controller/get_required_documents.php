<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si le cycle est fourni
if (!isset($_GET['cycle']) || empty($_GET['cycle'])) {
    echo json_encode(['success' => false, 'message' => 'Cycle non spécifié']);
    exit;
}

$cycle = $_GET['cycle'];
$pdo = Connexion::getInstance()->getPDO();

try {
    // Récupérer les documents obligatoires pour le cycle spécifié
    $stmt = $pdo->prepare("
        SELECT id, designation, description, est_obligatoire, delai_jours 
        FROM documents_obligatoires 
        WHERE cycle = :cycle OR cycle = 'Tous'
        ORDER BY est_obligatoire DESC, designation ASC
    ");
    
    $stmt->execute(['cycle' => $cycle]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
