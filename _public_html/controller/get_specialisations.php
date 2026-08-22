<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_GET['orientation_id'])) {
echo json_encode(['error' => 'Paramètres manquants']);
exit;
}

$orientationId = intval($_GET['orientation_id']);

if ($orientationId <= 0) {
    echo json_encode(['error' => 'Paramètres invalides']);
exit;
}

$db = Connexion::getInstance()->getPDO();

try {
$stmt = $db->prepare("
SELECT s.*
FROM specialisation s
WHERE s.idorientation = ?
ORDER BY s.designation
");
$stmt->execute([$orientationId]);

$specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($specialisations);
} catch (PDOException $e) {
echo json_encode(['error' => 'Erreur lors de la récupération des spécialisations: ' . $e->getMessage()]);
}
