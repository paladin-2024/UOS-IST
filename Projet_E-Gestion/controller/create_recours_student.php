<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Recours.php';

// Vérifier si l'utilisateur est connecté en tant qu'étudiant
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $matricule = isset($_POST['matricule']) ? $_POST['matricule'] : '';
    $idEcue = isset($_POST['id_ecue']) ? intval($_POST['id_ecue']) : 0;
    $idSession = isset($_POST['id_session']) ? intval($_POST['id_session']) : 0;
    $idAnneeAcad = isset($_POST['id_annee_acad']) ? intval($_POST['id_annee_acad']) : 0;
    $motif = isset($_POST['motif']) ? $_POST['motif'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $idCreateur = isset($_POST['id_createur']) ? intval($_POST['id_createur']) : 0;
    
    // Validation des champs obligatoires
    if (empty($matricule) || $idEcue <= 0 || $idSession <= 0 || $idAnneeAcad <= 0 || empty($motif) || empty($description) || $idCreateur <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../portail/student?tab=recours';
            });
        </script>";
        exit();
    }

    // Traitement du fichier de preuve
    $preuve = null;
    if (isset($_FILES['preuve']) && $_FILES['preuve']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $filename = $_FILES['preuve']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Format de fichier non pris en charge. Veuillez utiliser PDF, JPG ou PNG.'
                }).then(() => {
                    window.location.href = '../portail/student?tab=recours';
                });
            </script>";
            exit();
        }
        
        // Créer un nom de fichier unique
        $newFileName = 'recours_' . $matricule . '_' . time() . '.' . $ext;
        $uploadDir = dirname(__DIR__) . '/uploads/recours/';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Déplacer le fichier téléchargé
        if (move_uploaded_file($_FILES['preuve']['tmp_name'], $uploadDir . $newFileName)) {
            $preuve = $newFileName;
        }
    }
    
    try {
        $recoursModel = new Recours();
        
        // Ajouter le recours
        $success = $recoursModel->createRecours(
            $matricule,
            $idEcue,
            $idSession,
            $idAnneeAcad,
            $motif,
            $description,
            $preuve,
            $idCreateur
        );
        
        if ($success) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Votre recours a été soumis avec succès.'
                }).then(() => {
                    window.location.href = '../portail/student?tab=recours';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la soumission de votre recours.'
                }).then(() => {
                    window.location.href = '../portail/student?tab=recours';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue: " . $e->getMessage() . "'
            }).then(() => {
                window.location.href = '../portail/student?tab=recours';
            });
        </script>";
    }
} else {
    // Si la méthode n'est pas POST, rediriger vers la page d'accueil
    header('Location: ../portail/student');
    exit();
}
