<?php
session_start();
require_once '../../config/Connexion.php';
require_once '../../models/DependanceServiceFrais.php';

header('Content-Type: application/json');

try {
    if (empty($_SESSION['id'])) {
        throw new Exception('Non authentifié');
    }

    $anneeAcadId = intval($_GET['annee_acad_id'] ?? 0);

    if ($anneeAcadId <= 0) {
        throw new Exception('Année académique invalide');
    }

    $dependanceModel = new DependanceServiceFrais();
    $frais = $dependanceModel->getFraisAffectesByAnneeAcad($anneeAcadId);
    
    echo json_encode([
        'success' => true,
        'data' => $frais
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log('get_frais_by_annee.php: ' . $e->getMessage());
}
