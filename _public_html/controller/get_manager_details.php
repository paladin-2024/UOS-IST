<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID manquant']);
    exit;
}

$id = intval($_GET['id']);

try {
    $conn = Connexion::getInstance()->getPDO();
    
    $query = "SELECT * FROM responsable_section WHERE idresponsable_section = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $manager = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($manager) {
        echo json_encode($manager);
    } else {
        echo json_encode(['error' => 'Manager non trouvé']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>