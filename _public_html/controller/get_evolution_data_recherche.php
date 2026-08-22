<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_GET['sectionId']) || !is_numeric($_GET['sectionId'])) {
    echo json_encode(['error' => 'ID de section invalide']);
    exit;
}

$sectionId = intval($_GET['sectionId']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Log pour debug
    error_log("get_evolution_data_recherche.php - sectionId: " . $sectionId);
    
    $query = "SELECT aa.designation as annee,
                     COUNT(sj.idsujets) as total_sujets,
                     SUM(CASE WHEN sj.statut_validation = 'Validé' THEN 1 ELSE 0 END) as sujets_valides
              FROM sujets sj
              INNER JOIN annee_acad aa ON sj.annee_acad_idannee_acad = aa.idannee_acad
              INNER JOIN specialisation sp ON sj.idSpecialisation = sp.idSpecialisation
              INNER JOIN orientation ori ON sp.idorientation = ori.idorientation
              INNER JOIN section sec ON ori.section_idsection = sec.idsection
              WHERE sec.idsection = :sectionId
              GROUP BY aa.idannee_acad, aa.designation
              ORDER BY aa.idannee_acad DESC
              LIMIT 5";
    
    // Log de la requête pour debug
    error_log("get_evolution_data_recherche.php - Query: " . $query);
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    $stmt->execute();
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log du résultat pour debug
    error_log("get_evolution_data_recherche.php - Résultats: " . count($data) . " lignes");
    
    echo json_encode(array_reverse($data));
    
} catch (Exception $e) {
    error_log("get_evolution_data_recherche.php - Erreur: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()]);
}
?>