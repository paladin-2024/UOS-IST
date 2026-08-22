<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/User.php';

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';

$userModel = new User();
$users = $userModel->getUserListe($offset, $limit, $search);
$total = $userModel->countUser($search);

echo json_encode([
    'users' => $users,
    'total' => $total,
    'hasMore' => ($offset + $limit) < $total
]);