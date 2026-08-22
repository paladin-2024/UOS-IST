<?php
// Vérifier si l'utilisateur est connecté et a les droits d'admin
session_start();
if (!isset($_SESSION['id']) && !isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer l'ID de la galerie
$galleryId = isset($_GET['gallery_id']) ? intval($_GET['gallery_id']) : 0;

if ($galleryId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de galerie invalide']);
    exit;
}

// Connexion à la base de données
require_once dirname(__DIR__) . '/config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer les images de la galerie
$stmt = $db->prepare("SELECT * FROM media WHERE gallery_id = :gallery_id ORDER BY created_at DESC");
$stmt->bindParam(':gallery_id', $galleryId);
$stmt->execute();
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($media);
exit;