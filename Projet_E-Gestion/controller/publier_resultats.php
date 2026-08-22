<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier que la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON du corps de la requête
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['deliberationId'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$deliberationId = intval($data['deliberationId']);
$userId = $_SESSION['id'];

// Initialiser les classes
$deliberation = new Deliberation();
$agent = new Agent();

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$agentId = $agent->getAgentIdByUserId($userId);

// Récupérer les informations de la délibération
$delib = $deliberation->getDeliberationById($deliberationId);

if (!$delib) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Délibération non trouvée']);
    exit();
}

if (!$isAdmin) {
    // Vérifier si l'agent est président du jury (seul autorisé à publier)
    $isPresident = $delib['president_id'] == $agentId;
    
    if (!$isPresident) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Seul le président du jury peut publier les résultats']);
        exit();
    }
}

// Vérifier que la délibération est validée
if ($delib['statut'] !== 'Validée') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'La délibération doit être validée avant de pouvoir publier les résultats']);
    exit();
}

// Publier les résultats
$success = $deliberation->publierResultats($deliberationId,$userId);

if ($success) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Résultats publiés avec succès'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la publication des résultats'
    ]);
}
