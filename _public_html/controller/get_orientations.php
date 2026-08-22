<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de la section est fourni
if (!isset($_GET['section_id']) || empty($_GET['section_id'])) {
    echo json_encode(['error' => 'ID section manquant']);
    exit;
}

$sectionId = intval($_GET['section_id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Requête pour obtenir les orientations associées à la section
    $query = "SELECT idorientation, \"designationOrientation\" 
              FROM orientation 
              WHERE section_idsection = :section_id 
              ORDER BY \"designationOrientation\"";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':section_id', $sectionId, PDO::PARAM_INT);
    $stmt->execute();
    
    $orientations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($orientations);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>