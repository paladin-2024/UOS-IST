<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php'; // Include the 405 error page
require_once dirname(__DIR__) . '/config/Connexion.php'; // Include the database connection
require_once dirname(__DIR__) . '/models/Agent.php'; // Include the Agent model

// Create an instance of the Agent class
$agent = new Agent();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the document ID from the form
    $idDocument = isset($_POST['idDocument']) ? trim($_POST['idDocument']) : '';

    // Validate the ID
    if (empty($idDocument)) {
        // Error message if ID is missing
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID du document manquant.'
            }).then(() => {
                window.location.href = '../grh/agent.doc.add';
            });
        </script>";
        exit();
    }

    // Call the function to delete the document
    if ($agent->deleteDocument($idDocument)) {
        // Success message with SweetAlert
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Document supprimé avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.doc.add';
            });
        </script>";
    } else {
        // Error message with SweetAlert
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du document.'
            }).then(() => {
                window.location.href = '../grh/agent.doc.add';
            });
        </script>";
    }
} else {
    // Redirect if accessed directly without form submission
    header("Location: ../grh/agent.doc.add");
    exit();
}