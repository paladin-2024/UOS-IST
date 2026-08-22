<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $studentId = $_SESSION['student_id'] ?? '';

    // Validation des données requises
    if (!$currentPassword || !$newPassword || !$confirmPassword || !$studentId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs'
            }).then(() => {
                window.location.href = '../portail/profile';
            });
        </script>";
        exit();
    }

    // Vérification que les nouveaux mots de passe correspondent
    if ($newPassword !== $confirmPassword) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les nouveaux mots de passe ne correspondent pas'
            }).then(() => {
                window.location.href = '../portail/profile';
            });
        </script>";
        exit();
    }

    // Validation de la force du mot de passe
    if (strlen($newPassword) < 8) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le mot de passe doit contenir au moins 8 caractères'
            }).then(() => {
                window.location.href = '../portail/profile';
            });
        </script>";
        exit();
    }

    try {
        $etudiantModel = new Etudiant();

        // Vérification de l'ancien mot de passe
        if (!$etudiantModel->verifyPassword($studentId, $currentPassword)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le mot de passe actuel est incorrect'
                }).then(() => {
                    window.location.href = '../portail/profile';
                });
            </script>";
            exit();
        }

        // Changement du mot de passe
        $success = $etudiantModel->changePassword($studentId, $newPassword);

        if ($success) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Votre mot de passe a été modifié avec succès'
                }).then(() => {
                    window.location.href = '../portail/profile';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors du changement de mot de passe'
                }).then(() => {
                    window.location.href = '../portail/profile';
                });
            </script>";
        }
    } catch (Exception $e) {
        // Log l'erreur pour l'administrateur
        error_log("Erreur lors du changement de mot de passe: " . $e->getMessage());
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors du changement de mot de passe'
            }).then(() => {
                window.location.href = '../portail/profile';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../portail/profile');
    exit();
}
?>