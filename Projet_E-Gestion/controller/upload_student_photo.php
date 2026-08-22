<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérification des droits d'accès
if (!isset($_SESSION['id'])) {
    header("Location: ../index");
    exit();
}

try {
    // Vérifier si le formulaire a été soumis
    if (!isset($_POST['idEtudiant']) || !isset($_FILES['photoFile'])) {
        throw new Exception("Paramètres manquants");
    }
    
    $idEtudiant = intval($_POST['idEtudiant']);
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'etudiant.inscrit';
    
    if ($idEtudiant <= 0) {
        throw new Exception("ID étudiant invalide");
    }
    
    // Vérifier si un fichier a été uploadé
    if ($_FILES['photoFile']['error'] != UPLOAD_ERR_OK) {
        throw new Exception("Erreur lors du téléchargement: " . $_FILES['photoFile']['error']);
    }
    
    $universite = new Universite();
    
    // Uploader la photo
    $photoUrl = $universite->uploadStudentPhoto($idEtudiant, $_FILES['photoFile']);
    
    $_SESSION['success'] = "Photo téléchargée avec succès.";
    
    // Rediriger selon le paramètre 'redirect'
    if ($redirect === 'ecard') {
        header("Location: generate_ecard.php?id=$idEtudiant");
    } else {
        header("Location: ../etudiants/etudiant.inscrit");
    }
    exit();
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Rediriger vers la page précédente
    if (isset($_POST['idEtudiant'])) {
        $idEtudiant = intval($_POST['idEtudiant']);
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'etudiant.inscrit';
        header("Location: ../etudiants/upload-photo?id=$idEtudiant&redirect=$redirect");
    } else {
        header("Location: ../etudiants/etudiant.inscrit");
    }
    exit();
}
