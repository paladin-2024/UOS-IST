<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

try {
    // Récupérer les données du QR code
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['matricule']) || !isset($data['timestamp']) || !isset($data['hash'])) {
        throw new Exception("Données de carte invalides");
    }
    
    // Vérifier le hash
    $expectedHash = hash('sha256', $data['matricule'] . $_ENV['CONFIG_SECRET_KEY'] . $data['timestamp']);
    if ($data['hash'] !== $expectedHash) {
        throw new Exception("Signature de carte invalide");
    }
    
    // Vérifier l'étudiant
    $universite = new Universite();
    $etudiant = $universite->getEtudiantById($data['id']);
    
    if (!$etudiant || $etudiant['matricule'] !== $data['matricule']) {
        throw new Exception("Étudiant non trouvé ou données incohérentes");
    }
    
    // Vérifier si la carte n'est pas expirée (par exemple, 1 an)
    $cardTime = $data['timestamp'];
    $currentTime = time();
    $oneYearInSeconds = 365 * 24 * 60 * 60;
    
    if ($currentTime - $cardTime > $oneYearInSeconds) {
        echo json_encode([
            'valid' => false,
            'message' => 'Carte expirée'
        ]);
        exit();
    }
    
    // Carte valide
    echo json_encode([
        'valid' => true,
        'etudiant' => [
            'matricule' => $etudiant['matricule'],
            'nom' => $etudiant['noms'],
            'promotion' => $etudiant['designationPromotion'],
            'annee' => $etudiant['annee']
        ]
    ]);
    
} catch (Exception $e) {    echo json_encode([
        'valid' => false,
        'message' => $e->getMessage()
    ]);
}
