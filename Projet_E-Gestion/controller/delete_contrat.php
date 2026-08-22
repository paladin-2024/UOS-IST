<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Create an instance of the Agent class
$agent = new Agent();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the contract ID from the form
    $idContratAgent = isset($_POST['idContratAgent']) ? trim($_POST['idContratAgent']) : '';

    // Validate the ID
    if (empty($idContratAgent)) {
        // Error message if ID is missing
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID du contrat manquant.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
        exit();
    }

    // Call the function to delete the contract
    if ($agent->deleteContract($idContratAgent)) {
        // Success message with SweetAlert
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Contrat supprimé avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
    } else {
        // Error message with SweetAlert
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du contrat.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
    }
} else {
    // Redirect if accessed directly without form submission
    header("Location: ../grh/agent.contrat.add");
    exit();
}