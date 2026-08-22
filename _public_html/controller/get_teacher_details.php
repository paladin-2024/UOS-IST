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
    echo json_encode(['error' => 'ID de l\'enseignant non valide']);
    exit;
}

$teacherId = intval($_GET['id']);
$universite = new Universite();
$agentModel = new Agent();

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupération des informations de base de l'enseignant
    $queryTeacher = "SELECT a.*, g.designation as grade, s.designationSection 
                    FROM agent a 
                    LEFT JOIN grade g ON a.grade_id = g.idgrade
                    LEFT JOIN agent_section ags ON a.idAgent = ags.idAgent
                    LEFT JOIN section s ON ags.idsection = s.idsection
                    WHERE a.idAgent = :id 
                    AND a.type_agent = 'Enseignant'
                    AND (ags.estPrincipal = 1 OR ags.estPrincipal IS NULL)
                    LIMIT 1";
    
    $stmtTeacher = $db->prepare($queryTeacher);
    $stmtTeacher->bindParam(':id', $teacherId, PDO::PARAM_INT);
    $stmtTeacher->execute();
    
    $teacherDetails = $stmtTeacher->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacherDetails) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Enseignant non trouvé']);
        exit;
    }
    
    // Récupération des étudiants encadrés (comme directeur ou encadreur)
    $queryStudents = "SELECT e.idetudiant, e.noms, e.matricule, e.photo, 
                      s.idsujets, s.intitule, s.statut_validation, s.etatSujet,
                      s.annee_acad_idannee_acad, aa.designation as annee_academique,
                      sec.designationSection, sp.designation as specialisation
                      FROM sujets s
                      INNER JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                      LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                      LEFT JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                      LEFT JOIN section sec ON sp.idsection = sec.idsection
                      WHERE (s.idDirecteur = :id OR s.idEncadreur = :id)
                      ORDER BY aa.designation DESC, e.noms ASC";
    
    $stmtStudents = $db->prepare($queryStudents);
    $stmtStudents->bindParam(':id', $teacherId, PDO::PARAM_INT);
    $stmtStudents->execute();
    
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupération des spécialisations de l'enseignant
    $querySpecialisations = "SELECT s.idSpecialisation, s.designation, 
                            ur.designation_UR, sec.designationSection
                            FROM enseignant_specialisation es
                            INNER JOIN specialisation s ON es.idSpecialisation = s.idSpecialisation
                            INNER JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
                            INNER JOIN section sec ON s.idsection = sec.idsection
                            WHERE es.idAgent = :id
                            ORDER BY sec.designationSection, s.designation";
    
    $stmtSpecialisations = $db->prepare($querySpecialisations);
    $stmtSpecialisations->bindParam(':id', $teacherId, PDO::PARAM_INT);
    $stmtSpecialisations->execute();
    
    $specialisations = $stmtSpecialisations->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul des statistiques
    $sujets_valides = 0;
    $sujets_en_attente = 0;
    $total_etudiants = count($students);
    
    foreach ($students as $student) {
        if ($student['statut_validation'] === 'Validé') {
            $sujets_valides++;
        } else if ($student['statut_validation'] === 'En attente') {
            $sujets_en_attente++;
        }
    }
    
    // Construction de la réponse complète
    $response = $teacherDetails;
    $response['students'] = $students;
    $response['specialisations'] = $specialisations;
    $response['sujets_valides'] = $sujets_valides;
    $response['sujets_en_attente'] = $sujets_en_attente;
    $response['total_etudiants'] = $total_etudiants;
    
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
