<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Service.php';

// Créer une instance de la classe Service
$service = new Service();

// Vérifier si la requête est de type GET
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Récupérer l'ID du service à supprimer
    $idService = isset($_GET['idService']) ? intval($_GET['idService']) : 0;

    // Vérifier si l'ID est valide
    if ($idService <= 0) {
        // Message d'erreur pour ID invalide
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID du service invalide.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
        exit();
    }

    // Appeler la fonction deleteService
    if ($service->deleteService($idService)) {
        // Redirection avec succès et message Swal
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Service supprimé avec succès.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
    } else {
        // Message d'erreur avec Swal
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du service.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/service.add");
    exit();
}
?>
