<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_GET['idSpecialisation']) || empty($_GET['idSpecialisation'])) {
    echo json_encode(['error' => 'ID de la spécialisation manquant']);
    exit;
}

$idSpecialisation = intval($_GET['idSpecialisation']);
$db = Connexion::getInstance()->getPDO();

try {
    $query = "SELECT es.id as idAffectation, a.noms, g.designation as gradeDesignation, 
                     es.dateAffectation
              FROM enseignant_specialisation es
              JOIN agent a ON es.idAgent = a.idAgent
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE es.idSpecialisation = ?
              ORDER BY a.noms";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$idSpecialisation]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($teachers);
} catch (PDOException $e) {
    error_log("Erreur SQL: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la récupération des enseignants']);
}
