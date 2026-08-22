<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérifier si l'utilisateur est administrateur ou président de jury
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];

$universite = new Universite();
$ecue = new Ecue();
$agent = new Agent();

$agentId = $agent->getAgentIdByUserId($userId);
$isJuryPresident = $universite->isJuryPresident($agentId);

if (!$isAdmin && !$isJuryPresident) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Vous n\'avez pas les droits pour accéder à cet historique']);
    exit();
}

// Récupérer les paramètres
$ecueId = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : '';

if ($ecueId <= 0 || $sessionId <= 0 || $anneeId <= 0 || empty($matricule)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit();
}

// Récupérer les informations de l'ECUE et de la session
$ecueInfo = $ecue->getEcueById($ecueId);
$sessionInfo = $universite->getSessionById($sessionId);

// Log pour le débogage
error_log("Récupération de l'historique pour ECUE=$ecueId, Session=$sessionId, Annee=$anneeId, Matricule=$matricule");
error_log("Session info: " . ($sessionInfo ? json_encode($sessionInfo) : "null"));

// Récupérer l'historique des modifications
$historique = $universite->getHistoriqueCotes($ecueId, $sessionId, $anneeId, $matricule);

// Log pour le débogage
error_log("Nombre d'enregistrements d'historique trouvés: " . count($historique));



// Préparer la réponse
$response = [
    'ecue' => $ecueInfo,
    'session' => $sessionInfo,
    'historique' => $historique,
    'debug' => [
        'ecueId' => $ecueId,
        'sessionId' => $sessionId,
        'anneeId' => $anneeId,
        'matricule' => $matricule,
        'recordCount' => $count ?? 0
    ]
];

// Envoyer la réponse
header('Content-Type: application/json');
echo json_encode($response);
