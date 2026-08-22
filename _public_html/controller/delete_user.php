<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/User.php';

header('Content-Type: application/json; charset=utf-8'); // Forcer le format JSON

$userModel = new User();

if (isset($_GET['idUser'])) {
    $idUser = intval($_GET['idUser']); // Convertir l'ID en entier pour éviter les injections
    $result = $userModel->deleteUser($idUser);

    echo json_encode([
        "status" => $result ? "success" : "error",
        "message" => $result ? "Utilisateur supprimé avec succès." : "Erreur lors de la suppression."
    ]);
    exit;
} else {
    echo json_encode([
        "status" => "error",
        "message" => "ID de l'utilisateur non spécifié."
    ]);
    exit;
}
