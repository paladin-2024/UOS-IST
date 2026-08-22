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
require_once '../models/Agent.php';

// Vérification du paramètre ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de l\'unité de recherche non valide']);
    exit;
}

$urId = intval($_GET['id']);
$universite = new Universite();
$agentModel = new Agent();

try {
    // Récupération des informations de base de l'unité de recherche
    $db = Connexion::getInstance()->getPDO();
    
    $queryUR = "SELECT ur.*, o.designationOrientation as departement 
                FROM unite_recherche ur
                LEFT JOIN specialisation s ON s.idUnite_recherche = ur.idunite_recherche
                LEFT JOIN orientation o ON s.idsection = o.section_idsection
                WHERE ur.idunite_recherche = :id
                LIMIT 1";
    
    $stmtUR = $db->prepare($queryUR);
    $stmtUR->bindParam(':id', $urId, PDO::PARAM_INT);
    $stmtUR->execute();
    
    $urDetails = $stmtUR->fetch(PDO::FETCH_ASSOC);
    
    if (!$urDetails) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unité de recherche non trouvée']);
        exit;
    }
    
    // Récupération des enseignants associés
    $queryTeachers = "SELECT DISTINCT a.idAgent, a.noms, a.email, a.telephone, a.photo, a.niveauEtude, a.matricule, g.designation as grade
                     FROM agent a
                     INNER JOIN enseignant_specialisation es ON a.idAgent = es.idAgent
                     INNER JOIN specialisation s ON es.idSpecialisation = s.idSpecialisation
                     LEFT JOIN grade g ON a.grade_id = g.idgrade
                     WHERE s.idUnite_recherche = :id
                     AND a.type_agent = 'Enseignant'
                     ORDER BY a.noms";
    
    $stmtTeachers = $db->prepare($queryTeachers);
    $stmtTeachers->bindParam(':id', $urId, PDO::PARAM_INT);
    $stmtTeachers->execute();
    
    $teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupération des sections associées
    $querySections = "SELECT s.idsection, s.designationSection
                     FROM section s
                     INNER JOIN unite_recherche_section urs ON s.idsection = urs.idsection
                     WHERE urs.idunite_recherche = :id
                     ORDER BY s.designationSection";
    
    $stmtSections = $db->prepare($querySections);
    $stmtSections->bindParam(':id', $urId, PDO::PARAM_INT);
    $stmtSections->execute();
    
    $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
    
    // Construction de la réponse complète
    $response = $urDetails;
    $response['teachers'] = $teachers;
    $response['sections'] = $sections;
    
    // Envoi de la réponse JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Gestion des erreurs
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur lors de la récupération des données',
        'message' => $e->getMessage()
    ]);
}
?>
