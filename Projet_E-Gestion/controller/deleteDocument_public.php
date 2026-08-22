<?php
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

session_start();

if (!isset($_GET['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID du document manquant.'
        }).then(() => {
            window.location.href = '../document/doc.public.add';
        });
    </script>";
    exit();
}

$documentId = $_GET['id'];
$documentModel = new Structure();

// Fetch the document details
$document = $documentModel->getDocumentById_public($documentId);

if (!$document) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Document non trouvé.'
        }).then(() => {
            window.location.href = '../document/doc.public.add';
        });
    </script>";
    exit();
}

// Delete the file from the server
$filePath = '../uploads/' . $document['chemin_fichier'];
if (file_exists($filePath)) {
    unlink($filePath);
}

// Delete the document record from the database
if ($documentModel->deleteDocument_public($documentId)) {
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: 'Le document a été supprimé avec succès.'
        }).then(() => {
            window.location.href = '../document/doc.public.add';
        });
    </script>";
} else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Échec de la suppression du document.'
        }).then(() => {
            window.location.href = '../document/doc.public.add';
        });
    </script>";
}
?>