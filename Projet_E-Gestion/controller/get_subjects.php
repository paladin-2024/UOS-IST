<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

header('Content-Type: application/json');

// Vérification de la session
if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

try {
    $etudiant = new Etudiant();
    
    // Récupération des sujets disponibles pour le département et le cycle de l'étudiant
    $sujets = $etudiant->getSujetsDisponibles(
        $_SESSION['departement_id'],
        $_SESSION['cycle']
    );
    
    // Ajout d'informations supplémentaires pour chaque sujet
    $sujets = array_map(function($sujet) {
        // Formatage de la date
        $sujet['dateCreation'] = date('Y-m-d H:i:s', strtotime($sujet['dateCreation']));
        
        // Définition de l'état du sujet
        if (!$sujet['etudiant_idetudiant']) {
            $sujet['etatSujet'] = 'Disponible';
        } else if ($sujet['etudiant_idetudiant'] == $_SESSION['student_id']) {
            $sujet['etatSujet'] = 'Attribué';
        } else {
            $sujet['etatSujet'] = 'Non disponible';
        }
        
        return $sujet;
    }, $sujets);
    
    echo json_encode($sujets);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}