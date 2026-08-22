<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit();
}

// Vérifier les droits d'accès
$universite = new Universite();
$agent = new Agent();
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$isJuryPresident = $universite->isJuryPresident($agentId);
$isJuryMember = false;

// Récupérer les bureaux de jury où l'agent est membre
if ($agentId) {
    $juryBureaux = $universite->getJuryBureauxByAgent($agentId);
    $isJuryMember = !empty($juryBureaux);
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryMember) {
    echo json_encode(['error' => 'Accès refusé']);
    exit();
}

// Récupérer les paramètres
$deliberationId = isset($_GET['deliberation_id']) ? intval($_GET['deliberation_id']) : 0;
$matricule = isset($_GET['matricule']) ? trim($_GET['matricule']) : '';

if (!$deliberationId || empty($matricule)) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit();
}

// Créer une instance de la classe Deliberation
$deliberation = new Deliberation();

// Récupérer les informations de la délibération
$deliberationInfo = $deliberation->getDeliberationInfo($deliberationId);
if (!$deliberationInfo) {
    echo json_encode(['error' => 'Délibération introuvable']);
    exit();
}

// Vérifier si l'utilisateur a accès à cette délibération
if (!$isAdmin) {
    $bureauId = $deliberationInfo['idbureau'];
    $hasAccess = false;
    
    foreach ($juryBureaux as $bureau) {
        if ($bureau['idbureau'] == $bureauId) {
            $hasAccess = true;
            break;
        }
    }
    
    if (!$hasAccess) {
        echo json_encode(['error' => 'Vous n\'avez pas accès à cette délibération']);
        exit();
    }
}

// Récupérer les détails de l'étudiant
try {

    // Récupérer les informations de session et d'année académique
    $sessionId = $deliberationInfo['session_idsession'];
    $anneeId = $deliberationInfo['annee_acad_idannee_acad'];
    $promotionId = $deliberationInfo['idpromotion'];
    
    // Informations de base de l'étudiant
    $etudiant = $universite->getEtudiantByMatricule($matricule,$anneeId);
    if (!$etudiant) {
        echo json_encode(['error' => 'Étudiant introuvable']);
        exit();
    }
    
    
    
    // Récupérer les moyennes de semestre
    $moyennesSemestre = $deliberation->getStudentSemesterAverages($matricule, $sessionId, $anneeId);
    
    // Récupérer la moyenne annuelle
    $moyenneAnnuelle = $deliberation->getStudentAnnualAverage($matricule, $promotionId, $sessionId, $anneeId);
    
    // Récupérer les moyennes d'UE
    $moyennesUE = $deliberation->getStudentUEAverages($matricule, $sessionId, $anneeId);
    
    // Récupérer le résultat final
    $resultatFinal = $deliberation->getStudentFinalResult($matricule, $deliberationId);
    
    // Construire la réponse
    $response = [
        'etudiant' => $etudiant,
        'moyennes_semestre' => $moyennesSemestre,
        'moyenne_annuelle' => $moyenneAnnuelle,
        'moyennes_ue' => $moyennesUE,
        'resultat_final' => $resultatFinal
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des détails: ' . $e->getMessage()]);
}
