<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Cours.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/AffectationEnseignant.php';

// Créer une instance des classes
$cours = new Cours();
$ecue = new Ecue();
$affectation = new AffectationEnseignant();

// Vérifier l'action demandée
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add_course':
        // Récupérer les données du formulaire
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
        $idEnseignant = isset($_POST['idEnseignant']) ? intval($_POST['idEnseignant']) : 0;
        $idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
        
        // Validation des données
        if (empty($titre) || $idECUE <= 0 || $idEnseignant <= 0 || $idAnneeAcad <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tous les champs sont obligatoires.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Ajouter le cours
        if ($cours->addCours($titre, $description, $idECUE, $idEnseignant, $idAnneeAcad)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le cours a été ajouté avec succès.'
                }).then(() => {
                    window.location.href = '../enseignement/cours';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'ajout du cours.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
        
    case 'add_chapter':
        // Récupérer les données du formulaire
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $ordre = isset($_POST['ordre']) ? intval($_POST['ordre']) : 1;
        $idCours = isset($_POST['idCours']) ? intval($_POST['idCours']) : 0;
        
        // Validation des données
        if (empty($titre) || $idCours <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le titre et le cours sont obligatoires.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Ajouter le chapitre
        if ($cours->addPartieCours($titre, $description, $ordre, $idCours)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le chapitre a été ajouté avec succès.'
                }).then(() => {
                    window.location.href = '../enseignement/cours.details?id=" . $idCours . "';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'ajout du chapitre.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
        
    case 'add_resource':
        // Récupérer les données du formulaire
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $typeRessource = isset($_POST['typeRessource']) ? trim($_POST['typeRessource']) : '';
        $estPayant = isset($_POST['estPayant']) ? 1 : 0;
        $idFrais = isset($_POST['idFrais']) ? intval($_POST['idFrais']) : null;
        $idPartie = isset($_POST['idPartie']) ? intval($_POST['idPartie']) : 0;
        $lienExterne = isset($_POST['lienExterne']) ? trim($_POST['lienExterne']) : '';
        
        // Traitement du fichier
        $fichier = '';
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/';
            $fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
            $newFileName = 'resource_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $uploadFile = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                $fichier = $newFileName;
            }
        }
        
        // Validation des données
        if (empty($titre) || $idPartie <= 0 || (empty($fichier) && empty($lienExterne))) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le titre, la partie et un fichier ou lien sont obligatoires.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Ajouter la ressource
        if ($cours->addRessource($titre, $description, $typeRessource, $fichier, $lienExterne, $estPayant, $idFrais, $idPartie)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'La ressource a été ajoutée avec succès.'
                }).then(() => {
                    window.location.href = '../enseignement/cours.details?id=" . $_POST['idCours'] . "';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'ajout de la ressource.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
        
    default:
        // Redirection par défaut
        header('Location: ../index');
        exit();
}
