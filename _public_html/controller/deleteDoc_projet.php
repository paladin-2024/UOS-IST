<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if (isset($_GET['id'])) {
    $documentId = intval($_GET['id']);

    // Fetch document details to get the file name
    $document = $projet->getDocumentById($documentId);
    if ($document) {
        $filePath = dirname(__DIR__) . '/uploads/' . $document['fichier'];

        // Delete the document from the database
        if ($projet->deleteDocument($documentId)) {
            // Delete the file from the file system
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Document supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../projet/document.add';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression du document.'
                }).then(() => {
                    window.location.href = '../projet/document.add';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Document introuvable.'
            }).then(() => {
                window.location.href = '../projet/document.add';
            });
        </script>";
    }
} else {
    header("Location: ../projet/document.add");
    exit();
}
?>