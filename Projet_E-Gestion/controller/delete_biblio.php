<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $universite = new Universite();
        

        // Récupérer l'ID du travail à supprimer
        $id = $_GET['id'];

        // Récupérer les informations du travail avant suppression
        $travail = $universite->getTravailById($id);
        if (!$travail) {
            throw new Exception('Travail non trouvé.');
        }

        // Supprimer le travail
        $result = $universite->deleteTravail($id);

        if ($result) {
            
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le travail scientifique a été supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../bibliotheque/bilio_add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la suppression du travail.');
        }

    } catch (Exception $e) {
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
} else {
    header("Location: ../bibliotheque/bilio_add");
    exit();
}
?>