<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

require_once dirname(__DIR__) . '/config/Connexion.php';

try {
    $db = Connexion::getInstance()->getPDO();
    $userId = $_SESSION['id'];

    // Vérifier s'il y a des nouvelles visites dans les dernières 5 minutes
    $checkQuery = "
        SELECT COUNT(*) as nouvelles_visites,
               STRING_AGG(CONCAT(nom_visiteur, ' ', prenom_visiteur), ', ') as noms_visiteurs
        FROM visites
        WHERE cree_par = ?
        AND date_creation >= NOW() - INTERVAL '5 minutes'
        AND statut_visite = 'programmee'
    ";
    
    $stmt = $db->prepare($checkQuery);
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $hasNew = $result['nouvelles_visites'] > 0;
    $message = '';

    if ($hasNew) {
        if ($result['nouvelles_visites'] == 1) {
            $message = "Nouvelle visite programmée : " . $result['noms_visiteurs'];
        } else {
            $message = $result['nouvelles_visites'] . " nouvelles visites programmées";
        }
    }

    echo json_encode([
        'hasNew' => $hasNew,
        'count' => (int)$result['nouvelles_visites'],
        'message' => $message
    ]);

} catch (Exception $e) {
    error_log("Erreur check nouvelles visites: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>