<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

$enseignantModel = new Enseignant();
$userId = $_SESSION['id'];
$enseignant = $enseignantModel->getEnseignantByUserId($userId);

if (!$enseignant) {
    header('Location: ../index');
    exit();
}

$idEnseignant = $enseignant['idAgent'];

// Vérifier si les paramètres nécessaires sont présents
if (!isset($_GET['idsujets']) || !isset($_GET['action'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants.'
        }).then(() => {
            window.location.href = '../recherche/projet.recherche';
        });
    </script>";
    exit();
}

$idSujet = intval($_GET['idsujets']);
$action = $_GET['action'];

// Définir l'état du sujet en fonction de l'action
$etatSujet = '';
switch ($action) {
    case 'validate':
        $etatSujet = 'Validé';
        $message = 'Le sujet a été validé avec succès.';
        break;
    case 'reject':
        $etatSujet = 'Rejeté';
        $message = 'Le sujet a été rejeté.';
        break;
    default:
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Action non reconnue.'
            }).then(() => {
                window.location.href = '../recherche/projet.recherche';
            });
        </script>";
        exit();
}

// Mettre à jour l'état du sujet
$result = $enseignantModel->updateEtatSujet($idSujet, $etatSujet, $idEnseignant);

if ($result) {
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: '$message'
        }).then(() => {
            window.location.href = '../recherche/projet.recherche';
        });
    </script>";
} else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Vous n\'êtes pas autorisé à effectuer cette action ou une erreur est survenue.'
        }).then(() => {
            window.location.href = '../recherche/projet.recherche';
        });
    </script>";
}
