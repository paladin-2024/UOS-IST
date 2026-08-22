<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }

    if (!isset($_SESSION['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Utilisateur non authentifié']);
        exit;
    }

    if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']);
        exit;
    }

    $file = $_FILES['file'];
    $userId = (int) $_SESSION['id'];

    // Accepte uniquement les PDF issus du scan
    $allowedMime = ['application/pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        http_response_code(415);
        echo json_encode(['success' => false, 'error' => 'Type de fichier non supporté (PDF requis)']);
        exit;
    }

    $uploadDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Impossible de créer le dossier uploads');
        }
    }

    $newFileName = sprintf('SCAN_PRIV_%s_%s.pdf', date('Ymd_His'), $userId);
    $destPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException("Échec lors de l'enregistrement du fichier");
    }

    echo json_encode([
        'success' => true,
        'filename' => $newFileName,
        'message' => 'Fichier scanné enregistré avec succès'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

