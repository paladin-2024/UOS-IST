<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Grade.php';

// Créer une instance de la classe Grade
$grade = new Grade();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $idgrade = isset($_POST['idgrade']) ? intval($_POST['idgrade']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $type_agent = isset($_POST['type_agent']) ? trim($_POST['type_agent']) : '';

    // Validation des champs requis
    if (empty($designation) || empty($type_agent) || $idgrade <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
        exit();
    }

    // Vérifier si le grade existe
    $gradeInfo = $grade->getGradeById($idgrade);
    if (!$gradeInfo) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le grade sélectionné est invalide.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour le grade (sauf pour le grade en cours de modification)
    $existingGrade = $grade->checkDuplicateGrade($designation, $type_agent);
    $sameGrade = ($gradeInfo['designation'] === $designation && $gradeInfo['type_agent'] === $type_agent);
    
    if ($existingGrade && !$sameGrade) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un grade avec ce nom existe déjà pour ce type d\'agent.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
        exit();
    }

    // Appeler la fonction de mise à jour du grade
    if ($grade->updateGrade($idgrade, $designation, $description, $type_agent)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Grade mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du grade.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/grade.add");
    exit();
}
?>
