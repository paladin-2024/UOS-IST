<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

// Créer une instance de la classe Structure
$structure = new Structure();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
    $phone1 = isset($_POST['phone1']) ? trim($_POST['phone1']) : '';
    $phone2 = isset($_POST['phone2']) ? trim($_POST['phone2']) : '';
    $siteweb = isset($_POST['siteweb']) ? trim($_POST['siteweb']) : '';
    $logo = $_FILES['logo']; // Le fichier image
    $joursOuvrables = isset($_POST['joursOuvrables']) ? trim($_POST['joursOuvrables']) : 0;
    $IPR = isset($_POST['IPR']) ? trim($_POST['IPR']) : 0;
    $tauxRetenuAbsence = isset($_POST['tauxRetenuAbsence']) ? trim($_POST['tauxRetenuAbsence']) : 0;
    $nJoursRecouvrement = isset($_POST['nJoursRecouvrement']) ? trim($_POST['nJoursRecouvrement']) : 0;

    // Validation des champs requis
    if (empty($designation) || empty($adresse)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation et l\'adresse sont obligatoires.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
        exit();
    }

    // Vérifier si le logo a été téléchargé
    if ($logo['error'] != UPLOAD_ERR_OK) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez télécharger un logo valide.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
        exit();
    }

    // Créer le dossier uploads s'il n'existe pas
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Création du dossier avec les permissions nécessaires
    }

    // Gérer le logo : générer un nom unique
    $logoExtension = pathinfo($logo['name'], PATHINFO_EXTENSION);
    $logoName = strtolower(str_replace(' ', '_', $designation)) . '_logo.' . $logoExtension;
    $logoPath = $uploadDir . $logoName;

    // Déplacer le fichier téléchargé dans le dossier uploads
    if (!move_uploaded_file($logo['tmp_name'], $logoPath)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors du téléchargement du logo.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour la désignation
    if ($structure->checkDuplicateStructure($designation)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une structure avec ce nom existe déjà.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
        exit();
    }

    // Appeler la fonction d'ajout de structure
    if ($structure->addStructure($designation, $adresse, $phone1, $phone2, $siteweb, $logoName, $joursOuvrables, $IPR, $tauxRetenuAbsence, $nJoursRecouvrement)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Structure ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la structure.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/structure.add");
    exit();
}
?>
