<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Vous devez être connecté pour accéder à cette ressource.']);
    exit;
}

// Vérifier si l'ID est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID de session manquant']);
    exit;
}

$idUser = $_SESSION['id'];
$session_id = intval($_GET['id']);
$connexion = Connexion::getInstance()->getPDO();

try {
    // Récupérer les détails de la session
    $stmt = $connexion->prepare("
        SELECT s.*,
               c.designation as caisse_nom, c.devise,
               a_agent.noms as agent_nom, a_agent.matricule as agent_matricule,
               a_validateur.noms as validateur_nom, a_validateur.matricule as validateur_matricule
        FROM sessions_caisse s
        JOIN caisses c ON s.caisse_id = c.id
        LEFT JOIN agent a_agent ON s.idAgent = a_agent.idAgent
        LEFT JOIN agent a_validateur ON s.idValidateur = a_validateur.idAgent
        WHERE s.id = :session_id
    ");
    $stmt->bindParam(':session_id', $session_id);
    $stmt->execute();
    
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Session non trouvée']);
        exit;
    }
    
    // Vérifier que l'utilisateur a accès à cette caisse
    $stmt = $connexion->prepare("
        SELECT COUNT(*) as count
        FROM droits_acces_finances 
        WHERE idUser = :idUser AND type = 'Caisse' 
        AND (entite_id = :caisse_id OR entite_id IS NULL)
        AND est_actif = 1 
        AND (date_debut IS NULL OR date_debut <= CURRENT_DATE) 
        AND (date_fin IS NULL OR date_fin >= CURRENT_DATE)
    ");
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':caisse_id', $session['caisse_id']);
    $stmt->execute();
    $acces_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($acces_count == 0) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Vous n\'avez pas accès à cette caisse']);
        exit;
    }
    
    // Formater les nombres pour l'affichage
    $session['montant_ouverture'] = floatval($session['montant_ouverture']);
    if ($session['montant_fermeture'] !== null) {
        $session['montant_fermeture'] = floatval($session['montant_fermeture']);
    }
    if ($session['montant_calcule'] !== null) {
        $session['montant_calcule'] = floatval($session['montant_calcule']);
    }
    if ($session['difference'] !== null) {
        $session['difference'] = floatval($session['difference']);
    }
    
    // Retourner les détails au format JSON
    header('Content-Type: application/json');
    echo json_encode($session);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}