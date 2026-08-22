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

// Récupération du paramètre optionnel d'année académique
$anneeId = isset($_GET['anneeId']) ? intval($_GET['anneeId']) : null;

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Construction de la requête SQL pour compter les sujets par statut
    $query = "SELECT 
                statut_validation as status, 
                COUNT(*) as count 
              FROM sujets 
              WHERE 1=1";
    
    // Ajouter le filtre par année académique si spécifié
    if ($anneeId) {
        $query .= " AND annee_acad_idannee_acad = :anneeId";
    }
    
    // Grouper par statut et ordonner
    $query .= " GROUP BY statut_validation 
                ORDER BY FIELD(statut_validation, 'Validé', 'En attente', 'Rejeté', 'Modifié')";
    
    $stmt = $db->prepare($query);
    
    // Lier le paramètre d'année si nécessaire
    if ($anneeId) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si aucun résultat, créer un ensemble de données vide mais structuré
    if (empty($results)) {
        $results = [
            ['status' => 'Validé', 'count' => 0],
            ['status' => 'En attente', 'count' => 0],
            ['status' => 'Rejeté', 'count' => 0],
            ['status' => 'Modifié', 'count' => 0]
        ];
    }
    
    // Vérifier que tous les statuts sont représentés
    $allStatuses = ['Validé', 'En attente', 'Rejeté', 'Modifié'];
    $existingStatuses = array_column($results, 'status');
    
    foreach ($allStatuses as $status) {
        if (!in_array($status, $existingStatuses)) {
            $results[] = ['status' => $status, 'count' => 0];
        }
    }
    
    // Tri final pour maintenir l'ordre des statuts
    usort($results, function($a, $b) use ($allStatuses) {
        return array_search($a['status'], $allStatuses) - array_search($b['status'], $allStatuses);
    });
    
    // Envoi de la réponse JSON
    header('Content-Type: application/json');
    echo json_encode($results);
    
} catch (Exception $e) {
    // Gestion des erreurs
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur lors de la récupération des statistiques',
        'message' => $e->getMessage()
    ]);
}
?>
