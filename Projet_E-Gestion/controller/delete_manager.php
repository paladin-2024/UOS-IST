<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['idresponsable_section'])) {
    $managerId = intval($_GET['idresponsable_section']);

    if ($managerId > 0) {
        $deleteSuccess = $universite->deleteManager($managerId);

        if ($deleteSuccess) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Manager supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/faculte';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression du manager.'
                }).then(() => {
                    window.location.href = '../configuration/faculte';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de manager invalide.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/faculte");
    exit();
}
?>