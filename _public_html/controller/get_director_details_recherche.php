<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/UniteRecherche.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID invalide']);
    exit;
}

$directorId = intval($_GET['id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du directeur
    $query = "SELECT a.*, g.designation as grade, s.designation as service
              FROM agent a
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              LEFT JOIN service s ON a.idService = s.idService
              WHERE a.idAgent = :directorId";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':directorId', $directorId, PDO::PARAM_INT);
    $stmt->execute();
    
    $directorDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$directorDetails) {
        echo json_encode(['error' => 'Directeur non trouvé']);
        exit;
    }
    
    // Récupérer les spécialisations du directeur
    $querySpec = "SELECT s.designation, ur.designation_UR
                  FROM enseignant_specialisation es
                  INNER JOIN specialisation s ON es.idSpecialisation = s.idSpecialisation
                  INNER JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
                  WHERE es.idAgent = :directorId
                  ORDER BY s.designation";
    
    $stmtSpec = $db->prepare($querySpec);
    $stmtSpec->bindParam(':directorId', $directorId, PDO::PARAM_INT);
    $stmtSpec->execute();
    
    $specialisations = $stmtSpec->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les sujets dirigés
    $querySujets = "SELECT sj.intitule, sj.statut_validation, 
                           e.noms as nom_etudiant, aa.designation as annee_acad
                    FROM sujet sj
                    INNER JOIN etudiant e ON sj.idEtudiant = e.idetudiant
                    LEFT JOIN annee_acad aa ON sj.idAnneeAcad = aa.idannee_acad
                    WHERE sj.idDirecteur = :directorId
                    ORDER BY sj.dateCreation DESC";
    
    $stmtSujets = $db->prepare($querySujets);
    $stmtSujets->bindParam(':directorId', $directorId, PDO::PARAM_INT);
    $stmtSujets->execute();
    
    $sujets = $stmtSujets->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les statistiques
    $sujets_valides = count(array_filter($sujets, function($s) { return $s['statut_validation'] === 'Validé'; }));
    $sujets_en_attente = count(array_filter($sujets, function($s) { return $s['statut_validation'] === 'En attente'; }));
    $sujets_rejetes = count(array_filter($sujets, function($s) { return $s['statut_validation'] === 'Rejeté'; }));
    $total_etudiants = count($sujets);
    
    $directorDetails['specialisations'] = $specialisations;
    $directorDetails['sujets'] = $sujets;
    $directorDetails['sujets_valides'] = $sujets_valides;
    $directorDetails['sujets_en_attente'] = $sujets_en_attente;
    $directorDetails['sujets_rejetes'] = $sujets_rejetes;
    $directorDetails['total_etudiants'] = $total_etudiants;
    
    echo json_encode($directorDetails);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()]);
}
?>