<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$connexion = Connexion::getInstance()->getPDO();

try {
    // Récupération des données du formulaire
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $idUser = intval($_POST['idUser']);
    $type = $_POST['type']; // Doit être 'Caisse'
    $niveau = $_POST['niveau'];
    $entite_id = !empty($_POST['entite_id']) ? intval($_POST['entite_id']) : null;
    $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $est_actif = isset($_POST['est_actif']) ? 1 : 0;
    $commentaire = $_POST['commentaire'] ?? null;
    $idCreateur = $_SESSION['id'];

    // Si c'est une création
    if (!$id) {
        $sql = "INSERT INTO droits_acces_finances (
                    \"idUser\", type, niveau, entite_id, date_debut, date_fin,
                    est_actif, commentaire, \"idCreateur\"
                ) VALUES (
                    :idUser, :type, :niveau, :entite_id, :date_debut, :date_fin,
                    :est_actif, :commentaire, :idCreateur
                )";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':niveau', $niveau);
        $stmt->bindParam(':entite_id', $entite_id);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':est_actif', $est_actif);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':idCreateur', $idCreateur);
        
        $stmt->execute();
        
        $_SESSION['message'] = "Le droit d'accès a été créé avec succès.";
        $_SESSION['messageType'] = "success";
    }
    // Si c'est une modification
    else {
        $sql = "UPDATE droits_acces_finances SET 
                    \"idUser\" = :idUser,
                    niveau = :niveau,
                    entite_id = :entite_id,
                    date_debut = :date_debut,
                    date_fin = :date_fin,
                    est_actif = :est_actif,
                    commentaire = :commentaire
                WHERE id = :id AND type = :type";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':niveau', $niveau);
        $stmt->bindParam(':entite_id', $entite_id);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':est_actif', $est_actif);
        $stmt->bindParam(':commentaire', $commentaire);
        
        $stmt->execute();
        
        $_SESSION['message'] = "Le droit d'accès a été mis à jour avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des accès caisses
header('Location: ../?view=finance/config_acces_caisses');
exit;