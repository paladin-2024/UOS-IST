<?php
session_start();
require_once '../models/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Vérifier si les données requises sont présentes
if (!isset($_POST['idSeance']) || !isset($_POST['titre']) || !isset($_POST['date_seance']) || 
    !isset($_POST['heure_debut']) || !isset($_POST['heure_fin']) || !isset($_POST['idLabo'])) {
    $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
    header('Location: ../laboratoire/seance.list&id=' . $_POST['idLabo']);
    exit;
}

// Récupérer les données du formulaire
$idSeance = intval($_POST['idSeance']);
$idLabo = intval($_POST['idLabo']);
$titre = htmlspecialchars(trim($_POST['titre']));
$dateSeance = $_POST['date_seance'];
$heureDebut = $_POST['heure_debut'];
$heureFin = $_POST['heure_fin'];
$description = isset($_POST['description']) ? htmlspecialchars(trim($_POST['description'])) : '';
$geoVerificationActive = isset($_POST['geo_verification_active']) ? 1 : 0;
$refLatitude = isset($_POST['ref_latitude']) && !empty($_POST['ref_latitude']) ? floatval($_POST['ref_latitude']) : null;
$refLongitude = isset($_POST['ref_longitude']) && !empty($_POST['ref_longitude']) ? floatval($_POST['ref_longitude']) : null;

// Valider les données
if (empty($titre) || empty($dateSeance) || empty($heureDebut) || empty($heureFin)) {
    $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
    header('Location: ../laboratoire/seance.edit&id=' . $idSeance);
    exit;
}

// Vérifier que l'heure de fin est après l'heure de début
if ($heureDebut >= $heureFin) {
    $_SESSION['error'] = "L'heure de fin doit être postérieure à l'heure de début.";
    header('Location: ../laboratoire/seance.edit&id=' . $idSeance);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Mettre à jour les informations de la séance
    $query = "UPDATE seance_labo SET 
              titre = :titre, 
              date_seance = :dateSeance, 
              heure_debut = :heureDebut, 
              heure_fin = :heureFin, 
              description = :description, 
              geo_verification_active = :geoVerificationActive,
              ref_latitude = :refLatitude,
              ref_longitude = :refLongitude
              WHERE idseance_labo = :idSeance";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':titre', $titre);
    $stmt->bindParam(':dateSeance', $dateSeance);
    $stmt->bindParam(':heureDebut', $heureDebut);
    $stmt->bindParam(':heureFin', $heureFin);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':geoVerificationActive', $geoVerificationActive);
    $stmt->bindParam(':refLatitude', $refLatitude);
    $stmt->bindParam(':refLongitude', $refLongitude);
    $stmt->bindParam(':idSeance', $idSeance);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "La séance a été mise à jour avec succès.";
    } else {
        $_SESSION['error'] = "Une erreur est survenue lors de la mise à jour de la séance.";
    }
    
    // Rediriger vers la liste des séances
    header('Location: ../laboratoire/seance.list&id=' . $idLabo);
    exit;
    
} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    header('Location: ../laboratoire/seance.edit&id=' . $idSeance);
    exit;
}
