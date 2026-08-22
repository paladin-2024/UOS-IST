<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Sujet.php';

$sujet = new Sujet();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $intitule = isset($_POST['intitule']) ? trim($_POST['intitule']) : '';
    $cycle = isset($_POST['cycle']) ? trim($_POST['cycle']) : '';
    $idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
    $anneeAcadId = isset($_POST['annee_acad']) ? intval($_POST['annee_acad']) : 0;
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;

    // Récupération des données optionnelles
    $etatSujet = isset($_POST['etatSujet']) ? trim($_POST['etatSujet']) : 'En attente';
    $directeurId = isset($_POST['directeur']) && !empty($_POST['directeur']) ? intval($_POST['directeur']) : null;
    $encadreurId = isset($_POST['encadreur']) && !empty($_POST['encadreur']) ? intval($_POST['encadreur']) : null;

    // Validation des données
    if (empty($intitule) || empty($cycle) || $idSpecialisation <= 0 || $anneeAcadId <= 0 || $idUser <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
        exit();
    }

    // Vérification que le directeur et l'encadreur sont différents s'ils sont tous deux spécifiés
    if ($directeurId !== null && $encadreurId !== null && $directeurId === $encadreurId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le directeur et l\'encadreur ne peuvent pas être la même personne.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
        exit();
    }

    // Création du sujet dans la base de données
    $result = $sujet->createSujet($intitule, $cycle, $idSpecialisation, $anneeAcadId, $idUser, $etatSujet, null, $directeurId, $encadreurId);

    // Redirection en fonction du résultat
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le sujet de recherche a été créé avec succès.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la création du sujet de recherche.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../ur/sujets");
    exit();
}
?>
