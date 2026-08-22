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
    
    // Gérer les frais en array
    $fraisIds = $_POST['frais_id'] ?? [];
    if (!is_array($fraisIds)) {
        $fraisIds = [$fraisIds];
    }
    $fraisIds = array_filter(array_map('intval', $fraisIds), function($id) { return $id > 0; });

    // Gérer les promotions en array
    $promotionIds = $_POST['promotion_id'] ?? [];
    if (!is_array($promotionIds)) {
        $promotionIds = [$promotionIds];
    }
    $promotionIds = array_filter(array_map('intval', $promotionIds), function($id) { return $id > 0; });

    $cycle = trim($_POST['cycle'] ?? '');
    $anneeAcadId = intval($_POST['annee_acad_id'] ?? 0);
    $ordre = intval($_POST['ordre'] ?? 0);

    if ($serviceId <= 0 || empty($fraisIds)) {
        throw new Exception('Données invalides');
    }

    $dependanceModel = new DependanceServiceFrais();
    $service = $dependanceModel->getServiceById($serviceId);

    if (!$service) {
        throw new Exception('Service introuvable');
    }

    // Déterminer le scope et valider les paramètres
    $scope = $service['scope'];
    
    if ($scope === 'promotion' && empty($promotionIds)) {
        throw new Exception('Au moins une promotion est requise pour ce type de service');
    }
    
    if ($scope === 'cycle' && empty($cycle)) {
        throw new Exception('Cycle requis pour ce type de service');
    }
    
    if ($scope === 'annee_complete' && $anneeAcadId <= 0) {
        throw new Exception('Année académique requise pour ce type de service');
    }

    // Ajouter les dépendances pour chaque combinaison promotion/frais
    $successCount = 0;
    $errorCount = 0;
    
    if ($scope === 'promotion') {
        // Créer une dépendance pour chaque promotion et chaque frais
        foreach ($promotionIds as $promotionId) {
            foreach ($fraisIds as $fraisId) {
                if ($dependanceModel->addDependance($serviceId, $fraisId, $promotionId, null, null, $scope, $ordre, $_SESSION['id'])) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }
    } else {
        // Pour les autres scopes, une dépendance par frais
        foreach ($fraisIds as $fraisId) {
            if ($dependanceModel->addDependance($serviceId, $fraisId, null, $cycle ?: null, $anneeAcadId ?: null, $scope, $ordre, $_SESSION['id'])) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
    }
    
    if ($successCount > 0) {
        $msg = $successCount > 1 ? "$successCount frais ont été ajoutés" : '1 frais a été ajouté';
        $_SESSION['message'] = "$msg aux dépendances avec succès";
        $_SESSION['messageType'] = 'success';
    }
    
    if ($errorCount > 0) {
        throw new Exception("$errorCount erreur(s) lors de l'ajout de dépendances");
    }
    
    if ($successCount === 0 && $errorCount === 0) {
        throw new Exception('Aucune dépendance n\'a été ajoutée');
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
    $_SESSION['messageType'] = 'danger';
    error_log('add_dependance_service_frais.php: ' . $e->getMessage());
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '?view=finance/config_services_frais');
exit;
