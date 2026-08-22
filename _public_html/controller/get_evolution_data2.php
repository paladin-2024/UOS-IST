<?php
// Initialisation de la session et vérification de connexion
session_start();
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Inclusion des fichiers nécessaires
require_once '../config/Connexion.php';
require_once '../models/Universite.php';

// Vérification du paramètre de section
$sectionId = isset($_GET['sectionId']) ? intval($_GET['sectionId']) : null;

// Si aucune section n'est spécifiée, on peut renvoyer les données globales
if (!$sectionId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de section requis']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les 5 dernières années académiques
    $queryAnnees = "SELECT idannee_acad, designation as annee 
                    FROM annee_acad 
                    ORDER BY designation DESC 
                    LIMIT 5";
    
    $stmtAnnees = $db->prepare($queryAnnees);
    $stmtAnnees->execute();
    $annees = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);
    
    // Inverser pour avoir les années dans l'ordre chronologique
    $annees = array_reverse($annees);
    
    // Résultats à renvoyer
    $evolutionData = [];
    
    foreach ($annees as $annee) {
        $anneeId = $annee['idannee_acad'];
        
        // Compte total des étudiants dans la section pour cette année
        $queryTotal = "SELECT COUNT(DISTINCT s.etudiant_idetudiant) as total_etudiants
                       FROM sujets s
                       INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                       WHERE sp.idsection = :sectionId
                       AND s.annee_acad_idannee_acad = :anneeId
                       AND s.etudiant_idetudiant IS NOT NULL";
        
        $stmtTotal = $db->prepare($queryTotal);
        $stmtTotal->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
        $stmtTotal->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmtTotal->execute();
        $resultTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
        
        // Compte des étudiants avec sujets validés
        $queryValides = "SELECT COUNT(DISTINCT s.etudiant_idetudiant) as etudiants_valides
                         FROM sujets s
                         INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                         WHERE sp.idsection = :sectionId
                         AND s.annee_acad_idannee_acad = :anneeId
                         AND s.statut_validation = 'Validé'
                         AND s.etudiant_idetudiant IS NOT NULL";
        
        $stmtValides = $db->prepare($queryValides);
        $stmtValides->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
        $stmtValides->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmtValides->execute();
        $resultValides = $stmtValides->fetch(PDO::FETCH_ASSOC);
        
        // Ajouter les données pour cette année
        $evolutionData[] = [
            'annee' => $annee['annee'],
            'total_etudiants' => $resultTotal['total_etudiants'] ?? 0,
            'etudiants_valides' => $resultValides['etudiants_valides'] ?? 0
        ];
    }
    
    // Vérifier si nous avons des données
    if (empty($evolutionData) || array_sum(array_column($evolutionData, 'total_etudiants')) == 0) {
        // Créer des données vides structurées
        $evolutionData = [];
        foreach ($annees as $annee) {
            $evolutionData[] = [
                'annee' => $annee['annee'],
                'total_etudiants' => 0,
                'etudiants_valides' => 0
            ];
        }
    }
    
    // Envoi de la réponse JSON
    header('Content-Type: application/json');
    echo json_encode($evolutionData);
    
} catch (Exception $e) {
    // Gestion des erreurs
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur lors de la récupération des données d\'évolution',
        'message' => $e->getMessage()
    ]);
}
?>
