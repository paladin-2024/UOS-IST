<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Vérifier si l'utilisateur est administrateur ou président de jury
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];

$universite = new Universite();
$agent = new Agent();

$agentId = $agent->getAgentIdByUserId($userId);
$isJuryPresident = $universite->isJuryPresident($agentId);

if (!$isAdmin && !$isJuryPresident) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Récupérer les paramètres
$ecueId = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

if ($ecueId <= 0 || $sessionId <= 0 || $anneeId <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Récupérer le nombre de modifications pour chaque étudiant
$historiqueCount = $universite->getHistoriqueCountByEcue($ecueId, $sessionId, $anneeId);

// Envoyer la réponse
header('Content-Type: application/json');
echo json_encode($historiqueCount);
