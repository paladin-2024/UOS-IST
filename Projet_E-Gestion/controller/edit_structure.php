<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

// Créer une instance de la classe Structure
$structure = new Structure();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer l'ID de la structure à modifier
    $id = isset($_POST['idStructure']) ? $_POST['idStructure'] : 0;
    
    // Vérifier si l'ID est valide
    if ($id <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de la structure invalide.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
        exit();
    }

    // Récupérer les données du formulaire
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
    $phone1 = isset($_POST['phone1']) ? trim($_POST['phone1']) : '';
    $phone2 = isset($_POST['phone2']) ? trim($_POST['phone2']) : '';
    $siteweb = isset($_POST['siteweb']) ? trim($_POST['siteweb']) : '';
    $logo = $_FILES['logo']; // Le fichier image (logo)
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

    // Vérifier si un nouveau logo a été téléchargé
    $logoName = null;
    if ($logo['error'] == UPLOAD_ERR_OK) {
        // Créer le dossier uploads s'il n'existe pas
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // Création du dossier avec les permissions nécessaires
        }

        // Gérer le logo : générer un nom unique
        $logoExtension = pathinfo($logo['name'], PATHINFO_EXTENSION);
        $logoName = strtolower(str_replace(' ', '_', $designation)) . '_logo.' . $logoExtension;
        $logoPath = $uploadDir . $logoName;

        // Vérifier s'il existe un logo actuel pour la structure
        $existingLogo = $structure->getLogoById($id); // Vous devez ajouter cette méthode dans votre modèle Structure
        if ($existingLogo && file_exists($uploadDir . $existingLogo)) {
            // Supprimer l'ancien logo
            unlink($uploadDir . $existingLogo);
        }

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
    }

    // Appeler la fonction d'édition de la structure
    if ($structure->updateStructure($id, $designation, $adresse, $phone1, $phone2, $siteweb, $logoName, $joursOuvrables, $IPR, $tauxRetenuAbsence, $nJoursRecouvrement)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Structure modifiée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la modification de la structure.'
            }).then(() => {
                window.location.href = '../configuration/structure.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/structures.add");
    exit();
}
?>
