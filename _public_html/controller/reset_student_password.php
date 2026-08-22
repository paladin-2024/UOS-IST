<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resetPassword'])) {
    $etudiantId = $_POST['etudiantId'];
    $newPassword = '12345678'; // Mot de passe par défaut

    try {
        $etudiant = new Etudiant();
        // First check if student exists
        $existingStudent = $etudiant->getEtudiantById($etudiantId);
        if (!$existingStudent) {
            echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé.']);
            exit;
        }
        $result = $etudiant->changePassword($etudiantId, $newPassword);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la réinitialisation du mot de passe.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}
?>
