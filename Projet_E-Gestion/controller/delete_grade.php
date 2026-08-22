<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Grade.php';

// Créer une instance de la classe Grade
$grade = new Grade();

// Vérifier si l'ID du grade est fourni
if (isset($_GET['idgrade']) && !empty($_GET['idgrade'])) {
    $idgrade = intval($_GET['idgrade']);
    
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
    
    // Supprimer le grade
    if ($grade->deleteGrade($idgrade)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Grade supprimé avec succès.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du grade. Il est possible que ce grade soit utilisé par des agents.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
    }
} else {
    // Rediriger si l'ID n'est pas fourni
    header("Location: ../configuration/grade.add");
    exit();
}
?>
