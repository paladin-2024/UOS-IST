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

// Vérifier que la requête est de type GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les paramètres
if (!isset($_GET['deliberation']) || empty($_GET['deliberation']) || !isset($_GET['matricule']) || empty($_GET['matricule'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit();
}

$deliberationId = intval($_GET['deliberation']);
$matricule = $_GET['matricule'];
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
    // Vérifier si l'agent est membre du bureau de jury
    $isMember = $deliberation->isAgentJuryMember($agentId, $delib['idbureau']);
    
    if (!$isMember) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas accès à cette délibération']);
        exit();
    }
}

// Récupérer les éléments disponibles pour intervention
$elements = $deliberation->getElementsIntervention($deliberationId, $matricule);

if (isset($elements['error'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $elements['error']]);
    exit();
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'elements' => $elements
]);
