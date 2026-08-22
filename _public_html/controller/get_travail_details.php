<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (isset($_GET['id'])) {
    try {
        $universite = new Universite();
        $travail = $universite->getTravailById($_GET['id']);
        
        if ($travail) {
            // Ajouter les statistiques de consultation
            $travail['stats_consultations'] = $universite->getConsultationsStats($travail['id']);
            
            // Renvoyer les données au format JSON
            header('Content-Type: application/json');
            echo json_encode($travail);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Travail non trouvé']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'ID non fourni']);
}