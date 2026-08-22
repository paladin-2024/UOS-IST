<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idetudiant = isset($_POST['idetudiant']) ? intval($_POST['idetudiant']) : 0;
    $matricule = isset($_POST['matricule']) ? $_POST['matricule'] : '';
    $titre = isset($_POST['titre']) ? $_POST['titre'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $type_document = isset($_POST['type_document']) ? $_POST['type_document'] : '';
    $document_obligatoire_id = isset($_POST['document_obligatoire_id']) ? intval($_POST['document_obligatoire_id']) : null;
    $annee_acad_id = isset($_POST['annee_acad_id']) ? intval($_POST['annee_acad_id']) : 0;
    $idUser = $_SESSION['id'];

    if (empty($idetudiant) || empty($matricule) || empty($titre) || empty($type_document) || empty($annee_acad_id)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
        exit();
    }

    // Vérification du fichier
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] != 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez sélectionner un fichier valide.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
        exit();
    }

    $file = $_FILES['document_file'];
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_file_size = 5 * 1024 * 1024; // 5 Mo
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Seuls les fichiers PDF, JPG et PNG sont acceptés.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
                        });
        </script>";
        exit();
    }
    
    if ($file['size'] > $max_file_size) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La taille du fichier ne doit pas dépasser 5 Mo.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
        exit();
    }
    
    // Générer un nom de fichier unique
    $document_dir = dirname(__DIR__) . '/uploads/documents_etudiants/';
    if (!is_dir($document_dir)) {
        mkdir($document_dir, 0777, true);
    }
    
    $new_filename = $matricule . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $document_dir . $new_filename;
    $web_path = 'uploads/documents_etudiants/' . $new_filename;
    
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Échec du téléchargement du fichier.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
        exit();
    }
    
    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Insérer le document
        $stmt = $conn->prepare("
            INSERT INTO etudiant_documents 
            (idetudiant, matricule, type_document, document_obligatoire_id, titre, description, 
             chemin_fichier, date_ajout, annee_acad_id, idUser, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'En attente de validation')
        ");
        
        $stmt->execute([
            $idetudiant,
            $matricule,
            $type_document,
            $document_obligatoire_id ?: null,
            $titre,
            $description,
            $web_path,
            $annee_acad_id,
            $idUser
        ]);
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le document a été ajouté avec succès.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
    } catch (PDOException $e) {
        // Supprimer le fichier en cas d'erreur d'insertion
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de l\'enregistrement du document: " . $e->getMessage() . "'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
    }
} else {
    header("Location: ../index");
    exit();
}

