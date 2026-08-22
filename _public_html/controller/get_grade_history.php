<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID manquant']);
    exit;
}

$gradeHistoryId = intval($_GET['id']);

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Préparer et exécuter la requête SQL
    $query = "SELECT * FROM historique_grade WHERE idhistorique_grade = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $gradeHistoryId, PDO::PARAM_INT);
    $stmt->execute();
    
    $gradeHistory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$gradeHistory) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Historique de grade non trouvé']);
        exit;
    }
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode([
        'id' => $gradeHistory['idhistorique_grade'],
        'idAgent' => $gradeHistory['idAgent'],
        'idgrade' => $gradeHistory['idgrade'],
        'date_promotion' => $gradeHistory['date_promotion'],
        'reference_decision' => $gradeHistory['reference_decision'],
        'reference_notification' => $gradeHistory['reference_notification']
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit;
}
