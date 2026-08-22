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

    // Vérifier les champs à mettre à jour
    if (isset($_POST['designation'])) {
        // Mise à jour du service
        $designation = trim($_POST['designation'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($designation)) {
            throw new Exception('Désignation obligatoire');
        }

        $service = $dependanceModel->getServiceById($serviceId);
        if (!$service) {
            throw new Exception('Service introuvable');
        }

        if ($dependanceModel->updateService($serviceId, $designation, $description, $service['scope'], $service['active'])) {
            $_SESSION['message'] = 'Service mis à jour avec succès';
            $_SESSION['messageType'] = 'success';
        } else {
            throw new Exception('Erreur lors de la mise à jour du service');
        }
    } elseif (isset($_POST['active'])) {
        // Activation/Désactivation du service
        $active = intval($_POST['active']) ? true : false;
        
        $service = $dependanceModel->getServiceById($serviceId);
        if (!$service) {
            throw new Exception('Service introuvable');
        }

        if ($dependanceModel->updateService($serviceId, $service['designation'], $service['description'], $service['scope'], $active)) {
            $_SESSION['message'] = 'Service ' . ($active ? 'activé' : 'désactivé') . ' avec succès';
            $_SESSION['messageType'] = 'success';
        } else {
            throw new Exception('Erreur lors de la mise à jour du service');
        }
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
    $_SESSION['messageType'] = 'danger';
    error_log('update_service_frais.php: ' . $e->getMessage());
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '?view=finance/config_services_frais');
exit;
