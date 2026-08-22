<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userJournalId = isset($_POST['userJournalId']) ? intval($_POST['userJournalId']) : 0;

    if ($userJournalId > 0) {
        $structure = new Structure();
        if ($structure->removeUserFromJournal($userJournalId)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la suppression de l\'utilisateur.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée.']);
}
?>