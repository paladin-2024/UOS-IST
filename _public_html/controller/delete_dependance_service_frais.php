<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/DependanceServiceFrais.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    if (empty($_SESSION['id'])) {
        throw new Exception('Non authentifié');
    }

    $dependanceId = intval($_POST['dependance_id'] ?? 0);

    if ($dependanceId <= 0) {
        throw new Exception('Dépendance invalide');
    }

    $dependanceModel = new DependanceServiceFrais();
    
    if ($dependanceModel->deleteDependance($dependanceId)) {
        $_SESSION['message'] = 'Dépendance supprimée avec succès';
        $_SESSION['messageType'] = 'success';
    } else {
        throw new Exception('Erreur lors de la suppression de la dépendance');
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
    $_SESSION['messageType'] = 'danger';
    error_log('delete_dependance_service_frais.php: ' . $e->getMessage());
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '?view=finance/config_services_frais');
exit;
