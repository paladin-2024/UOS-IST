<?php
// Inclure le fichier de configuration de la base de données
require_once dirname(__DIR__) . '/config/Connexion.php';

$db=Connexion::getInstance()->getPDO();

// Récupérer toutes les sections
$stmt = $db->query("SELECT idsection as id, designationSection as designation FROM section ORDER BY designationSection");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($sections);
?>
