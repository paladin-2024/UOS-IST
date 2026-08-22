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

if (!$data || !isset($data['deliberationId']) || !isset($data['matricule']) || 
    !isset($data['typeMention']) || !isset($data['commentaire'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$deliberationId = intval($data['deliberationId']);
$matricule = $data['matricule'];
$typeMention = $data['typeMention'];
$commentaire = $data['commentaire'];
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
    
    // Vérifier si l'agent est président ou secrétaire (seuls autorisés à ajouter des mentions)
    $isPresident = $delib['president_id'] == $agentId;
    $isSecretaire = $delib['secretaire_id'] == $agentId;
    
    if (!$isPresident && !$isSecretaire) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Seuls le président et le secrétaire peuvent ajouter des mentions spéciales']);
        exit();
    }
}

// Ajouter la mention spéciale
$success = $deliberation->ajouterMentionSpeciale(
    $typeMention,
    $matricule,
    $deliberationId,
    $commentaire,
    $userId
);

if ($success) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Mention spéciale ajoutée avec succès'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'ajout de la mention spéciale'
    ]);
}
