<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

// Créer une instance de la classe User
$structure = new Structure();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $idUser = $_POST['idUser'];
    $idStructure = $_POST['idStructure'];
    $voir = $_POST['voir'];

    
        // Appeler la fonction addUser si aucun doublon n'est trouvé
        if ($structure->addUserStructure($idUser,$idStructure,$voir)) {
            // Redirection avec succès et message Swal
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur autorisé avec succès'
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
                    text: 'Erreur lors de l\'ajout de l\'utilisateur.'
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
