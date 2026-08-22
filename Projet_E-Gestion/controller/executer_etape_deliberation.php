<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Universite.php';

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

if (!$data || !isset($data['deliberationId']) || !isset($data['etape'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$deliberationId = intval($data['deliberationId']);
$etape = $data['etape'];
$etapeIndex = isset($data['etapeIndex']) ? intval($data['etapeIndex']) : 0;
$totalEtapes = isset($data['totalEtapes']) ? intval($data['totalEtapes']) : 1;
$userId = $_SESSION['id'];

// Initialiser les classes
$deliberation = new Deliberation();
$agent = new Agent();
$universite = new Universite();

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

// Récupérer la configuration de délibération
$configDeliberation = $universite->getDeliberationConfig($delib['idbureau'], $delib['session_idsession'], $delib['annee_acad_id']);

// Mettre à jour le statut de l'étape
$deliberation->saveProcessusEtape($deliberationId, $etape, 'En cours', 'Exécution en cours...', ($etapeIndex * 100) / $totalEtapes, $userId);

// Exécuter l'étape en fonction de son type
$success = false;
$message = '';

try {
    switch ($etape) {
        case 'initialisation':
            // Initialisation du processus
            $success = true;
            $message = 'Initialisation terminée';
            break;
            
        case 'calcul_ecue':
            // Calcul des moyennes ECUE (déjà fait dans les cotes_grille)
            $success = true;
            $message = 'Calcul des moyennes ECUE terminé';
            break;
            
        case 'calcul_ue':
            // Calcul des moyennes UE
            $success = $deliberation->calculerMoyennesUE($deliberationId, $configDeliberation);
            $message = $success ? 'Calcul des moyennes UE terminé' : 'Erreur lors du calcul des moyennes UE';
            break;
            
        case 'compensation_intra_ue':
            // Compensation intra-UE
            if ($configDeliberation['compensation_intra_ue']) {
                $success = $deliberation->appliquerCompensationIntraUE($deliberationId, $configDeliberation);
                $message = $success ? 'Compensation intra-UE terminée' : 'Erreur lors de la compensation intra-UE';
            } else {
                $success = true;
                $message = 'Compensation intra-UE désactivée dans la configuration';
            }
            break;
            
        case 'compensation_inter_ue':
            // Compensation inter-UE
            if ($configDeliberation['compensation_inter_ue']) {
                $success = $deliberation->appliquerCompensationInterUE($deliberationId, $configDeliberation);
                $message = $success ? 'Compensation inter-UE terminée' : 'Erreur lors de la compensation inter-UE';
            } else {
                $success = true;
                $message = 'Compensation inter-UE désactivée dans la configuration';
            }
            break;
            
        case 'compensation_inter_semestre':
            // Compensation inter-semestre
            if ($configDeliberation['compensation_inter_semestre']) {
                $success = $deliberation->appliquerCompensationInterUE($deliberationId, $configDeliberation);
                $message = $success ? 'Compensation inter-semestre terminée' : 'Erreur lors de la compensation inter-semestre';
            } else {
                $success = true;
                $message = 'Compensation inter-semestre désactivée dans la configuration';
            }
            break;
            
            case 'decisions_jury':
                // Application des décisions du jury (interventions manuelles déjà enregistrées)
                $success = true;
                $message = 'Décisions du jury appliquées';
                break;
                
            case 'finalisation':
                // Finalisation des résultats
                $success = $deliberation->finaliserResultats($deliberationId, $userId);
                $message = $success ? 'Finalisation des résultats terminée' : 'Erreur lors de la finalisation des résultats';
                break;
                
            case 'validation':
                // Validation de la délibération
                $success = $deliberation->validerDeliberation($deliberationId);
                $message = $success ? 'Délibération validée' : 'Erreur lors de la validation de la délibération';
                break;
                
            case 'publication':
                // Publication des résultats
                $success = $deliberation->publierResultats($deliberationId, $userId);
                $message = $success ? 'Résultats publiés' : 'Erreur lors de la publication des résultats';
                break;
                
            default:
                $success = false;
                $message = 'Étape inconnue';
                break;
        }
        
        // Mettre à jour le statut final de l'étape
        $statut = $success ? 'Terminé' : 'Erreur';
        $progression = $success ? 100 : 0;
        $deliberation->saveProcessusEtape($deliberationId, $etape, $statut, $message, $progression, $userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'etape' => $etape,
            'progression' => $progression
        ]);
        
    } catch (Exception $e) {
        // En cas d'erreur, mettre à jour le statut de l'étape
        $deliberation->saveProcessusEtape($deliberationId, $etape, 'Erreur', $e->getMessage(), 0, $userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage(),
            'etape' => $etape,
            'progression' => 0
        ]);
    }
    