<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Create an instance of the Agent class
$agent = new Agent();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the family member ID from the form
    $idDossierFamille = isset($_POST['idDossierFamille']) ? trim($_POST['idDossierFamille']) : '';

    // Validate the ID
    if (empty($idDossierFamille)) {
        // Error message if ID is missing
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID du membre de la famille manquant.'
            }).then(() => {
                window.location.href = '../grh/agent.famille.add';
            });
        </script>";
        exit();
    }

    // Call the function to delete the family member
    if ($agent->deleteFamilyMember($idDossierFamille)) {
        // Success message with SweetAlert
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Membre de la famille supprimé avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.famille.add';
            });
        </script>";
    } else {
        // Error message with SweetAlert
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du membre de la famille.'
            }).then(() => {
                window.location.href = '../grh/agent.famille.add';
            });
        </script>";
    }
} else {
    // Redirect if accessed directly without form submission
    header("Location: ../grh/agent.famille.add");
    exit();
}