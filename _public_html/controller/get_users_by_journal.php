<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['journalId'])) {
    $journalId = intval($_GET['journalId']);
    $structure = new Structure();

    $users = $structure->getUsersByJournal($journalId);
    echo json_encode($users);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>