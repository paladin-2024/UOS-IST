<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $idSujet = isset($_POST['editIdSujet']) ? intval($_POST['editIdSujet']) : 0;
    $intitule = isset($_POST['editIntitule']) ? trim($_POST['editIntitule']) : '';
    $cycle = isset($_POST['editCycle']) ? trim($_POST['editCycle']) : '';
    $idSpecialisation = isset($_POST['editIdSpecialisation']) ? intval($_POST['editIdSpecialisation']) : 0;
    $anneeAcadId = isset($_POST['editAnneeAcad']) ? intval($_POST['editAnneeAcad']) : 0;

    // Récupération des données optionnelles
    $etatSujet = isset($_POST['etatSujet']) ? trim($_POST['etatSujet']) : 'En attente';
    $etudiantId = isset($_POST['etudiant']) && !empty($_POST['etudiant']) ? intval($_POST['etudiant']) : null;
    $directeurId = isset($_POST['directeur']) && !empty($_POST['directeur']) ? intval($_POST['directeur']) : null;
    $encadreurId = isset($_POST['encadreur']) && !empty($_POST['encadreur']) ? intval($_POST['encadreur']) : null;

    // Validation des données
    if ($idSujet <= 0 || empty($intitule) || empty($cycle) || $idSpecialisation <= 0 || $anneeAcadId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../recherche/sujets';
            });
        </script>";
        exit();
    }

    // Mise à jour du sujet dans la base de données
    $result = $universite->updateSujet($idSujet, $intitule, $cycle, $idSpecialisation, $anneeAcadId,$etatSujet,$etudiantId,$directeurId,$encadreurId);

    // Redirection en fonction du résultat
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le sujet de recherche a été modifié avec succès.'
            }).then(() => {
                window.location.href = '../recherche/sujets';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la modification du sujet de recherche.'
            }).then(() => {
                window.location.href = '../recherche/sujets';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../recherche/sujets");
    exit();
}
?>