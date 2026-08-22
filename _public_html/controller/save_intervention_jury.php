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

if (!$data || !isset($data['deliberationId']) || !isset($data['typeElement']) || 
    !isset($data['idElement']) || !isset($data['matricule']) || 
    !isset($data['noteOriginale']) || !isset($data['noteModifiee']) || !isset($data['motif'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$deliberationId = intval($data['deliberationId']);
$typeElement = $data['typeElement'];
$idElement = intval($data['idElement']);
$matricule = $data['matricule'];
$noteOriginale = floatval($data['noteOriginale']);
$noteModifiee = floatval($data['noteModifiee']);
$motif = $data['motif'];
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
    
    // Vérifier si l'agent est président ou secrétaire (seuls autorisés à faire des interventions)
    $isPresident = $delib['president_id'] == $agentId;
    $isSecretaire = $delib['secretaire_id'] == $agentId;
    
    if (!$isPresident && !$isSecretaire) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Seuls le président et le secrétaire peuvent effectuer des interventions']);
        exit();
    }
}

// Enregistrer l'intervention
$success = $deliberation->saveIntervention(
    $deliberationId,
    $typeElement,
    $idElement,
    $matricule,
    $noteOriginale,
    $noteModifiee,
    $motif,
    $agentId,
    $userId
);

if ($success) {
    // Mettre à jour la note selon le type d'élément
    switch ($typeElement) {
        case 'ECUE':
            // Mettre à jour la note de l'ECUE
            $success = $deliberation->updateEcueNote($idElement, $matricule, $noteModifiee, $delib['session_idsession'], $delib['annee_acad_id']);
            break;
            
        case 'UE':
            // Mettre à jour la moyenne de l'UE
            $success = $deliberation->updateUeNote($idElement, $matricule, $deliberationId, $noteModifiee);
            break;
            
        case 'Semestre':
            // Mettre à jour la moyenne du semestre
            $success = $deliberation->updateSemestreNote($idElement, $matricule, $deliberationId, $noteModifiee);
            break;
            
        case 'Annuel':
            // Mettre à jour la moyenne annuelle
            $success = $deliberation->updateMoyenneAnnuelle($matricule, $deliberationId, $noteModifiee);
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Intervention enregistrée avec succès' : 'Erreur lors de la mise à jour de la note'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'intervention']);
}
