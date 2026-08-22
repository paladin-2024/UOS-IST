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
        if ($structure->deleteLigneDepense($idLigne)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Ligne de dépense supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../budget/budget.depense.groupe.edit';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression de la ligne de dépense.'
                }).then(() => {
                    window.location.href = '../budget/budget.depense.groupe.edit';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de ligne de dépense invalide.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
    }
} else {
    header("Location: ../budget/budget.depense.groupe.edit");
    exit();
}
?>