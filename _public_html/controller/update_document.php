<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

$agent = new Agent();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idDocument = isset($_POST['idDocument']) ? intval($_POST['idDocument']) : 0;
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $source = isset($_POST['source']) ? $_POST['source'] : '';

    if (empty($titre) || empty($description)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le titre et la description sont obligatoires.'
            }).then(() => {
                window.location.href = '../grh/agent.doc." . ($source === 'edit' ? 'edit' : 'add') . "';
            });
        </script>";
        exit();
    }

    $fichier = null;
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
                    window.location.href = '../grh/agent.doc." . ($source === 'edit' ? 'edit' : 'add') . "';
                });
            </script>";
            exit();
        }
    }

    if ($fichier !== null) {
        $success = $agent->updateDocument($idDocument, $titre, $description, $fichier);
    } else {
        $success = $agent->updateDocumentWithoutFile($idDocument, $titre, $description);
    }

    if ($success) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Document modifié avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.doc." . ($source === 'edit' ? 'edit' : 'add') . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la modification du document.'
            }).then(() => {
                window.location.href = '../grh/agent.doc." . ($source === 'edit' ? 'edit' : 'add') . "';
            });
        </script>";
    }
} else {
    header("Location: ../grh/agent.doc.edit");
    exit();
}
?>