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

if (!$data || !isset($data['bureauId']) || !isset($data['promotionId']) || !isset($data['sessionId']) || !isset($data['anneeId'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$bureauId = intval($data['bureauId']);
$promotionId = intval($data['promotionId']);
$sessionId = intval($data['sessionId']);
$anneeId = intval($data['anneeId']);
$userId = $_SESSION['id'];

// Initialiser les classes
$deliberation = new Deliberation();
$agent = new Agent();

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$agentId = $agent->getAgentIdByUserId($userId);

if (!$isAdmin) {
    // Vérifier si l'agent est membre du bureau de jury
    $isMember = $deliberation->isAgentJuryMember($agentId, $bureauId);
    
    if (!$isMember) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas accès à ce bureau de jury']);
        exit();
    }
}

// Vérifier si une délibération existe déjà pour ces paramètres
$existingDeliberation = $deliberation->getDeliberationExistante($bureauId, $promotionId, $sessionId, $anneeId);

if ($existingDeliberation) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Une délibération existe déjà pour ces paramètres',
        'deliberationId' => $existingDeliberation['iddeliberation']
    ]);
    exit();
}

// Créer une nouvelle délibération
$deliberationId = $deliberation->createDeliberation($bureauId, $promotionId, $sessionId, $anneeId, $userId);

if ($deliberationId) {
    // Initialiser les étapes du processus
    $deliberation->saveProcessusEtape($deliberationId, 'Initialisation', 'En attente', 'Délibération initiée', 0, $userId);
    $deliberation->saveProcessusEtape($deliberationId, 'Calcul ECUE', 'En attente', 'En attente', 0, $userId);
    $deliberation->saveProcessusEtape($deliberationId, 'Calcul UE', 'En attente', 'En attente', 0, $userId);
    $deliberation->saveProcessusEtape($deliberationId, 'Compensation intra-UE', 'En attente', 'En attente', 0, $userId);
    $deliberation->saveProcessusEtape($deliberationId, 'Compensation inter-UE', 'En attente', 'En attente', 0, $userId);
    $deliberation->saveProcessusEtape($deliberationId, 'Compensation inter-semestre', 'En attente', 'En attente', 0, $userId);
    $deliberation->saveProcessusEtape($deliberationId, 'Décisions jury', 'En attente', 'En attente', 0, $userId);
    $deliberation->saveProcessusEtape($deliberationId, 'Finalisation', 'En attente', 'En attente', 0, $userId);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Délibération initiée avec succès',
        'deliberationId' => $deliberationId
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'initialisation de la délibération']);
}
