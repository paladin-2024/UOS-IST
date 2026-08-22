<?php
session_start();
include "../config/Connexion.php";

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['idUser'])) {
    $idUser = intval($_GET['idUser']);
    $connexion = Connexion::getInstance()->getPDO();

    $query = "SELECT \"idRole\", \"isPrincipal\" FROM t_user_roles WHERE \"idUser\" = :idUser";
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':idUser', $idUser);
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($roles);
} else {
    http_response_code(400);
}
?>
