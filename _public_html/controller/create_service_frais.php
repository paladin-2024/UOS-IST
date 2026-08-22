<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/DependanceServiceFrais.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Vérifier les permissions
    if (empty($_SESSION['id'])) {
        throw new Exception('Non authentifié');
    }

    $designation = trim($_POST['designation'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $scope = trim($_POST['scope'] ?? '');

    // Validation
    if (empty($designation) || empty($type) || empty($scope)) {
        throw new Exception('Les champs obligatoires sont manquants');
    }

    if (!in_array($type, ['service', 'document'])) {
        throw new Exception('Type invalide');
    }

    if (!in_array($scope, ['promotion', 'cycle', 'annee_complete'])) {
        throw new Exception('Portée invalide');
    }

    $dependanceModel = new DependanceServiceFrais();
    
    if ($dependanceModel->createService($designation, $type, $description, $scope, $_SESSION['id'])) {
        $_SESSION['message'] = 'Service créé avec succès';
        $_SESSION['messageType'] = 'success';
    } else {
        throw new Exception('Erreur lors de la création du service');
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
    $_SESSION['messageType'] = 'danger';
    error_log('create_service_frais.php: ' . $e->getMessage());
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '?view=finance/config_services_frais');
exit;
