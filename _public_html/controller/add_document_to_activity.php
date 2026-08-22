<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $dateDocument = isset($_POST['dateDocument']) ? $_POST['dateDocument'] : '';
    $activityId = isset($_POST['idActivite_projet']) ? intval($_POST['idActivite_projet']) : 0;
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    // Validate required fields
    if (empty($titre) || empty($dateDocument) || $activityId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../projet/activite.add';
            });
        </script>";
        exit();
    }

    // Handle file upload
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/';
        $originalFileName = basename($_FILES['fichier']['name']);
        $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
        $dateTime = date('YmdHis');
        $newFileName = "DOC_PROJET_{$activityId}_{$dateTime}.{$fileExtension}";
        $filePath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $filePath)) {
            // Add document to activity
            try {
                if ($projet->addDocumentToActivity($titre, $description, $dateDocument, $newFileName, $userId, $activityId)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'Document ajouté à l\'activité avec succès.'
                        }).then(() => {
                            window.location.href = '../projet/document.add';
                        });
                    </script>";
                } else {
                    throw new Exception('Erreur lors de l\'ajout du document à l\'activité');
                }
            } catch (Exception $e) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur lors de l\'ajout du document à l\'activité: " . addslashes($e->getMessage()) . "'
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
                    text: 'Erreur lors du téléchargement du fichier.'
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
                text: 'Aucun fichier téléchargé ou erreur lors du téléchargement.'
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