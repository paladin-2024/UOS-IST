<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non authentifié']);
    exit();
}

// Récupérer le matricule
$matricule = isset($_GET['matricule']) ? trim($_GET['matricule']) : '';

if (empty($matricule)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Matricule non spécifié']);
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations de l'étudiant
    $stmt = $connexion->prepare("
        SELECT e.*, 
               p.designationPromotion, 
               o.designationOrientation, 
               s.designationSection
        FROM etudiant e
        LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
        LEFT JOIN section s ON o.section_idsection = s.idsection
        WHERE e.matricule = :matricule AND e.est_actif=1
    ");
    $stmt->bindParam(':matricule', $matricule);
    $stmt->execute();
    
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Aucun étudiant trouvé avec ce matricule']);
        exit();
    }
    
    // Préparer les données à retourner en utilisant les bons noms de colonnes
    $result = [
        'id' => $etudiant['idetudiant'], // Utiliser idetudiant au lieu de id
        'matricule' => $etudiant['matricule'],
        'nom' => $etudiant['noms'],
        'promotion' => $etudiant['designationPromotion'] ?? 'Non spécifiée',
        'faculte' => ($etudiant['designationSection'] ?? '') . ' - ' . ($etudiant['designationOrientation'] ?? ''),
        'promotion_id' => $etudiant['promotion_idpromotion']
    ];
    
    // Retourner les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    // Afficher des informations de débogage plus détaillées
    error_log('Erreur dans get_etudiant_info.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit();
}
?>
