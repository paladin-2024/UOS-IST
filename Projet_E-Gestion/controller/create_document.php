<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

$agent = new Agent();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $agentId = isset($_POST['agentId']) ? intval($_POST['agentId']) : 0;
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $idUser = $_SESSION['id'];

    // Handle file upload
    $fichier = '';
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/';
        $fichier = basename($_FILES['fichier']['name']);
        $uploadFile = $uploadDir . $fichier;

        if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors du téléchargement du fichier.'
                }).then(() => {
                    window.location.href = '../grh/agent.doc.add';
                });
            </script>";
            exit();
        }
    }

    if (empty($titre) || empty($description) || empty($fichier) || $agentId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../grh/agent.doc.add';
            });
        </script>";
        exit();
    }

    if ($agent->addDocument($agentId, $titre, $description, $fichier, $idUser)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Document ajouté avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.doc.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du document.'
            }).then(() => {
                window.location.href = '../grh/agent.doc.add';
            });
        </script>";
    }
} else {
    header("Location: ../grh/agent.doc.add");
    exit();
}
?>