<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de l'année académique est fourni
if (!isset($_GET['annee_id']) || empty($_GET['annee_id'])) {
    echo json_encode(['error' => 'ID année académique manquant']);
    exit;
}

$anneeId = intval($_GET['annee_id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Requête pour obtenir les sections associées à l'année académique
    $query = "SELECT idsection, \"designationSection\" 
              FROM section 
              WHERE \"idAnnee\" = :annee_id 
              ORDER BY \"designationSection\"";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':annee_id', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($sections);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>