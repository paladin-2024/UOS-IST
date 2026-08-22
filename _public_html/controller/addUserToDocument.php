<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idDocument = isset($_POST['id_document']) ? intval($_POST['id_document']) : 0;
    $idUser = isset($_POST['idUser']) ? intval($_POST['idUser']) : 0;

    if ($idDocument <= 0 || $idUser <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../document/doc.prive.add';
            });
        </script>";
        exit();
    }

    // Use the model method to add the user to the document
    if ($structure->addUserToDocument($idUser, $idDocument)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Utilisateur ajouté avec succès au document.'
            }).then(() => {
                window.location.href = '../document/doc.prive.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'utilisateur au document.'
            }).then(() => {
                window.location.href = '../document/doc.prive.add';
            });
        </script>";
    }
} else {
    header("Location: ../document/doc.prive.add");
    exit();
}
?>