<?php
// Redirection basée sur le type d'utilisateur
if (isset($_SESSION['student_id'])) {
    // Étudiant - rediriger vers le portail étudiant avec l'onglet plan
    header('Location: index.php?view=student#plan');
    exit();
} elseif (isset($_SESSION['id'])) {
    // Enseignant/Directeur - rediriger vers l'interface directeur
    header('Location: index.php?view=plan_directeur');
    exit();
} else {
    // Non connecté
    header('Location: index.php');
    exit();
}
?>
