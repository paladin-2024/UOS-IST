<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Créer une instance de la classe Agent
$agent = new Agent();

// Vérifier si la requête est de type GET
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Récupérer l'ID de l'agent à supprimer
    $idAgent = isset($_GET['idAgent']) ? intval($_GET['idAgent']) : 0;

    // Vérifier si l'ID est valide
    if ($idAgent <= 0) {
        // Message d'erreur pour ID invalide
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de l\'agent invalide.'
            }).then(() => {
                window.location.href = '../grh/agent.add';
            });
        </script>";
        exit();
    }

    // Appeler la fonction deleteAgent
    if ($agent->deleteAgent($idAgent)) {
        // Redirection avec succès et message Swal
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Agent supprimé avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.add';
            });
        </script>";
    } else {
        // Message d'erreur avec Swal
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'agent.'
            }).then(() => {
                window.location.href = '../grh/agent.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../grh/agent.add");
    exit();
}
?>