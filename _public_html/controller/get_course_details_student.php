<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id']) && !isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$idEcue = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idEcue <= 0) {
    echo json_encode(['error' => 'ID de cours invalide']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $ecue = new Ecue();
    $universite = new Universite();
    $etudiant = new Etudiant();
    
    // Récupérer l'année académique actuelle
    $currentYear = $universite->getCurrentAcademicYear();
    $idAnneeAcad = $currentYear['idannee_acad'] ?? 0;
    
    // Récupérer l'ID de l'étudiant depuis la session
    $studentId = $_SESSION['student_id'] ?? 0;
    
    // Récupérer les détails du cours
    $courseDetails = $ecue->getEcueById($idEcue);
    
    if (!$courseDetails) {
        echo json_encode(['error' => 'Cours non trouvé']);
        exit;
    }
    
    // Récupérer les enseignants
    $enseignants = $ecue->getEnseignantsByEcue($idEcue, $idAnneeAcad);
    
    // Récupérer les supports de cours
    $supports = $ecue->getSupportsByEcue($idEcue);
    
    // Vérifier l'accès aux supports payants pour chaque support
    foreach ($supports as &$support) {
        $support['access_granted'] = true; // Default to true
        
        if ($support['est_payant'] && isset($support['idfrais']) && !empty($support['idfrais'])) {
            // Vérifier si l'étudiant a payé les frais associés
            $hasPaid = $etudiant->hasPaidFrais($studentId, $support['idfrais']);
            $support['access_granted'] = $hasPaid;
        }
    }
    unset($support); // Important pour ne pas modifier le tableau original par référence
    
    // Récupérer les chapitres
    $chapters = $ecue->getChaptersByEcue($idEcue);
    
    // Récupérer les devoirs/Travaux Pratiques
    $travaux = $ecue->getAssignmentsByEcue($idEcue, $idAnneeAcad);
    
    // Vérifier l'accès aux travaux payants et enrichir avec les infos de groupe
    foreach ($travaux as &$travail) {
        $travail['access_granted'] = true;
        
        if ($travail['est_payant'] && isset($travail['idfrais']) && !empty($travail['idfrais'])) {
            $hasPaid = $etudiant->hasPaidFrais($studentId, $travail['idfrais']);
            $travail['access_granted'] = $hasPaid;
        }
        
        // Pour les travaux de groupe, vérifier si l'étudiant appartient à un groupe
        if (($travail['type_travail'] ?? '') === 'groupe') {
            $travail['is_group_work'] = true;
            $travail['groupe_formé'] = false;
            $travail['groupe_paye'] = false;
            $travail['fichier_groupe'] = null;
            $travail['groupe_info'] = null;
            
            // Chercher le groupe de l'étudiant pour ce devoir
            $stmtGroupe = $db->prepare("SELECT gt.* FROM groupes_travail gt
                INNER JOIN membres_groupe_travail mgt ON gt.id_groupe = mgt.id_groupe
                WHERE gt.id_devoir = :idDevoir AND mgt.id_etudiant = :idEtudiant");
            $stmtGroupe->bindParam(':idDevoir', $travail['iddevoir'], PDO::PARAM_INT);
            $stmtGroupe->bindParam(':idEtudiant', $studentId, PDO::PARAM_INT);
            $stmtGroupe->execute();
            $groupe = $stmtGroupe->fetch(PDO::FETCH_ASSOC);
            
            if ($groupe) {
                $travail['groupe_formé'] = true;
                $travail['groupe_paye'] = (bool) $groupe['est_paye'];
                $travail['groupe_info'] = [
                    'id_groupe' => $groupe['id_groupe'],
                    'numero_groupe' => $groupe['numero_groupe']
                ];
                
                // Si le groupe a payé, accorder l'accès
                if ($groupe['est_paye']) {
                    $travail['access_granted'] = true;
                }
                
                // Chercher le fichier spécifique au groupe si fichier_par_groupe est activé
                if (!empty($travail['fichier_par_groupe'])) {
                    $stmtFichier = $db->prepare("SELECT fichier FROM fichiers_groupes_travail 
                        WHERE id_devoir = :idDevoir AND numero_groupe = :numGroupe");
                    $stmtFichier->bindParam(':idDevoir', $travail['iddevoir'], PDO::PARAM_INT);
                    $stmtFichier->bindParam(':numGroupe', $groupe['numero_groupe'], PDO::PARAM_INT);
                    $stmtFichier->execute();
                    $fichierGroupe = $stmtFichier->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fichierGroupe) {
                        $travail['fichier_groupe'] = $fichierGroupe['fichier'];
                    }
                }
            }
        }
    }
    unset($travail);
    
    // Pour chaque chapitre, récupérer les ressources
    foreach ($chapters as &$chapter) {
        $ressources = $ecue->getRessourcesByChapter($chapter['idpartie']);
        
        // Vérifier l'accès aux ressources payantes
        foreach ($ressources as &$ressource) {
            $ressource['access_granted'] = true;
            
            if ($ressource['est_payant'] && isset($ressource['idfrais']) && !empty($ressource['idfrais'])) {
                $hasPaid = $etudiant->hasPaidFrais($studentId, $ressource['idfrais']);
                $ressource['access_granted'] = $hasPaid;
            }
        }
        unset($ressource);
        
        $chapter['ressources'] = $ressources;
    }
    unset($chapter);
    
    // Retourner les données
    echo json_encode([
        'course' => $courseDetails,
        'enseignants' => $enseignants,
        'supports' => $supports,
        'chapters' => $chapters,
        'devoirs' => $travaux
    ]);
    
} catch (Exception $e) {
    error_log("Erreur dans get_course_details_student.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors du chargement des détails du cours: ' . $e->getMessage()]);
}
