<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Grade.php';

// Créer une instance de la classe Grade
$grade = new Grade();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $type_agent = isset($_POST['type_agent']) ? trim($_POST['type_agent']) : '';

    // Validation des champs requis
    if (empty($designation) || empty($type_agent)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation et le type d\'agent sont obligatoires.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour le grade
    if ($grade->checkDuplicateGrade($designation, $type_agent)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un grade avec ce nom existe déjà pour ce type d\'agent.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
        exit();
    }

    // Appeler la fonction d'ajout de grade
    if ($grade->addGrade($designation, $description, $type_agent)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Grade ajouté avec succès.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du grade.'
            }).then(() => {
                window.location.href = '../configuration/grade.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/grade.add");
    exit();
}
?>
