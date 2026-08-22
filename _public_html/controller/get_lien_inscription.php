<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    $stmt = $connexion->prepare("
        SELECT * FROM liens_inscription_externe 
        WHERE id = ?
    ");
    $stmt->execute([intval($_GET['id'])]);
    $lien = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lien) {
        echo json_encode(['success' => false, 'message' => 'Lien non trouvé']);
        exit();
    }
    
    echo json_encode(['success' => true, 'lien' => $lien]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>