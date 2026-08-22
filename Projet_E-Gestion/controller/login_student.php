<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $matricule = $_POST['matricule'] ?? '';
    $password = $_POST['password'] ?? '';

    $etudiant = new Etudiant();
    $user = $etudiant->login($matricule, $password);

    if ($user) {
        $_SESSION['student_id'] = $user['idetudiant'];
        $_SESSION['student_name'] = $user['noms'];
        $_SESSION['photo'] = $user['photo'];
        $_SESSION['orientation_id'] = $user['idorientation'];
        $_SESSION['cycle'] = $user['cycle'];
        $_SESSION['promotion_id'] = $user['promotion_idpromotion'];
        $_SESSION['student_matricule'] = $user['matricule'];  // Ajoutez cette ligne
        $_SESSION['annee_acad'] = $user['annee_acad_idannee_acad']; 
        header('Location: ../portail/student');
        exit();
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Matricule ou mot de passe incorrect'
            }).then(() => {
                window.location.href = '../portail/login';
            });
        </script>";
    }
}
?>