<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'ID de l'évaluation est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'ID d\'évaluation non spécifié']);
    exit;
}

$evaluationId = intval($_GET['id']);

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

try {
    // Requête pour récupérer les détails de l'évaluation
    $sql = "SELECT e.*, t.\"designationT\", t.categorie
            FROM evaluations e
            LEFT JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
            WHERE e.idevaluation = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$evaluationId]);
    
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($evaluation) {
        // Retourner les détails en JSON
        echo json_encode($evaluation);
    } else {
        echo json_encode(['error' => 'Évaluation non trouvée']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>
