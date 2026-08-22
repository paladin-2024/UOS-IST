<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Retrieve the email ID from the query string
    $emailId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Validate the email ID
    if ($emailId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de courriel invalide.'
            }).then(() => {
                window.location.href = '../reception/courriel.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        if ($structure->deleteEmail($emailId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Courriel supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../reception/courriel.add';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de la suppression du courriel.");
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du courriel: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '..../reception/courriel.add';
            });
        </script>";
    }
} else {
    header("Location: ../reception/courriel.add");
    exit();
}
?>