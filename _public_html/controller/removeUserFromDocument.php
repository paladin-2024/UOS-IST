<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //$data = json_decode(file_get_contents('php://input'), true);

    if (isset($_POST['idUser']) && isset($_POST['id_document'])) {
        $userId = intval($_POST['idUser']);
        $documentId = intval($_POST['id_document']);
        $structure = new Structure();

        try {
            $structure->removeUserFromDocument($userId, $documentId);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'An error occurred while removing the user from the document.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input. User ID and document ID are required.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
?>