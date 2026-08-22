<?php
// Inclure le fichier de configuration de la base de données
require_once dirname(__DIR__) . '/config/Connexion.php';

$db=Connexion::getInstance()->getPDO();
// Vérifier si l'ID de section est fourni
if (!isset($_GET['section_id']) || empty($_GET['section_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$section_id = intval($_GET['section_id']);

// Récupérer les orientations pour la section spécifiée
$stmt = $db->prepare("SELECT idorientation as id, designationOrientation as designation FROM orientation WHERE section_idsection = ? ORDER BY designationOrientation");
$stmt->execute([$section_id]);
$orientations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($orientations);
?>
