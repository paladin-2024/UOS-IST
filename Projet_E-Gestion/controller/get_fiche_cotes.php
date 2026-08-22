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

// Initialiser les classes
$universite = new Universite();
$ecue = new Ecue();
$agent = new Agent();

// Récupérer les paramètres
$ecueId = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;

// Vérifier si les paramètres sont valides
if (!$ecueId || !$sessionId || !$anneeId || !$promotionId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit();
}

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);

if (!$isAdmin) {
    // Vérifier si l'utilisateur est membre d'un jury gérant cette promotion
    $hasAccess = $universite->canAgentAccessPromotion($agentId, $promotionId);

    if (!$hasAccess) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Vous n\'avez pas accès à cette promotion']);
        exit();
    }
}

$isSecondSession = $ecue->isDeuxiemeSession($sessionId);

// Récupérer les données de l'ECUE
$ecueInfo = $ecue->getEcueById($ecueId);

if (!$ecueInfo) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ECUE non trouvé']);
    exit();
}

// AJOUTEZ CETTE PARTIE: Récupérer les infos de la session
$sessionInfo = $ecue->getSessionById($sessionId);
if ($sessionInfo) {
    // Ajouter le nom de la session aux infos de l'ECUE
    $ecueInfo['designSession'] = $sessionInfo['designSession'];
}

if ($isSecondSession) {
    // En seconde session, ne montrer que les étudiants qui ont échoué à l'UE
    $etudiants = $ecue->getStudentsEligibleForSecondSessionCours($ecueId, $anneeId);
} else {
    // En première session, montrer tous les étudiants de la promotion
    $etudiants = $universite->getEtudiantsByPromotion($promotionId, $anneeId);
}

if ($isSecondSession) {
    // En seconde session, ne montrer que les étudiants qui ont échoué à l'UE
    $etudiants = $ecue->getStudentsEligibleForSecondSessionCours($ecueId, $anneeId);
} else {
    // En première session, montrer tous les étudiants de la promotion
    $etudiants = $universite->getEtudiantsByPromotion($promotionId, $anneeId);
}

// Récupérer les cotes existantes
$cotes = $universite->getCotesGrille($ecueId, $sessionId, $anneeId);

// En deuxième session, récupérer aussi les cotes de première session pour pré-remplir CC
$cotesPremiereSession = [];
if ($isSecondSession) {
    // Récupérer l'ID de la première session
    $db = Connexion::getInstance()->getPDO();
    $sessionQuery = $db->prepare("
        SELECT idsession FROM session 
        WHERE LOWER(\"designSession\") LIKE CONCAT('%', ?, '%')
        OR LOWER(\"designSession\") = ?
        LIMIT 1
    ");
    $sessionQuery->execute(['premi', 'premiere session']);
    $firstSession = $sessionQuery->fetch(PDO::FETCH_ASSOC);

    if ($firstSession) {
        $firstSessionId = $firstSession['idsession'];
        $cotesPremiereSession = $universite->getCotesGrille($ecueId, $firstSessionId, $anneeId);
    }
}

// Récupérer les pondérations depuis la table configuration_moyenne
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->prepare("
    SELECT ponderation_cc, ponderation_ex 
    FROM configuration_moyenne 
    WHERE \"idECUE\" = :ecueId 
    AND session_idsession = :sessionId 
    AND annee_acad_id = :anneeId
    LIMIT 1
");
$configQuery->execute([
    ':ecueId' => $ecueId,
    ':sessionId' => $sessionId,
    ':anneeId' => $anneeId
]);
$configMoyenne = $configQuery->fetch(PDO::FETCH_ASSOC);

// Utiliser les valeurs par défaut si aucune configuration n'est trouvée
// Récupérer les pondérations depuis la configuration par défaut si pas de config spécifique
require_once '../models/Universite.php';
$universite = new Universite();
$ponderationsDefaut = $universite->getPonderationsDefaut();

$ponderationCC = $configMoyenne && isset($configMoyenne['ponderation_cc']) ?
    (float)$configMoyenne['ponderation_cc'] : $ponderationsDefaut['ponderation_cc'];
$ponderationEX = $configMoyenne && isset($configMoyenne['ponderation_ex']) ?
    (float)$configMoyenne['ponderation_ex'] : $ponderationsDefaut['ponderation_ex'];

// Préparer la réponse
$response = [
    'ecue' => $ecueInfo,
    'etudiants' => $etudiants,
    'cotes' => $cotes,
    'cotesPremiereSession' => $cotesPremiereSession, // Ajouter les cotes de 1ère session
    'session' => $sessionId,
    'annee' => $anneeId,
    'isSecondSession' => $isSecondSession, // Signaler si c'est 2ème session
    'ponderations' => [
        'cc' => $ponderationCC,
        'ex' => $ponderationEX
    ]
];

// Envoyer la réponse
header('Content-Type: application/json');
echo json_encode($response);
