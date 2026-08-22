<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $matricule = $_POST['matricule'] ?? '';
    $noms = $_POST['noms'] ?? '';
    $lieuNaissance = $_POST['lieuNaissance'] ?? '';
    $dateNaissance = $_POST['dateNaissance'] ?? '';
    $adressemail = $_POST['adressemail'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $sexe = $_POST['sexe'] ?? '';
    $nationalite = $_POST['nationalite'] ?? '';
    $idAnnee = $_POST['idAnnee'] ?? '';
    $promotionId = $_POST['promotionId'] ?? '';
    $idUser = $_SESSION['id'] ?? null; // Retrieve idUser from session

    // Validate input
    if (empty($id) || empty($matricule) || empty($noms) || empty($idAnnee) || empty($promotionId) || $idUser === null) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
        exit();
    }

    // Update the student
    $result = $universite->updateStudent($id, $matricule, $noms, $lieuNaissance, $dateNaissance, $adressemail, $telephone,$sexe, $nationalite, $idAnnee, $promotionId, $idUser);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Étudiant mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de l\'étudiant.'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../etudiants/etudiant.inscrit");
    exit();
}
?>