<?php
session_start();
require_once dirname(__DIR__).'/config/config.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__). '/models/Universite.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$universite = new Universite();
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    if ($action === 'missing_fields') {
        // Get missing fields statistics
        $stats = $universite->getProfileCompletionStats();
        $missingFieldsStats = $stats['missingFieldsStats'];
        
        echo json_encode([
            'labels' => [
                'Photo', 
                'Lieu de naissance', 
                'Date de naissance', 
                'Email', 
                'Téléphone', 
                'Adresse', 
                'Personne contact', 
                'Téléphone contact'
            ],
            'data' => [
                $missingFieldsStats['photo'],
                $missingFieldsStats['lieuNaissance'],
                $missingFieldsStats['dateNaissance'],
                $missingFieldsStats['adressemail'],
                $missingFieldsStats['telephone'],
                $missingFieldsStats['adresse'],
                $missingFieldsStats['personne_contact'],
                $missingFieldsStats['telephone_contact']
            ]
        ]);
    } 
    else if ($action === 'completion_status') {
        // Get overall completion statistics
        $stats = $universite->getProfileCompletionStats();
        
        echo json_encode([
            'labels' => ['Complet', 'Presque complet', 'En cours', 'Non commencé'],
            'data' => [
                $stats['completeCount'],
                $stats['partialCount'],
                $stats['inProgressCount'],
                $stats['notStartedCount']
            ]
        ]);
    }
    else if ($action === 'completion_by_promotion') {
        // Get completion statistics by promotion
        $statsByPromotion = $universite->getCompletionStatsByPromotion();
        
        $promotions = [];
        $completeData = [];
        $partialData = [];
        $inProgressData = [];
        $notStartedData = [];
        
        foreach ($statsByPromotion as $stat) {
            $promotions[] = $stat['promotion'];
            $completeData[] = $stat['complete'];
            $partialData[] = $stat['partial'];
            $inProgressData[] = $stat['inProgress'];
            $notStartedData[] = $stat['notStarted'];
        }
        
        echo json_encode([
            'labels' => $promotions,
            'datasets' => [
                [
                    'label' => 'Complet',
                    'backgroundColor' => 'rgba(40, 167, 69, 0.8)',
                    'data' => $completeData
                ],
                [
                    'label' => 'Presque complet',
                    'backgroundColor' => 'rgba(23, 162, 184, 0.8)',
                    'data' => $partialData
                ],
                [
                    'label' => 'En cours',
                    'backgroundColor' => 'rgba(255, 193, 7, 0.8)',
                    'data' => $inProgressData
                ],
                [
                    'label' => 'Non commencé',
                    'backgroundColor' => 'rgba(220, 53, 69, 0.8)',
                    'data' => $notStartedData
                ]
            ]
        ]);
    }
    else {
        echo json_encode(['error' => 'Action non reconnue']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
?>
