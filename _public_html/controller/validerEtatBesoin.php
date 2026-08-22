<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etatId = $_POST['etatId'] ?? null;
    $userId = $_POST['userId'] ?? null;

    if ($etatId) {
        $structure = new Structure();
        // Assuming there's a method to validate the état de besoin
        $success = $structure->validateEtatDeBesoin($etatId,$userId);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'État de besoin validé avec succès.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Échec de la validation de l\'état de besoin.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID de l\'état de besoin manquant.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Requête invalide.']);
}
?>