<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $universite = new Universite();
        
        
        // Récupération et nettoyage des données du formulaire
        $id = $_POST['id'];
        $titre = $_POST['titre'];
        $type_document = $_POST['type_document'];
        $nom_auteur = $_POST['nom_auteur'];
        $type_auteur = $_POST['type_auteur'];
        $departement_id = $_POST['departement_id'];
        $specialisation_id = $_POST['specialisation_id'];
        $annee_academique_id = $_POST['annee_academique_id'];
        $mots_cles = $_POST['mots_cles'];
        $resume = $_POST['resume'];
        $est_public = isset($_POST['est_public']) ? 1 : 0;

        // Validation des données
        if (!$id || !$titre || !$type_document || !$nom_auteur || 
            !$type_auteur || !$departement_id || !$specialisation_id || 
            !$annee_academique_id) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }

        // Récupérer le travail existant
        $travail = $universite->getTravailById($id);
        if (!$travail) {
            throw new Exception('Travail non trouvé.');
        }

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

        // Gestion du nouveau fichier PDF
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document'];
            
            // Validation plus robuste du type de fichier
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $allowedTypes = ['application/pdf'];
            $allowedExtensions = ['pdf'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Le fichier doit être au format PDF.');
            }
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('L\'extension du fichier doit être .pdf');
            }
            
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                throw new Exception('La taille du fichier ne doit pas dépasser 10MB.');
            }

            // Création du dossier de destination avec permissions sécurisées
            $uploadDir = dirname(__DIR__) . '/uploads/travaux/' . date('Y') . '/' . $type_document;
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Impossible de créer le dossier de destination.');
                }
            }

            // Génération d'un nom de fichier sécurisé
            $fileName = uniqid('doc_') . '_' . 
                       preg_replace('/[^a-zA-Z0-9]/', '_', $titre) . 
                       '_' . date('Ymd') . '.pdf';
            $filePath = $uploadDir . '/' . $fileName;
            $relativePath = 'uploads/travaux/' . date('Y') . '/' . $type_document . '/' . $fileName;

            // Déplacement du fichier avec vérification supplémentaire
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Erreur lors du téléchargement du fichier.');
            }

            // Vérification finale du fichier uploadé
            if (!file_exists($filePath) || filesize($filePath) !== $file['size']) {
                throw new Exception('Erreur de vérification du fichier uploadé.');
            }

            // Ajouter le nouveau chemin aux données
            $data['fichier_path'] = $relativePath;
        }

        // Mise à jour du travail
        $result = $universite->updateTravail($id, $data);

        if ($result) {
            // Si succès, supprimer l'ancien fichier si nécessaire
            if (isset($data['fichier_path']) && !empty($travail['fichier_path'])) {
                $oldFilePath = dirname(__DIR__) . '/' . $travail['fichier_path'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
            
           
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le travail scientifique a été mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../depot/depot.travail';
                });
            </script>";
        } else {
            throw new Exception('Une erreur est survenue lors de la mise à jour du travail.');
        }

    } catch (Exception $e) {
        
        
        // Nettoyage en cas d'erreur
        if (isset($filePath) && file_exists($filePath)) {
            unlink($filePath);
        }
        
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
    header("Location: ../depot/depot.travail");
    exit();
}
?>