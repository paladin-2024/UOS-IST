<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $universite = new Universite();
        
        // Récupération des données du formulaire
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
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
        
        // Récupération des champs spécifiques aux thèses
        $anneeThese = null;
        $universiteThese = null;
        $faculteThese = null;
        $specialisationThese = null;
        
        if ($type_document === 'Thèse') {
            $anneeThese = isset($_POST['anneeThese']) ? trim($_POST['anneeThese']) : '';
            $universiteThese = isset($_POST['universiteThese']) ? trim($_POST['universiteThese']) : '';
            $faculteThese = isset($_POST['faculteThese']) ? trim($_POST['faculteThese']) : '';
            $specialisationThese = isset($_POST['specialisationThese']) ? trim($_POST['specialisationThese']) : '';
            
            // Validation des champs spécifiques aux thèses
            if (empty($anneeThese) || empty($universiteThese) || empty($faculteThese) || empty($specialisationThese)) {
                throw new Exception('Tous les champs spécifiques aux thèses doivent être remplis.');
            }
        }

        // Validation des données générales
        if ($id <= 0 || empty($titre) || empty($type_document) || empty($nom_auteur) || 
            empty($type_auteur) || $departement_id <= 0 || $specialisation_id <= 0 || 
            $annee_academique_id <= 0) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }

        // Récupération du travail existant
        $travail = $universite->getTravailById($id);
        if (!$travail) {
            throw new Exception('Le travail scientifique demandé n\'existe pas.');
        }

        // Préparation des données pour la mise à jour
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
            'est_public' => $est_public
        ];
        
        // Ajouter les champs spécifiques aux thèses si nécessaire
        if ($type_document === 'Thèse') {
            $data['anneeThese'] = $anneeThese;
            $data['universiteThese'] = $universiteThese;
            $data['faculteThese'] = $faculteThese;
            $data['specialisationThese'] = $specialisationThese;
        }

        // Gestion du fichier PDF si un nouveau fichier est téléchargé
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
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

            // Ajouter le nouveau chemin du fichier aux données
            $data['fichier_path'] = $relativePath;
        }

        // Mise à jour du travail dans la base de données
        $result = $universite->updateTravail($id, $data);

        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le travail scientifique a été mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../bibliotheque/bilio_add';
                });
            </script>";
        } else {
            // En cas d'échec, supprimer le nouveau fichier si un fichier a été téléchargé
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
            throw new Exception('Une erreur est survenue lors de la mise à jour du travail.');
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
    header("Location: ../bibliotheque/bilio_add");
    exit();
}
?>