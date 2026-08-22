<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$userId=$_SESSION['id'];

// Create instances of necessary classes
$universite = new Universite();
$agent = new Agent();
$structure = new Structure();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
    $nomEnseignant = isset($_POST['nomEnseignant']) ? trim($_POST['nomEnseignant']) : '';
    $grade = isset($_POST['grade']) ? trim($_POST['grade']) : '';
    $idDepartement = isset($_POST['idDepartement']) ? intval($_POST['idDepartement']) : 0;
    $idSection = isset($_POST['idsection']) ? intval($_POST['idsection']) : 0;

    // Validate required fields
    if ($idAgent <= 0 || empty($nomEnseignant) || empty($grade) || $idDepartement <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs sont obligatoires.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
        exit();
    }


    // Check for duplicate teacher
    if ($universite->checkDuplicateTeacher($nomEnseignant, $idAgent)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un enseignant avec ces informations existe déjà.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
        exit();
    }

    
    try {
        // Call the function to add a teacher
        $teacherId = $universite->createTeacher($nomEnseignant, $grade, $idAgent, $idDepartement, $userId);
        
        // Si un ID de section a été fourni, ajouter l'affectation
        if ($teacherId && $idSection > 0) {
            $universite->addTeacherSection($teacherId, $idSection, 1); // 1 = section principale
        }
        
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Enseignant ajouté avec succès.'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
    } catch (Exception $e) {
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'enseignant: " . $e->getMessage() . "'
            }).then(() => {
                window.location.href = '../cours/enseignant';
            });
        </script>";
    }
} else {
    // Redirect if accessed directly without form submission
    header("Location: ../cours/enseignant");
    exit();
}
?>
