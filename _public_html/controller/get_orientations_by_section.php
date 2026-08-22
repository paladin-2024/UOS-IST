<?php
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier que l'ID de la section est fourni
if (!isset($_GET['idSection'])) {
    echo json_encode(['error' => 'ID de la section non fourni']);
    exit;
}

$idSection = intval($_GET['idSection']);
$db = Connexion::getInstance()->getPDO();

try {
    // Récupérer toutes les orientations pour cette section
    $stmt = $db->prepare("
        SELECT o.idorientation, o.designationOrientation
        FROM orientation o
        WHERE o.section_idsection = ?
        ORDER BY o.designationOrientation
    ");
    
    $stmt->execute([$idSection]);
    $orientations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($orientations);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des orientations: ' . $e->getMessage()]);
}
?>