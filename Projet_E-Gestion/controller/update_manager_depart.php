<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$universite = new Universite();
$user=new Structure();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $managerId = $_POST['editManagerId'] ?? '';
    $userId = $_POST['editUserId'] ?? '';
    $fonction = $_POST['editFonction'] ?? '';
    $anneeAcadId = $_POST['idAnnee'] ?? '';

    $getUser=$user->getUserById($userId)->fetch();
    $noms = $getUser['nomUser'];

    // Validate inputs
    if (empty($managerId) || empty($userId) || empty($fonction) || empty($anneeAcadId)) {
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

    // Handle file upload for new signature
    $signaturePath = '';
    if (isset($_FILES['editSignature']) && $_FILES['editSignature']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $timestamp = time();
        $extension = pathinfo($_FILES['editSignature']['name'], PATHINFO_EXTENSION);
        $signaturePath = $uploadDir . "SIGNATURE_" . $timestamp . '.' . $extension;

        // Move the uploaded file to the designated directory
        if (!move_uploaded_file($_FILES['editSignature']['tmp_name'], $signaturePath)) {
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
    }

    // Update the manager in the database
    $updateSuccess = false;
    if (!empty($signaturePath)) {
        $updateSuccess = $universite->updateManagerDepartement($managerId, $noms, $fonction, $signaturePath, $userId, $anneeAcadId);
    } else {
        // If no new signature is uploaded, do not update the signature field
        $updateSuccess = $universite->updateManagerDepartement($managerId, $noms, $fonction, null, $userId, $anneeAcadId);
    }

    if ($updateSuccess) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Manager mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/departement';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du manager.'
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