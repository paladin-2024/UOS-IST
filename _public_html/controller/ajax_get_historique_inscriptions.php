<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/Connexion.php';

try {
    $matricule = isset($_GET['matricule']) ? trim($_GET['matricule']) : '';
    
    if (empty($matricule)) {
        echo json_encode(['success' => false, 'message' => 'Matricule requis']);
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    
    $query = "SELECT e.idetudiant, e.matricule, e.noms, e.est_actif, e.\"dateEnregistrement\",
                     p.\"designationPromotion\", 
                     o.\"designationOrientation\",
                     s.\"designationSection\",
                     a.designation as annee
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              JOIN annee_acad a ON a.idannee_acad = e.annee_acad_idannee_acad
              WHERE e.matricule = :matricule
              ORDER BY a.designation DESC, e.idetudiant DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
    $stmt->execute();
    $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'inscriptions' => $inscriptions,
        'total' => count($inscriptions)
    ]);
    
} catch (Exception $e) {
    error_log("Erreur historique inscriptions: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
