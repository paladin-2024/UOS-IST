<?php
session_start();
require_once '../../config/Connexion.php';
require_once '../../models/DependanceServiceFrais.php';

header('Content-Type: application/json');

try {
    if (empty($_SESSION['id'])) {
        throw new Exception('Non authentifié');
    }

    $studentId = intval($_GET['student_id'] ?? 0);
    $serviceId = intval($_GET['service_id'] ?? 0);

    if ($studentId <= 0 || $serviceId <= 0) {
        throw new Exception('Paramètres invalides');
    }

    $dependanceModel = new DependanceServiceFrais();
    
    // Vérifier l'accès
    $resultat = $dependanceModel->verifierAccesService($studentId, $serviceId);
    
    // Enregistrer l'accès
    $connexion = Connexion::getInstance()->getPDO();
    $query = "SELECT matricule FROM etudiant WHERE idetudiant = :studentId";
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
    $stmt->execute();
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($etudiant) {
        $dependanceModel->enregistrerAcces(
            $serviceId,
            $studentId,
            $etudiant['matricule'],
            $resultat['acces'],
            $resultat['raison'],
            $resultat['frais_payes'] ?? [],
            $resultat['frais_manquants'] ?? []
        );
    }
    
    echo json_encode($resultat);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    error_log('verifier_acces_service.php: ' . $e->getMessage());
}
