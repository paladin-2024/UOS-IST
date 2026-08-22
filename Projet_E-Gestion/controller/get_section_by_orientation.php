<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de l'orientation est fourni
if (!isset($_GET['orientation_id']) || empty($_GET['orientation_id'])) {
    echo json_encode(['error' => 'ID orientation manquant']);
    exit;
}

$orientationId = intval($_GET['orientation_id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Requête pour obtenir la section associée à l'orientation
    $query = "SELECT s.idsection, s.\"designationSection\" 
              FROM section s 
              INNER JOIN orientation o ON o.section_idsection = s.idsection 
              WHERE o.idorientation = :orientation_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':orientation_id', $orientationId, PDO::PARAM_INT);
    $stmt->execute();
    
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($section) {
        echo json_encode($section);
    } else {
        echo json_encode(['error' => 'Section non trouvée pour cette orientation']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>