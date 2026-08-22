<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if (isset($_GET['idbureau'])) {
    $juryId = intval($_GET['idbureau']);
    
    $result = $universite->deleteJury($juryId);
    
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Bureau de jury supprimé avec succès.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du bureau de jury.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/jury");
    exit();
}
?>
