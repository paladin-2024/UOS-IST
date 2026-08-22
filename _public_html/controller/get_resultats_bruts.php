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

// Récupérer les résultats bruts
$resultats = $deliberation->getResultatsBruts($deliberationId);

if (isset($resultats['error'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $resultats['error']]);
    exit();
}

// Formater les données pour la réponse
$response = [
    'success' => true,
    'deliberation' => $delib,
    'etudiants' => array_map(function($etudiant) {
        return [
            'matricule' => $etudiant['matricule'],
            'noms' => $etudiant['nom']
        ];
    }, $resultats['etudiants']),
    'ecues' => $resultats['ecues'],
    'cotes' => []
];

// Formater les cotes
foreach ($resultats['etudiants'] as $etudiant) {
    foreach ($etudiant['cotes'] as $ecueId => $cote) {
        $response['cotes'][] = [
            'matricule' => $etudiant['matricule'],
            'idECUE' => $ecueId,
            'CC' => $cote['CC'],
            'EX' => $cote['EX'],
            'MF' => $cote['MF']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
