<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Définir en-tête JSON dès le début
header('Content-Type: application/json; charset=UTF-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si les paramètres nécessaires sont spécifiés
if (!isset($_GET['annee_academique']) || empty($_GET['annee_academique']) || 
    !isset($_GET['section']) || empty($_GET['section']) ||
    !isset($_GET['promotion']) || empty($_GET['promotion'])) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$anneeAcademique = $_GET['annee_academique'];
$section = $_GET['section'];
$promotion = $_GET['promotion'];
$session = isset($_GET['session']) ? $_GET['session'] : '';

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Journaliser les paramètres pour débogage
    error_log("Recherche avec: annee=$anneeAcademique, section=$section, promotion=$promotion, session=$session");
    
    // Construire la requête de base
    $query = "SELECT p.*, 
                    (SELECT COUNT(*) FROM etudiants_palmares_archives e WHERE e.idpalmares = p.idpalmares) as nb_etudiants 
              FROM palmares_archives p 
              WHERE p.annee_academique = :annee_academique 
              AND p.section = :section 
              AND p.promotion = :promotion";
    
    $params = [
        ':annee_academique' => $anneeAcademique,
        ':section' => $section,
        ':promotion' => $promotion
    ];
    
    // Ajouter le critère de session si spécifié
    if (!empty($session)) {
        $query .= " AND p.session = :session";
        $params[':session'] = $session;
    }
    
    // Ajouter l'ordre de tri
    $query .= " ORDER BY p.date_creation DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $palmares = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Journaliser le nombre de résultats
    error_log("Nombre de résultats: " . count($palmares));
    
    echo json_encode($palmares);
    
} catch (PDOException $e) {
    error_log("Erreur SQL: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
    exit;
}
