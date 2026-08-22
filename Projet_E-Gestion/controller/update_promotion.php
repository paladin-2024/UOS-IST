<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login');
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promotion_id'])) {
    $etudiantId = $_SESSION['student_id'];
    $promotionId = $_POST['promotion_id'];
    
    // Valider l'ID de promotion
    if (!is_numeric($promotionId) || $promotionId <= 0) {
        $_SESSION['error'] = "ID de promotion invalide.";
        header('Location: ../portail/profile');
        exit();
    }
    
    $etudiantModel = new Etudiant();
    
    // Mettre à jour la promotion
    if ($etudiantModel->updatePromotion($etudiantId, $promotionId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Votre promotion a été mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../portail/profile';
            });
        </script>";
        exit();
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s'est produite lors de la mise à jour de votre promotion.'
            }).then(() => {
                window.location.href = '../portail/profile';
            });
        </script>";
        exit();
    }
    
    // Rediriger vers la page de profil
    header('Location: ../portail/profile');
    exit();
} else {
    // Redirection si accès direct au script
    header('Location: ../portail/profile');
    exit();
}
?>