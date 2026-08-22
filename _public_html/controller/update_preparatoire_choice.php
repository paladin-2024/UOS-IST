<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si l'ID étudiant est présent
    if (!isset($_POST['studentId']) || empty($_POST['studentId'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID étudiant manquant'
        ]);
        exit;
    }
    
    // Vérifier si la classe a été sélectionnée
    if (!isset($_POST['selectedClass']) || empty($_POST['selectedClass'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Veuillez sélectionner une classe préparatoire'
        ]);
        exit;
    }
    
    // Récupérer les données du formulaire
    $id = intval($_POST['studentId']);
    $selectedClass = trim(htmlspecialchars($_POST['selectedClass']));
    $noms = isset($_POST['noms']) ? trim(htmlspecialchars($_POST['noms'])) : '';
    $lieuNaissance = isset($_POST['lieuNaissance']) ? trim(htmlspecialchars($_POST['lieuNaissance'])) : '';
    $dateNaissance = isset($_POST['dateNaissance']) ? trim($_POST['dateNaissance']) : '';
    $sexe = isset($_POST['sexe']) ? trim(htmlspecialchars($_POST['sexe'])) : '';
    $nationalite = isset($_POST['nationalite']) ? trim(htmlspecialchars($_POST['nationalite'])) : '';
    $adressemail = isset($_POST['adressemail']) ? trim(htmlspecialchars($_POST['adressemail'])) : '';
    $telephone = isset($_POST['telephone']) ? trim(htmlspecialchars($_POST['telephone'])) : '';
    $adresse = isset($_POST['adresse']) ? trim(htmlspecialchars($_POST['adresse'])) : '';
    $personne_contact = isset($_POST['personne_contact']) ? trim(htmlspecialchars($_POST['personne_contact'])) : '';
    $telephone_contact = isset($_POST['telephone_contact']) ? trim(htmlspecialchars($_POST['telephone_contact'])) : '';
    
    // Validation des champs requis
    if (empty($noms) || empty($sexe) || empty($nationalite) || empty($adressemail) || empty($telephone)) {
        echo json_encode([
            'success' => false,
            'message' => 'Veuillez remplir tous les champs obligatoires'
        ]);
        exit;
    }
    
    // Validation de l'email
    if (!filter_var($adressemail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'L\'adresse email est invalide'
        ]);
        exit;
    }
    
    // Traitement de la photo si elle existe
    $photoFilename = isset($_POST['existingPhoto']) ? $_POST['existingPhoto'] : null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2 Mo
        
        // Vérifier le type de fichier
        if (!in_array($_FILES['photo']['type'], $allowedTypes)) {
            echo json_encode([
                'success' => false,
                'message' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG.'
            ]);
            exit;
        }
        
        // Vérifier la taille du fichier
        if ($_FILES['photo']['size'] > $maxSize) {
            echo json_encode([
                'success' => false,
                'message' => 'La taille du fichier ne doit pas dépasser 2 Mo.'
            ]);
            exit;
        }
        
        // Créer un nom de fichier unique
        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoFilename = 'etudiant_' . $id . '_' . time() . '.' . $extension;
        $uploadDir = '../uploads/photos_etudiants/';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $uploadPath = $uploadDir . $photoFilename;
        
        // Déplacer le fichier téléchargé
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du téléchargement de la photo'
            ]);
            exit;
        }
        
        // Le chemin relatif pour la base de données
        $photoFilename = 'uploads/photos_etudiants/' . $photoFilename;
    }
    
    // Instancier la classe Universite
    $universite = new Universite();
    
    // Mettre à jour les informations de l'étudiant et son choix de classe
    $result = $universite->updatePreparatoireChoice(
        $id,
        $selectedClass,
        $noms,
        $lieuNaissance,
        $dateNaissance,
        $sexe,
        $nationalite,
        $adressemail,
        $telephone,
        $adresse,
        $personne_contact,
        $telephone_contact,
        $photoFilename
    );
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Choix de classe et profil mis à jour avec succès'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du choix de classe et du profil'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
}
