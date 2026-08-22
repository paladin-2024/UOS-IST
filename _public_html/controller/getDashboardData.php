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

    // Statistiques en temps réel
    $statsQuery = "
        SELECT 
            COUNT(*) as total_visites,
            COUNT(CASE WHEN statut_visite = 'programmee' THEN 1 END) as visites_programmees,
            COUNT(CASE WHEN statut_visite = 'en_cours' THEN 1 END) as visites_en_cours,
            COUNT(CASE WHEN statut_visite = 'terminee' THEN 1 END) as visites_terminees,
            COUNT(CASE WHEN statut_visite = 'annulee' THEN 1 END) as visites_annulees,
            COUNT(CASE WHEN DATE(date_visite) = CURDATE() THEN 1 END) as visites_aujourdhui,
            COUNT(CASE WHEN DATE(date_visite) = CURDATE() + INTERVAL 1 DAY THEN 1 END) as visites_demain
        FROM visites 
        WHERE cree_par = ?
    ";
    
    $stmt = $db->prepare($statsQuery);
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($stats);

} catch (Exception $e) {
    error_log("Erreur dashboard data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>