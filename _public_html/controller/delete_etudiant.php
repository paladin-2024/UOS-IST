<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['idetudiant'])) {
    $studentId = $_GET['idetudiant'];

    // Validate input
    if (empty($studentId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de l\'étudiant manquant.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
        exit();
    }

    // Delete the student
    $result = $universite->deleteStudent($studentId);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Étudiant supprimé avec succès.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'étudiant.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../etudiants/etudiant.inscrit");
    exit();
}
?>