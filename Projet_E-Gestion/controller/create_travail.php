<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $universite = new Universite();
        
        // Récupération des données du formulaire
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $type_document = isset($_POST['type_document']) ? trim($_POST['type_document']) : '';
        $nom_auteur = isset($_POST['nom_auteur']) ? trim($_POST['nom_auteur']) : '';
        $type_auteur = isset($_POST['type_auteur']) ? trim($_POST['type_auteur']) : '';
        $departement_id = isset($_POST['departement_id']) ? intval($_POST['departement_id']) : 0;
        $specialisation_id = isset($_POST['specialisation_id']) ? intval($_POST['specialisation_id']) : 0;
        $annee_academique_id = isset($_POST['annee_academique_id']) ? intval($_POST['annee_academique_id']) : 0;
        $mots_cles = isset($_POST['mots_cles']) ? trim($_POST['mots_cles']) : '';
        $resume = isset($_POST['resume']) ? trim($_POST['resume']) : '';
        $est_public = isset($_POST['est_public']) ? 1 : 0;

        // Validation des données
        if (empty($titre) || empty($type_document) || empty($nom_auteur) || 
            empty($type_auteur) || $departement_id <= 0 || $specialisation_id <= 0 || 
            $annee_academique_id <= 0) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }

        // Gestion du fichier PDF
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Le document PDF est obligatoire.');
        }

        $file = $_FILES['document'];
        $allowedTypes = ['application/pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        // Vérifications du fichier
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Seuls les fichiers PDF sont acceptés.');
        }
        if ($file['size'] > $maxSize) {
            throw new Exception('La taille du fichier ne doit pas dépasser 10MB.');
        }

        // Création du dossier de destination
        $uploadDir = dirname(__DIR__) . '/uploads/travaux/' . date('Y') . '/' . $type_document;
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Impossible de créer le dossier de destination.');
            }
        }

        // Génération d'un nom de fichier unique
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $titre) . '.pdf';
        $filePath = $uploadDir . '/' . $fileName;
        $relativePath = 'uploads/travaux/' . date('Y') . '/' . $type_document . '/' . $fileName;

        // Déplacement du fichier
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Erreur lors du téléchargement du fichier.');
        }

        // Préparation des données pour la création
        $data = [
            'titre' => $titre,
            'type_document' => $type_document,
            'nom_auteur' => $nom_auteur,
            'type_auteur' => $type_auteur,
            'departement_id' => $departement_id,
            'specialisation_id' => $specialisation_id,
            'annee_academique_id' => $annee_academique_id,
            'mots_cles' => $mots_cles,
            'resume' => $resume,
            'fichier_path' => $relativePath,
            'est_public' => $est_public
        ];

        // Création du travail dans la base de données
        $result = $universite->deposerTravail($data);

        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le travail scientifique a été déposé avec succès.'
                }).then(() => {
                    window.location.href = '../depot/depot.travail';
                });
            </script>";
        } else {
            // En cas d'échec, supprimer le fichier uploadé
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            throw new Exception('Une erreur est survenue lors du dépôt du travail.');
        }

    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../depot/depot.travail");
    exit();
}
?>