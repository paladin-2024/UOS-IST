<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'connexion.php';
require_once 'auth.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify authentication
$auth = new Auth();
$studentId = $auth->authenticate(); //Identifiant de l'étudiant

if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Vérifier si l'idECUE est fourni
if (!isset($_GET['idECUE']) || empty($_GET['idECUE'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du cours non spécifié'], JSON_UNESCAPED_UNICODE);
    exit();
}

$idECUE = intval($_GET['idECUE']);

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // S'assurer que la connexion est en UTF-8
    $conn->exec("SET NAMES utf8mb4");
    
    // Récupérer les détails du cours (ECUE)
    $stmt = $conn->prepare('SELECT e.*,
                           u."designationUE", u."codeUE",
                           s."numeroSemestre",
                           p."designationPromotion", p.cycle, p.annee_acad_idannee_acad,
                           o."designationOrientation", p.idpromotion
                           FROM ecue e
                           JOIN ue u ON e."UE_idUE" = u."idUE"
                           JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                           JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                           JOIN orientation o ON p.orientation_idorientation = o.idorientation
                           WHERE e."idECUE" = ?');
    $stmt->execute([$idECUE]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Cours non trouvé ou non accessible'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $anneeAcadId = $course['annee_acad_idannee_acad'];
    
    // Récupérer les enseignants du cours
    $stmt = $conn->prepare('SELECT e.*, a.noms, gr.designation as titre
                           FROM enseignant_ecue e
                           JOIN agent a ON e."idAgent" = a."idAgent"
                           LEFT JOIN grade gr ON gr.idgrade = a.grade_id
                           WHERE e."idECUE" = ?
                           AND e."anneeAcad" = ?');
    $stmt->execute([$idECUE, $anneeAcadId]);
    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les supports de cours
    $stmt = $conn->prepare('SELECT * FROM support_cours
                           WHERE "idECUE" = ?
                           ORDER BY "dateCreation" DESC');
    $stmt->execute([$idECUE]);
    $supports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vérifier l'accès aux supports
    foreach ($supports as &$support) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM paiement
                               INNER JOIN support_cours d ON d.idfrais=paiement.frais_idfrais
                               WHERE paiement.etudiant_idetudiant = ?
                               AND d.idsupport = ?");
        $stmt->execute([$studentId, $support['idsupport']]);
        $support['access_granted'] = ($stmt->fetchColumn() > 0);
    }
    
    // Récupérer les chapitres du cours
    $stmt = $conn->prepare('SELECT * FROM parties_cours
                           WHERE "idECUE" = ?
                           ORDER BY ordre ASC');
    $stmt->execute([$idECUE]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque chapitre, récupérer les ressources
    foreach ($chapters as &$chapter) {
        $idPartie = $chapter['idpartie'];
        
        $stmt = $conn->prepare('SELECT * FROM ressources_cours
                               WHERE idpartie = ?
                               ORDER BY "dateCreation" DESC');
        $stmt->execute([$idPartie]);
        $ressources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Vérifier l'accès aux ressources
        foreach ($ressources as &$ressource) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM paiement
                                   INNER JOIN ressources_cours d ON d.idfrais=paiement.frais_idfrais
                                   WHERE paiement.etudiant_idetudiant = ?
                                   AND d.idressource = ?");
            $stmt->execute([$studentId, $ressource['idressource']]);
            $ressource['access_granted'] = ($stmt->fetchColumn() > 0);
        }
        
        $chapter['ressources'] = $ressources;
    }
    
    // Récupérer les devoirs liés au cours
    $stmt = $conn->prepare('SELECT d.*
                           FROM devoirs d
                           WHERE d."idECUE" = ?
                           ORDER BY d.date_limite ASC');
    $stmt->execute([$idECUE]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque devoir, vérifier l'accès et la soumission éventuelle
    foreach ($assignments as &$assignment) {
        $idDevoir = $assignment['iddevoir'];
        
        // Vérifier l'accès au devoir
        $stmt = $conn->prepare('SELECT COUNT(*) FROM paiement
                               INNER JOIN devoirs d ON d.idfrais=paiement.frais_idfrais
                               WHERE paiement.etudiant_idetudiant = ?
                               AND d.iddevoir = ?
                               AND "estComplet" = 1');
        $stmt->execute([$studentId, $idDevoir]);
        $assignment['access_granted'] = ($stmt->fetchColumn() > 0);
        
        // Récupérer la réponse de l'étudiant
        $stmt = $conn->prepare("SELECT * FROM reponses_devoir
                               WHERE idetudiant = ?
                               AND iddevoir = ?");
        $stmt->execute([$studentId, $idDevoir]);
        $reponse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reponse) {
            $assignment['reponse'] = $reponse;
        }
    }
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'course' => $course,
        'enseignants' => $enseignants,
        'supports' => $supports,
        'chapters' => $chapters,
        'assignments' => $assignments
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
