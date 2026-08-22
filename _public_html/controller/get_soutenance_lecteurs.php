<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

// Définir le type de contenu JSON en premier
header('Content-Type: application/json; charset=utf-8');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    require_once __DIR__ . "/../config/Connexion.php";

    // Récupérer l'ID de la soutenance
    $soutenanceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$soutenanceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de soutenance invalide']);
        exit;
    }

    $db = Connexion::getInstance()->getPDO();

    // Récupérer les lecteurs assignés
    $query = "SELECT ls.id, ls.idsoutenance, ls.idenseignant, ls.est_premier_lecteur, 
                     a.noms, a.\"idAgent\", a.grade_id, g.designation as grade
              FROM lecteurs_soutenance ls
              INNER JOIN agent a ON ls.idenseignant = a.\"idAgent\"
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE ls.idsoutenance = :id
              ORDER BY ls.est_premier_lecteur DESC";

    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête");
    }
    
    if (!$stmt->execute(['id' => $soutenanceId])) {
        throw new Exception("Erreur lors de l'exécution de la requête");
    }
    
    $lecteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($lecteurs === false) {
        throw new Exception("Erreur lors de la récupération des données");
    }

    $response = [
        'success' => true,
        'lecteurs' => $lecteurs
    ];

    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erreur dans get_soutenance_lecteurs.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    
    $errorResponse = [
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ];
    
    $json = json_encode($errorResponse);
    
    if ($json === false) {
        echo '{"success":false,"message":"Erreur lors de l\'encodage JSON"}';
    } else {
        echo $json;
    }
}
?>
