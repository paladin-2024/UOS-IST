<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Projet.php';

$projet = new Projet();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idProjet = isset($_POST['idProjet']) ? intval($_POST['idProjet']) : 0;
    $nomProjet = isset($_POST['nomProjet']) ? trim($_POST['nomProjet']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $dateDebut = isset($_POST['dateDebut']) ? $_POST['dateDebut'] : '';
    $dateFin = isset($_POST['dateFin']) ? $_POST['dateFin'] : '';
    $statut = isset($_POST['statut']) ? trim($_POST['statut']) : '';
    $structureId = isset($_POST['Structure_idStructure']) ? intval($_POST['Structure_idStructure']) : 0;

    // Validation des champs requis
    if ($idProjet <= 0 || empty($nomProjet) || empty($description) || empty($dateDebut) || empty($dateFin) || empty($statut) || $structureId <= 0) {
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

    // Mise à jour du projet
    try {
        $data = [
            ':idProjet' => $idProjet,
            ':nomProjet' => $nomProjet,
            ':description' => $description,
            ':dateDebut' => $dateDebut,
            ':dateFin' => $dateFin,
            ':statut' => $statut,
            ':structureId' => $structureId
        ];

        if ($projet->updateProject($data)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Projet mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../projet/projet.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la mise à jour du projet');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du projet: " . addslashes($e->getMessage()) . "'
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