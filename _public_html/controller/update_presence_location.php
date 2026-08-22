<?php
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier que la méthode est bien POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer et valider les données
$idPresence = isset($_POST['idPresence']) ? intval($_POST['idPresence']) : 0;
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

if ($idPresence <= 0 || $latitude === null || $longitude === null) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$db = Connexion::getInstance()->getPDO();

try {
    // Mettre à jour la présence avec les coordonnées GPS
    $query = "UPDATE presence_labo 
              SET latitude = :latitude, longitude = :longitude 
              WHERE idpresence_labo = :idPresence";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(':latitude', $latitude);
    $stmt->bindParam(':longitude', $longitude);
    $stmt->bindParam(':idPresence', $idPresence);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Position enregistrée']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la position']);
}
