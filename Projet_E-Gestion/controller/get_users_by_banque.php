<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Banque.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['banqueId'])) {
    $banqueId = intval($_GET['banqueId']);
    $banque = new Banque();

    // Assuming there is a method getUsersByBanque in the Structure model
    $users = $banque->getUsersByBanque($banqueId);

    // Return the users as a JSON response
    echo json_encode($users);
} else {
    echo json_encode(['error' => 'Invalid request']);
}