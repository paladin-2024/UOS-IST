<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

// Créer une instance de la classe User
$structure = new Structure();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Récupérer les données du formulaire
    $idUser = $_GET['id'];

    
        // Appeler la fonction addUser si aucun doublon n'est trouvé
        if ($structure->deleteUserStructure($idUser)) {
            // Redirection avec succès et message Swal
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur supprimé avec succès'
                }).then(() => {
                    window.location.href = '../configuration/structure.add';
                });
            </script>";
        } else {
            // Message d'erreur avec Swal
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression de l\'utilisateur.'
                }).then(() => {
                    window.location.href = '../configuration/structure.add';
                });
            </script>";
        }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/structure.add");
    exit();
}
