<?php
// Returns services for a given structure (campus)
// Params: structure (int)
// JSON: [ { idService, designationService } ]

error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Service.php';

header('Content-Type: application/json');

try {
    $structureId = isset($_GET['structure']) ? intval($_GET['structure']) : 0;
    if ($structureId <= 0) {
        echo json_encode([]);
        exit;
    }

    $serviceModel = new Service();
    $items = $serviceModel->getService($structureId);

    // Map to expected keys used by front-ends
    $services = array_map(function($row) {
        return [
            'idService' => isset($row['idService']) ? $row['idService'] : (isset($row['idservice']) ? $row['idservice'] : null),
            'designationService' => isset($row['designationService']) ? $row['designationService'] : (isset($row['designation']) ? $row['designation'] : '')
        ];
    }, $items);

    echo json_encode($services);
} catch (Exception $e) {
    error_log('Erreur get_services_by_structure: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
?>

