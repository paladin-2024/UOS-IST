<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupération de l'ID du frais
    $id = $_GET['id'] ?? '';
    $idUser = $_SESSION['id'] ?? null;

    // Validation de l'ID
    if (empty($id) || $idUser === null) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Identifiant du frais non valide.'
            }).then(() => {
                window.location.href = '../frais/frais_add';
            });
        </script>";
        exit();
    }

    // Vérification de l'existence du frais
    $fraisExistant = $universite->getFraisById($id);
    if (!$fraisExistant) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le frais que vous essayez de supprimer n\'existe pas.'
            }).then(() => {
                window.location.href = '../frais/frais_add';
            });
        </script>";
        exit();
    }

    try {
        // Tentative de suppression du frais
        $result = $universite->deleteFrais(intval($id));

        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le frais a été supprimé avec succès.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '../frais/frais_add';
                });
            </script>";
        } else {
            // Si la suppression échoue à cause des paiements associés
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Impossible de supprimer',
                    text: 'Ce frais ne peut pas être supprimé car il est associé à des paiements existants.',
                    showConfirmButton: true
                }).then(() => {
                    window.location.href = '../frais/frais_add';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la suppression du frais : " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../frais/frais_add';
            });
        </script>";
    }
    exit();
} else {
    // Redirection si la méthode n'est pas GET
    header("Location: ../frais/frais_add");
    exit();
}
?>