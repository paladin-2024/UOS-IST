<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$universite = new Universite();
$user=new Structure();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['userId'] ?? '';
    $fonction = $_POST['fonction'] ?? '';
    $departementId = $_POST['departementId'] ?? '';
    $anneeAcadId = $_POST['idAnnee'] ?? '';

    $getUser=$user->getUserById($userId)->fetch();
    $noms = $getUser['nomUser'];

    // Validate inputs
    if (empty($userId) || empty($fonction) || empty($departementId) || empty($anneeAcadId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
        exit();
    }

    // Handle file upload for signature
    $signaturePath = '';
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $timestamp = time();
        $extension = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $signaturePath = $uploadDir . "SIGNATURE_" . $timestamp . '.' . $extension;

        // Move the uploaded file to the designated directory
        if (!move_uploaded_file($_FILES['signature']['tmp_name'], $signaturePath)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors du téléchargement de la signature.'
                }).then(() => {
                    window.location.href = '../configuration/departement';
                });
            </script>";
            exit();
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez importer une signature valide.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
        exit();
    }

    // Create the manager
    $result = $universite->createManagerDepartement($noms, $fonction, $signaturePath, $userId, $departementId, $anneeAcadId);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Manager ajouté avec succès.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du manager.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../configuration/departement");
    exit();
}
?>