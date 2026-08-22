
<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (isset($_POST['editSemestreBtn'])) {
    $idsemestre = $_POST['idsemestre'];
    $numeroSemestre = trim($_POST['numeroSemestre']);
    $promotion_idpromotion = $_POST['promotion_idpromotion'];
    
    // Validation
    if (empty($numeroSemestre)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le numéro du semestre est requis.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
        exit();
    }
    
    if (empty($promotion_idpromotion)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez sélectionner une promotion.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
        exit();
    }
    
    $universite = new Universite();
    
    // Tentative de mise à jour
    if ($universite->updateSemestre($idsemestre, $numeroSemestre, $promotion_idpromotion)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le semestre a été modifié avec succès.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
        exit();
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite lors de la modification du semestre.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
        exit();
    }
    
} else {
    // Accès direct au script sans passer par le formulaire
    header("Location: ../configuration/semestre");
    exit();
}
?>
