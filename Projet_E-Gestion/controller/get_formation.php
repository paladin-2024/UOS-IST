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

$formationId = intval($_GET['id']);

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Préparer et exécuter la requête SQL
    $query = "SELECT * FROM formation_agent WHERE idformation = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $formationId, PDO::PARAM_INT);
    $stmt->execute();
    
    $formation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$formation) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Formation non trouvée']);
        exit;
    }
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode([
        'id' => $formation['idformation'],
        'niveau' => $formation['niveau'],
        'etablissement' => $formation['etablissement'],
        'filiere' => $formation['filiere'],
        'annee_obtention' => $formation['annee_obtention'],
        'diplome_fichier' => $formation['diplome_fichier']
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
