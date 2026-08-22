<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Cours.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Récupérer l'ID du cours
$idCours = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idCours <= 0) {
    echo json_encode(['error' => 'ID de cours invalide']);
    exit();
}

$cours = new Cours();
$courseDetails = $cours->getCoursById($idCours);

if (!$courseDetails) {
    echo json_encode(['error' => 'Cours non trouvé']);
    exit();
}

// Récupérer les chapitres et ressources
$chapters = $cours->getChaptersByCourse($idCours);
$assignments = $cours->getAssignmentsByCourse($idCours);

// Vérifier l'accès aux ressources payantes
$etudiantId = $_SESSION['student_id'];
foreach ($chapters as &$chapter) {
    if (isset($chapter['ressources']) && is_array($chapter['ressources'])) {
        foreach ($chapter['ressources'] as &$ressource) {
            if ($ressource['est_payant']) {
                $ressource['access_granted'] = $cours->hasAccessToResource($etudiantId, $ressource['idressource']);
            } else {
                $ressource['access_granted'] = true;
            }
        }
    }
}

// Vérifier l'accès aux devoirs payants
foreach ($assignments as &$assignment) {
    if ($assignment['est_payant']) {
        $assignment['access_granted'] = $cours->hasAccessToAssignment($etudiantId, $assignment['iddevoir']);
    } else {
        $assignment['access_granted'] = true;
    }
    
    // Récupérer la réponse de l'étudiant s'il y en a une
    $assignment['reponse'] = $cours->getStudentResponse($etudiantId, $assignment['iddevoir']);
}

// Renvoyer les données au format JSON
echo json_encode([
    'course' => $courseDetails,
    'chapters' => $chapters,
    'assignments' => $assignments
]);
exit();

