<?php
header('Content-Type: application/json');
session_start();

try {
    // Test 1: Vérifier si la session existe
    if (!isset($_SESSION['id'])) {
        echo json_encode(['error' => 'Session non définie', 'debug' => 'SESSION']);
        exit;
    }
    
    // Test 2: Vérifier si le fichier Connexion existe
    $connectionFile = __DIR__ . '/../config/Connexion.php';
    if (!file_exists($connectionFile)) {
        echo json_encode(['error' => 'Fichier Connexion.php introuvable', 'debug' => 'FILE', 'path' => $connectionFile]);
        exit;
    }
    
    // Test 3: Inclure le fichier de connexion
    require_once $connectionFile;
    
    // Test 4: Tester la connexion à la base de données
    $connexion = Connexion::getInstance()->getPDO();
    if (!$connexion) {
        echo json_encode(['error' => 'Connexion à la base de données échouée', 'debug' => 'DATABASE']);
        exit;
    }
    
    // Test 5: Requête simple
    $query = "SELECT COUNT(*) as count FROM sujets LIMIT 1";
    $stmt = $connexion->prepare($query);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'debug' => 'ALL_TESTS_PASSED',
        'session_id' => $_SESSION['id'],
        'role' => $_SESSION['idRole'] ?? 'non défini',
        'sujets_count' => $count
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'debug' => 'EXCEPTION']);
}
?>
