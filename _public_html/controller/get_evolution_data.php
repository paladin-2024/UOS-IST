<?php
// Désactiver l'affichage des erreurs pour éviter de corrompre la réponse JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

try {
    // Valider le paramètre sectionId
    if (!isset($_GET['sectionId'])) {
        throw new Exception('Le paramètre sectionId est requis');
    }
    
    $sectionId = filter_input(INPUT_GET, 'sectionId', FILTER_VALIDATE_INT);
    
    if ($sectionId === false || $sectionId <= 0) {
        throw new Exception('ID de section invalide');
    }

    // Initialiser le modèle et récupérer les données
    $universite = new Universite();
    $data = $universite->getEvolutionBySection($sectionId);
    
    // Vérifier si des données ont été trouvées
    if (empty($data)) {
        echo json_encode([]);
        exit;
    }
    
    // Retourner les données (format simple attendu par le graphique)
    echo json_encode($data);
    
} catch (Exception $e) {
    // Loggez l'erreur
    error_log("Erreur dans get_evolution_data.php: " . $e->getMessage());
    
    // Définir le code de statut HTTP approprié
    http_response_code(500);
    
    // Retourner un message d'erreur au format JSON
    echo json_encode(['error' => $e->getMessage()]);
}
