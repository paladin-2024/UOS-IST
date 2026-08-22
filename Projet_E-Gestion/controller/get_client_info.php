<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit();
}

// Vérifier si l'ID du devis est fourni
if (!isset($_GET['devis_id']) || intval($_GET['devis_id']) <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de devis non valide']);
    exit();
}

$devis_id = intval($_GET['devis_id']);
$db = Connexion::getInstance()->getPDO();

try {
    // Récupérer les informations du devis et du client associé
    $query = "SELECT d.id_devis, d.numero_devis, d.date_devis, d.montant_ttc, d.validite,
                     c.id_client, c.nom_client, c.email, c.telephone
              FROM devis d
              JOIN client c ON d.id_client = c.id_client
              WHERE d.id_devis = :devis_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':devis_id', $devis_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Devis non trouvé']);
        exit();
    }
    
    // Renvoyer les informations au format JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
    exit();
}
