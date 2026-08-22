<?php
// Démarrer la session pour accéder aux variables de session
session_start();

// Inclure le fichier de connexion à la base de données
require_once dirname(__DIR__) . "/config/Connexion.php";

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si la requête est une méthode GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Méthode de requête invalide']);
    exit;
}

// Récupérer les paramètres
$promotionId = isset($_GET['promotion_id']) ? intval($_GET['promotion_id']) : 0;
$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

// Vérifier que les paramètres sont valides
if ($promotionId <= 0 || $anneeId <= 0) {
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

$userId = $_SESSION['id'];

try {
    // Établir la connexion à la base de données
    $pdo = Connexion::getInstance()->getPDO();
    
    // Vérifier que la promotion existe et récupérer sa section
    $queryCheckPromotion = "SELECT p.*, o.section_idsection 
                           FROM promotion p
                           LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                           WHERE p.idpromotion = ?";
    $stmtCheckPromotion = $pdo->prepare($queryCheckPromotion);
    $stmtCheckPromotion->execute([$promotionId]);
    $promotion = $stmtCheckPromotion->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        echo json_encode(['error' => 'Promotion non trouvée']);
        exit;
    }
    
    // Vérifier les droits de l'utilisateur sur cette promotion
    $hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur
    
    if (!$hasFullAccess) {
        // Récupérer les sections dont l'utilisateur est responsable
        $queryUserSections = "SELECT section_idsection 
                             FROM responsable_section 
                             WHERE idUser = ? AND annee_acad_idannee_acad = ?";
        $stmtUserSections = $pdo->prepare($queryUserSections);
        $stmtUserSections->execute([$userId, $anneeId]);
        $userSections = $stmtUserSections->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($userSections) || !in_array($promotion['section_idsection'], $userSections)) {
            echo json_encode(['error' => 'Accès refusé à cette promotion']);
            exit;
        }
    }
    
    // Récupérer les étudiants inscrits dans cette promotion pour cette année académique
    $queryEtudiants = "SELECT e.idetudiant, e.noms, e.matricule 
                      FROM etudiant e
                      WHERE e.promotion_idpromotion = ? 
                      AND e.annee_acad_idannee_acad = ?
                      AND e.est_actif = 1
                      ORDER BY e.noms";
    
    $stmtEtudiants = $pdo->prepare($queryEtudiants);
    $stmtEtudiants->execute([$promotionId, $anneeId]);
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);
    
    // Retourner les résultats en JSON
    echo json_encode([
        'success' => true,
        'etudiants' => $etudiants,
        'promotion' => [
            'id' => $promotion['idpromotion'],
            'designation' => $promotion['designationPromotion']
        ]
    ]);
    
} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    error_log("Erreur dans get_etudiants_promotion.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Gérer les autres erreurs
    error_log("Erreur générale dans get_etudiants_promotion.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur générale: ' . $e->getMessage()]);
}
?>