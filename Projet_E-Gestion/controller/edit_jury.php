<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $juryId = $_POST['editJuryId'] ?? '';
    $designation = $_POST['editDesignation'] ?? '';
    $numeroDecision = $_POST['editNumeroDecision'] ?? '';
    $dateDecision = $_POST['editDateDecision'] ?? '';
    $presidentId = $_POST['editPresidentId'] ?? '';
    $secretaireId = $_POST['editSecretaireId'] ?? '';
    $anneeAcadId = $_POST['editAnneeId'] ?? '';
    $estActif = $_POST['editEstActif'] ?? 1;
    $commentaire = $_POST['editCommentaire'] ?? '';

    // Valider les entrées
    if (empty($juryId) || empty($designation) || empty($numeroDecision) || empty($dateDecision) || 
        empty($presidentId) || empty($secretaireId) || empty($anneeAcadId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
        exit();
    }

    // Mettre à jour le jury
    $result = $universite->updateJury($juryId, $designation, $numeroDecision, $dateDecision, 
                                     $presidentId, $secretaireId, $anneeAcadId, 
                                     $estActif, $commentaire);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Bureau de jury mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du bureau de jury.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../configuration/jury");
    exit();
}
?>
