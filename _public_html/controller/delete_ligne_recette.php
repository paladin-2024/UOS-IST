<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if (isset($_GET['id'])) {
    $idLigne = intval($_GET['id']);

    if ($idLigne > 0) {
        // Use the model method to delete the ligne de dépense
        if ($structure->deleteLigneRecette($idLigne)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Ligne de récette supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../budget/budget.recette.grp.edit';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression de la ligne de récette.'
                }).then(() => {
                    window.location.href = '../budget/budget.recette.grp.edit';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de ligne de récette invalide.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.grp.edit';
            });
        </script>";
    }
} else {
    header("Location: ../budget/budget.recette.grp.edit");
    exit();
}
?>