<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $intitule = isset($_POST['intitule']) ? trim($_POST['intitule']) : '';
    $dateDebut = isset($_POST['dateDebut']) ? $_POST['dateDebut'] : '';
    $dateFin = isset($_POST['dateFin']) ? $_POST['dateFin'] : '';
    $budget = isset($_POST['budget']) ? floatval($_POST['budget']) : 0.0;
    $etatActivite = isset($_POST['etatActivite']) ? trim($_POST['etatActivite']) : '';
    $projetId = isset($_POST['Projet_idProjet']) ? intval($_POST['Projet_idProjet']) : 0;

    // Validation des champs requis
    if (empty($intitule) || empty($dateDebut) || empty($dateFin) || $budget <= 0 || empty($etatActivite) || $projetId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../projet/activite.add';
            });
        </script>";
        exit();
    }

    // Ajout de l'activité via le modèle
    try {
        if ($projet->addActivity($intitule, $dateDebut, $dateFin, $budget, $etatActivite, $projetId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Activité ajoutée avec succès.'
                }).then(() => {
                    window.location.href = '../projet/activite.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de l\'ajout de l\'activité');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'activité: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../projet/activite.add';
            });
        </script>";
    }
} else {
    header("Location: ../projet/activite.add");
    exit();
}
?>