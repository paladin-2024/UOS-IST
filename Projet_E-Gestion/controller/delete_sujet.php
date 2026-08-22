<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['idsujets'])) {
    // Récupération de l'ID du sujet
    $idSujet = intval($_GET['idsujets']);

    // Vérification de l'ID
    if ($idSujet <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Identifiant du sujet invalide.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
        exit();
    }

    // Vérification si le sujet n'est pas déjà attribué à un étudiant
    if ($universite->isSujetPris($idSujet)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de supprimer ce sujet car il est déjà attribué à un étudiant.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
        exit();
    }

    // Tentative de suppression du sujet
    $result = $universite->deleteSujet($idSujet);

    // Redirection en fonction du résultat
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le sujet de recherche a été supprimé avec succès.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la suppression du sujet de recherche.'
            }).then(() => {
                window.location.href = '../ur/sujets';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier ou paramètre manquant
    header("Location: ../ur/sujets");
    exit();
}
?>