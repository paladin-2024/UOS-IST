<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

try {
    // Vérifier si la requête est en POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Vérifier si l'ID est fourni
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('ID du travail non fourni');
    }

    

    $id = intval($_POST['id']);
    $universite = new Universite();
    
    // Récupérer les informations du travail avant validation
    $travail = $universite->getTravailById($id);
    if (!$travail) {
        throw new Exception('Travail non trouvé');
    }

    // Vérifier si le travail n'est pas déjà validé
    if ($travail['statut'] === 'Validé') {
        throw new Exception('Ce travail a déjà été validé');
    }

   

    // Valider le travail
    // Par défaut, on met le travail en public lors de la validation
    $result = $universite->validerTravail($id, true);

    if (!$result) {
        throw new Exception('Erreur lors de la validation du travail');
    }

    

    // Préparer la réponse
    $response = [
        'success' => true,
        'message' => 'Le travail a été validé avec succès',
        'data' => [
            'id' => $id,
            'titre' => $travail['titre'],
            'statut' => 'Validé',
            'date_validation' => date('Y-m-d H:i:s')
        ]
    ];

    // Logger l'action
    $user = $_SESSION['user']['nom'] ?? 'Utilisateur inconnu';
    $logMessage = sprintf(
        "[%s] Validation du travail ID:%d - '%s' par %s",
        date('Y-m-d H:i:s'),
        $id,
        $travail['titre'],
        $user
    );
    error_log($logMessage);

    echo json_encode($response);

} catch (Exception $e) {
    

    // Logger l'erreur
    error_log("Erreur lors de la validation du travail : " . $e->getMessage());

    // Renvoyer l'erreur au format JSON
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}