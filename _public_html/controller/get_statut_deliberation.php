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

// Récupérer l'ID de la délibération
if (!isset($_GET['deliberation']) || empty($_GET['deliberation'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de délibération manquant']);
    exit();
}

$deliberationId = intval($_GET['deliberation']);
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

// Récupérer les étapes du processus
$etapes = $deliberation->getProcessusEtapes($deliberationId);

// Calculer la progression globale
$progressionTotale = 0;
$nbEtapes = count($etapes);

if ($nbEtapes > 0) {
    $sommeProgression = 0;
    foreach ($etapes as $etape) {
        $sommeProgression += $etape['progression'];
    }
    $progressionTotale = $sommeProgression / $nbEtapes;
}

// Déterminer l'étape actuelle
$etapeActuelle = null;
foreach ($etapes as $etape) {
    if ($etape['statut'] === 'En cours') {
        $etapeActuelle = $etape;
        break;
    }
}

// Si aucune étape n'est en cours, prendre la première étape non terminée
if (!$etapeActuelle) {
    foreach ($etapes as $etape) {
        if ($etape['statut'] !== 'Terminé') {
            $etapeActuelle = $etape;
            break;
        }
    }
}

// Formater la réponse
$response = [
    'success' => true,
    'deliberation' => $delib,
    'etapes' => $etapes,
    'progression_totale' => $progressionTotale,
    'etape_actuelle' => $etapeActuelle,
    'statut_global' => $delib['statut']
];

header('Content-Type: application/json');
echo json_encode($response);
