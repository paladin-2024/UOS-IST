<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../auth/login');
    exit();
}

$enseignantModel = new Enseignant();
$universite = new Universite();

// Récupérer l'ID de l'agent validateur
$idUser = $_SESSION['id'];
$idValidateur = $enseignantModel->getAgentIdByUserId($idUser);

if (!$idValidateur) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Vous n\'êtes pas autorisé à effectuer cette action.'
        }).then(() => {
            window.location.href = '../recherche/choix_etudiant';
        });
    </script>";
    exit();
}

// Récupérer les paramètres
$idSujet = isset($_GET['idsujets']) ? intval($_GET['idsujets']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$commentaire = isset($_GET['commentaire']) ? trim($_GET['commentaire']) : '';

if ($idSujet <= 0 || empty($action)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres invalides.'
        }).then(() => {
            window.location.href = '../recherche/choix_etudiant';
        });
    </script>";
    exit();
}

$statutValidation = '';
$message = '';

switch ($action) {
    case 'validate':
        // Vérifier si le sujet a un directeur et un étudiant avant de le valider
        if (!$enseignantModel->sujetHasDirectorAndStudent($idSujet)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Validation impossible',
                    text: 'Un sujet doit avoir au minimum un directeur et un étudiant assignés pour être validé.'
                }).then(() => {
                    window.location.href = '../recherche/choix_etudiant';
                });
            </script>";
            exit();
        }
        
        $statutValidation = 'Validé';
        $message = 'Le sujet a été validé avec succès.';
        break;
    case 'reject':
        $statutValidation = 'Rejeté';
        $message = 'Le sujet a été rejeté.';
        break;
    case 'modify':
        $statutValidation = 'Modifié';
        $message = 'Une demande de modification a été envoyée.';
        break;
    default:
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Action non reconnue.'
            }).then(() => {
                window.location.href = '../recherche/choix_etudiant';
            });
        </script>";
        exit();
}

// Mettre à jour le statut de validation
$result = $enseignantModel->updateSujetValidation($idSujet, $statutValidation, $idUser, $commentaire);

echo "<script>
    Swal.fire({
        icon: '" . ($result ? 'success' : 'error') . "',
        title: '" . ($result ? 'Succès' : 'Erreur') . "',
        text: '" . ($result ? $message : 'Une erreur est survenue.') . "'
    }).then(() => {
        window.location.href = '../recherche/choix_etudiant';
    });
</script>";
?>
