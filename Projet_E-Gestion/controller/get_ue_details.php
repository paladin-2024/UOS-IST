<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idUE = intval($_GET['id']);
    $universite = new Universite();
    $ue = $universite->getUEById($idUE);
    
    header('Content-Type: application/json');
    
    if ($ue) {
        echo json_encode([
            'success' => true,
            'code' => $ue['codeUE'],
            'designation' => $ue['designationUE'],
            'description' => $ue['description'],
            'semestre_id' => $ue['semestre_idsemestre']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'UE non trouvée'
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID invalide'
    ]);
}
?>
