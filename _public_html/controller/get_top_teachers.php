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

// Nombre maximum d'enseignants à récupérer (par défaut 10)
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Construction de la requête SQL pour obtenir les enseignants avec le plus d'étudiants
    $query = "SELECT 
                a.\"idAgent\",
                a.noms as \"nomEnseignant\",
                g.designation as grade,
                s.\"designationSection\",
                s.idsection,
                COUNT(DISTINCT sj.etudiant_idetudiant) as total_etudiants,
                SUM(CASE WHEN sj.statut_validation = 'Validé' THEN 1 ELSE 0 END) as sujets_valides,
                SUM(CASE WHEN sj.statut_validation = 'En attente' THEN 1 ELSE 0 END) as sujets_en_attente
              FROM agent a
              LEFT JOIN agent_section ags ON a.\"idAgent\" = ags.\"idAgent\" AND ags.\"estPrincipal\" = 1
              LEFT JOIN section s ON ags.idsection = s.idsection
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              LEFT JOIN sujets sj ON (a.\"idAgent\" = sj.\"idDirecteur\" OR a.\"idAgent\" = sj.\"idEncadreur\")";
    
    // Ajouter le filtre par année académique si spécifié
    if ($anneeId) {
        $query .= " AND sj.annee_acad_idannee_acad = :anneeId";
    }
    
    $query .= " WHERE a.type_agent = 'Enseignant'
                GROUP BY a.\"idAgent\", a.noms, g.designation, s.\"designationSection\", s.idsection
                HAVING COUNT(DISTINCT sj.etudiant_idetudiant) > 0
                ORDER BY total_etudiants DESC
                LIMIT :limit";
    
    $stmt = $db->prepare($query);
    
    // Lier les paramètres
    if ($anneeId) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le taux de validation pour chaque enseignant
    foreach ($results as &$teacher) {
        $total = intval($teacher['total_etudiants']);
        $valides = intval($teacher['sujets_valides']);
        $teacher['taux_validation'] = $total > 0 ? round(($valides / $total) * 100) : 0;
    }
    
    // Envoi de la réponse JSON
    header('Content-Type: application/json');
    echo json_encode($results);
    
} catch (Exception $e) {
    // Gestion des erreurs
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur lors de la récupération des données',
        'message' => $e->getMessage()
    ]);
}
?>
