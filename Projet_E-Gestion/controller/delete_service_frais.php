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

    $serviceId = intval($_POST['service_id'] ?? 0);

    if ($serviceId <= 0) {
        throw new Exception('Service invalide');
    }

    $dependanceModel = new DependanceServiceFrais();
    
    if ($dependanceModel->deleteService($serviceId)) {
        $_SESSION['message'] = 'Service supprimé avec succès';
        $_SESSION['messageType'] = 'success';
    } else {
        throw new Exception('Erreur lors de la suppression du service');
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
    $_SESSION['messageType'] = 'danger';
    error_log('delete_service_frais.php: ' . $e->getMessage());
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '?view=finance/config_services_frais');
exit;
