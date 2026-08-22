<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $intitule = isset($_POST['intitule']) ? trim($_POST['intitule']) : '';
    $cycle = isset($_POST['cycle']) ? trim($_POST['cycle']) : '';
    $idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
    $anneeAcadId = isset($_POST['annee_acad']) ? intval($_POST['annee_acad']) : 0;
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;

    // Validation des données
    if (empty($intitule) || empty($cycle) || $idSpecialisation <= 0 || $anneeAcadId <= 0 || $idUser <= 0) {
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

    // Création du sujet dans la base de données
    $result = $universite->createSujet2($intitule, $cycle, $idSpecialisation, $anneeAcadId, $idUser);

    // Redirection en fonction du résultat
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le sujet de recherche a été créé avec succès.'
            }).then(() => {
                window.location.href = '../recherche/sujets';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la création du sujet de recherche.'
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