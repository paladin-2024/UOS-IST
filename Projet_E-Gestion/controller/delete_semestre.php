
<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (isset($_GET['idsemestre'])) {
    $idsemestre = $_GET['idsemestre'];
    
    $universite = new Universite();
    
    // Tentative de suppression
    if ($universite->deleteSemestre($idsemestre)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le semestre a été supprimé avec succès.'
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
                text: 'Une erreur s\'est produite lors de la suppression du semestre.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
        exit();
    }
    
} else {
    // Accès direct au script sans paramètre
    header("Location: ../configuration/semestre");
    exit();
}
?>
