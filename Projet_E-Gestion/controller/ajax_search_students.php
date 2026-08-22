<?php
// Désactiver l'affichage des erreurs pour éviter qu'elles interfèrent avec le JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['idRole'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$query = $_GET['q'] ?? '';
$promotionId = isset($_GET['promotion_id']) ? intval($_GET['promotion_id']) : 0;
$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if (empty($query) || strlen($query) < 2 || $promotionId <= 0 || $anneeId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Rechercher les étudiants par matricule ou nom
    $sql = "SELECT e.matricule, e.noms, p.designationPromotion as promotion,
                   p.idpromotion, a.designation as annee_academique
            FROM etudiant e
            INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            INNER JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
            WHERE (e.matricule LIKE :search OR e.noms LIKE :search)
            AND e.promotion_idpromotion = :promotion_id
            AND e.annee_acad_idannee_acad = :annee_id
            AND e.est_actif = 1
            ORDER BY e.noms ASC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $searchTerm = '%' . $query . '%';
    $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':annee_id', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    
} catch (Exception $e) {
    error_log("Erreur dans ajax_search_students.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>
