<?php
include_once '../config/Connexion.php';
include_once '../models/Structure.php';


if (isset($_GET['structureId'])) {
    $structureId = $_GET['structureId'];

    $structureModel = new Structure();
    $accounts = $structureModel->getComptesByStructure($structureId);

    echo json_encode($accounts);
}
?>