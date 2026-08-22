<?php
// Inclure le fichier de configuration de la base de données
require_once dirname(__DIR__) . '/config/Connexion.php';

$db=Connexion::getInstance()->getPDO();

// Récupérer les années académiques
$stmt = $db->query("SELECT idannee_acad as id, designation FROM annee_acad ORDER BY dateCreation DESC LIMIT 5");
$annees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($annees);
?>
