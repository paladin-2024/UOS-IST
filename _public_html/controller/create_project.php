<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nomProjet = isset($_POST['nomProjet']) ? trim($_POST['nomProjet']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $dateDebut = isset($_POST['dateDebut']) ? $_POST['dateDebut'] : '';
    $dateFin = isset($_POST['dateFin']) ? $_POST['dateFin'] : '';
    $statut = isset($_POST['statut']) ? trim($_POST['statut']) : '';
    $structureId = isset($_POST['Structure_idStructure']) ? intval($_POST['Structure_idStructure']) : 0;
    $userId = $_SESSION['id'];

    // Validation des champs requis
    if (empty($nomProjet) || empty($description) || empty($dateDebut) || empty($dateFin) || empty($statut) || $structureId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../projet/projet.add';
            });
        </script>";
        exit();
    }

    // Vérification des doublons
    if ($projet->checkDuplicateProject($nomProjet, $structureId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un projet avec ce nom existe déjà dans cette structure.'
            }).then(() => {
                window.location.href = '../projet/projet.add';
            });
        </script>";
        exit();
    }

    // Création du projet
    try {
        if ($projet->addProject($nomProjet, $description, $dateDebut, $dateFin, $statut, $structureId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Projet créé avec succès.'
                }).then(() => {
                    window.location.href = '../projet/projet.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la création du projet');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la création du projet: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../projet/projet.add';
            });
        </script>";
    }
} else {
    header("Location: ../projet/projet.add");
    exit();
}
?>