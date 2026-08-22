<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/UniteRecherche.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID invalide']);
    exit;
}

$urId = intval($_GET['id']);
$uniteRecherche = new UniteRecherche();

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails de l'unité de recherche
    $query = "SELECT ur.*, COUNT(DISTINCT es.\"idAgent\") as nombre_enseignants,
                     COUNT(DISTINCT s.\"idSpecialisation\") as nombre_specialisations
              FROM unite_recherche ur
              LEFT JOIN specialisation s ON ur.idunite_recherche = s.\"idUnite_recherche\"
              LEFT JOIN enseignant_specialisation es ON s.\"idSpecialisation\" = es.\"idSpecialisation\"
              WHERE ur.idunite_recherche = :urId
              GROUP BY ur.idunite_recherche";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':urId', $urId, PDO::PARAM_INT);
    $stmt->execute();
    
    $urDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$urDetails) {
        echo json_encode(['error' => 'Unité de recherche non trouvée']);
        exit;
    }
    
    // Récupérer les spécialisations
    $querySpec = "SELECT s.*, COUNT(es.\"idAgent\") as nombre_enseignants
                  FROM specialisation s
                  LEFT JOIN enseignant_specialisation es ON s.\"idSpecialisation\" = es.\"idSpecialisation\"
                  WHERE s.\"idUnite_recherche\" = :urId
                  GROUP BY s.\"idSpecialisation\"
                  ORDER BY s.designation";
    
    $stmtSpec = $db->prepare($querySpec);
    $stmtSpec->bindParam(':urId', $urId, PDO::PARAM_INT);
    $stmtSpec->execute();
    
    $specialisations = $stmtSpec->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les enseignants
    $queryEns = "SELECT DISTINCT a.noms, a.matricule, g.designation as grade, 
                        s.designation as specialisation
                 FROM agent a
                 INNER JOIN enseignant_specialisation es ON a.\"idAgent\" = es.\"idAgent\"
                 INNER JOIN specialisation s ON es.\"idSpecialisation\" = s.\"idSpecialisation\"
                 LEFT JOIN grade g ON a.grade_id = g.idgrade
                 WHERE s.\"idUnite_recherche\" = :urId
                 ORDER BY a.noms";
    
    $stmtEns = $db->prepare($queryEns);
    $stmtEns->bindParam(':urId', $urId, PDO::PARAM_INT);
    $stmtEns->execute();
    
    $enseignants = $stmtEns->fetchAll(PDO::FETCH_ASSOC);
    
    $urDetails['specialisations'] = $specialisations;
    $urDetails['enseignants'] = $enseignants;
    
    echo json_encode($urDetails);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()]);
}
?>