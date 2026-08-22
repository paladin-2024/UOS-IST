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
    require_once __DIR__ . "/../models/Soutenance.php";

    // Récupérer l'ID de la soutenance
    $soutenanceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$soutenanceId) {
        echo json_encode(['success' => false, 'message' => 'ID de soutenance invalide']);
        exit;
    }

    // Initialiser le modèle
    $soutenance = new Soutenance();
    $db = Connexion::getInstance()->getPDO();

    // Récupérer les détails de la soutenance
    $query = "SELECT s.*, 
                     sj.intitule as sujet_titre, sj.idsujets,
                     e.noms as etudiant_nom, e.matricule,
                     d.noms as directeur_nom,
                     sp.designation as specialisation
              FROM soutenance s
              JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
              LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
              WHERE s.idsoutenance = :id";

    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $soutenanceId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Soutenance non trouvée']);
        exit;
    }

    // Récupérer les lecteurs assignés
    $lecteurs_query = "SELECT ls.id, ls.idsoutenance, ls.idenseignant, ls.est_premier_lecteur, 
                             a.noms, a.\"idAgent\", a.grade_id, g.designation as grade
                      FROM lecteurs_soutenance ls
                      INNER JOIN agent a ON ls.idenseignant = a.\"idAgent\"
                      LEFT JOIN grade g ON a.grade_id = g.idgrade
                      WHERE ls.idsoutenance = :id
                      ORDER BY ls.est_premier_lecteur DESC";
    
    $lecteurs_stmt = $db->prepare($lecteurs_query);
    $lecteurs_stmt->execute(['id' => $soutenanceId]);
    $lecteurs = $lecteurs_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter les lecteurs aux résultats
    $result['lecteurs'] = $lecteurs;

    echo json_encode([
        'success' => true,
        'soutenance' => $result
    ]);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des détails de la soutenance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du traitement'
    ]);
}
