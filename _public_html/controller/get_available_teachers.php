<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

$db = Connexion::getInstance()->getPDO();

try {
    $query = "SELECT a.idAgent, a.noms, g.designation as grade
              FROM agent a
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE a.type_agent = 'Enseignant'
              ORDER BY a.noms";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($teachers);
} catch (PDOException $e) {
    error_log("Erreur SQL: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la récupération des enseignants']);
}