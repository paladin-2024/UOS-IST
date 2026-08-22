<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Banque.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['userBanqueId'])) {
    $userBanqueId = intval($_POST['userBanqueId']);
    $banque = new Banque();

    // Remove the user from the bank
    $success = $banque->removeUserFromBanque($userBanqueId);

    // Return a JSON response indicating success or failure
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to remove user from bank']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}