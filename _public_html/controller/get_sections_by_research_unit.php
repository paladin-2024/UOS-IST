<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_GET['idUniteRecherche']) || empty($_GET['idUniteRecherche'])) {
    echo json_encode(['error' => 'ID de l\'unité de recherche manquant']);
    exit;
}

$idUniteRecherche = intval($_GET['idUniteRecherche']);
$anneeAcad = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;
$db = Connexion::getInstance()->getPDO();

try {
    $query = "SELECT s.*, a.designation as anneeDesignation, a.idannee_acad as idAnnee
          FROM unite_recherche_section urs
          JOIN section s ON urs.idsection = s.idsection
          LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
          WHERE urs.idunite_recherche = ?";
    
    $params = [$idUniteRecherche];
    
    if ($anneeAcad > 0) {
        $query .= " AND a.idannee_acad = ?";
        $params[] = $anneeAcad;
    }
    
    $query .= " ORDER BY s.designationSection";

    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($sections);
} catch (PDOException $e) {
    error_log("Erreur SQL: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la récupération des sections']);
}
