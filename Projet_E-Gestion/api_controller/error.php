<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$code = isset($_GET['code']) ? intval($_GET['code']) : 500;

switch ($code) {
    case 404:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ressource non trouvée']);
        break;
    case 401:
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        break;
    case 403:
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès interdit']);
        break;
    default:
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur interne']);
}
?>
