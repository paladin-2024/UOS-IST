<?php
// Inclure le fichier de configuration de la base de données
require_once dirname(__DIR__) . '/config/Connexion.php';

$db=Connexion::getInstance()->getPDO();
// Vérifier si l'ID de l'année académique est fourni
if (!isset($_GET['annee_id']) || empty($_GET['annee_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$annee_id = intval($_GET['annee_id']);

// Récupérer les promotions pour l'année académique spécifiée
$stmt = $db->prepare("SELECT idpromotion as id, designationPromotion as designation FROM promotion WHERE annee_acad_idannee_acad = ? ORDER BY designationPromotion");
$stmt->execute([$annee_id]);
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($promotions);
?>
