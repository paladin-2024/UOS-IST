<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID manquant']);
    exit;
}

$orientationId = intval($_GET['id']);
$universite = new Universite();

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Récupérer l'année académique active
    $activeYear = $universite->getActiveAcademicYear();
    $activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;
    
    if ($activeYearId) {
        // Récupérer le chef actuel pour cette orientation et cette année
        $query = "SELECT idUser 
                 FROM responsable_orientation 
                 WHERE orientation_idorientation = :orientationId 
                 AND est_chef = 1 
                 AND annee_acad_idannee_acad = :anneeId
                 LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            'orientationId' => $orientationId,
            'anneeId' => $activeYearId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode(['success' => true, 'chefId' => $result['idUser']]);
        } else {
            echo json_encode(['success' => false, 'chefId' => null]);
        }
    } else {
        echo json_encode(['error' => 'Aucune année académique active']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>