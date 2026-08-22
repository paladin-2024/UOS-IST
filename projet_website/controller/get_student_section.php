<?php
// Inclure le fichier de configuration de la base de données
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si le matricule est fourni
if (!isset($_GET['matricule']) || empty($_GET['matricule'])) {
    echo json_encode(['success' => false, 'message' => 'Matricule non fourni']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $matricule = $_GET['matricule'];
    
    // Récupérer les informations de l'étudiant et de sa section via les relations
    $stmt = $db->prepare("
        SELECT e.*, p.idpromotion, p.designationPromotion, p.cycle,
               o.idorientation, o.designationOrientation,
               s.idsection, s.designationSection
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        WHERE e.matricule = ?
    ");
    $stmt->execute([$matricule]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit;
    }
    
    // Récupérer les orientations disponibles pour cette section
    $stmt = $db->prepare("
        SELECT idorientation as id, designationOrientation as designation
        FROM orientation
        WHERE section_idsection = ?
    ");
    $stmt->execute([$etudiant['idsection']]);
    $orientations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retourner les informations
    echo json_encode([
        'success' => true,
        'etudiant' => [
            'id' => $etudiant['idetudiant'],
            'matricule' => $etudiant['matricule'],
            'noms' => $etudiant['noms']
        ],
        'section' => [
            'id' => $etudiant['idsection'],
            'designation' => $etudiant['designationSection']
        ],
        'promotion' => [
            'id' => $etudiant['idpromotion'],
            'designation' => $etudiant['designationPromotion'],
            'cycle' => $etudiant['cycle']
        ],
        'orientation' => [
            'id' => $etudiant['idorientation'],
            'designation' => $etudiant['designationOrientation']
        ],
        'orientations' => $orientations
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
