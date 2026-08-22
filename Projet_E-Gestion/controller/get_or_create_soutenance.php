<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier les droits d'accès
if (!isset($_SESSION['id']) || !isset($_SESSION['idRole'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// Seuls les admins et les responsables de section peuvent créer des soutenances
$isAdmin = $_SESSION['idRole'] == 1;
$isChefSection = $_SESSION['idRole'] == 5;

if (!$isAdmin && !$isChefSection) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
    exit;
}

require_once __DIR__ . '/../config/Connexion.php';

$sujetId = isset($_GET['sujet_id']) ? intval($_GET['sujet_id']) : null;

if (!$sujetId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du sujet non fourni']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier si une soutenance existe déjà pour ce sujet
    $query = "SELECT idsoutenance FROM soutenance WHERE sujets_idsujets = :sujetId";
    $stmt = $db->prepare($query);
    $stmt->execute(['sujetId' => $sujetId]);
    $existingSoutenance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingSoutenance) {
        // La soutenance existe déjà
        echo json_encode([
            'success' => true,
            'soutenance_id' => $existingSoutenance['idsoutenance'],
            'message' => 'Soutenance existante'
        ]);
        exit;
    }
    
    // Récupérer les informations du sujet
    $query = "SELECT sj.*, e.idetudiant FROM sujets sj
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              WHERE sj.idsujets = :sujetId";
    $stmt = $db->prepare($query);
    $stmt->execute(['sujetId' => $sujetId]);
    $sujet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sujet) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sujet non trouvé']);
        exit;
    }
    
    // Récupérer l'année académique active
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $activeYear = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activeYear) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucune année académique active']);
        exit;
    }
    
    // Créer une nouvelle soutenance
    $query = "INSERT INTO soutenance (sujets_idsujets, statut, annee_acad_idannee_acad)
              VALUES (:sujetId, 'Non programmée', :anneeId)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'sujetId' => $sujetId,
        'anneeId' => $activeYear['idannee_acad']
    ]);
    
    $soutenanceId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'soutenance_id' => $soutenanceId,
        'message' => 'Soutenance créée avec succès'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur lors de la création de la soutenance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
