<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Vous devez être connecté pour accéder à cette ressource.']);
    exit;
}

// Vérifier si l'ID est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID manquant']);
    exit;
}

$id = intval($_GET['id']);
$connexion = Connexion::getInstance()->getPDO();

try {
    $stmt = $connexion->prepare("SELECT * FROM droits_acces_finances WHERE id = :id AND type = 'Caisse'");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $droit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$droit) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Droit d\'accès non trouvé']);
        exit;
    }
    
    // Retourner les données du droit d'accès au format JSON
    header('Content-Type: application/json');
    echo json_encode($droit);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}