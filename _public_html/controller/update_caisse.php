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
    $designation = $_POST['designation'];
    $description = $_POST['description'] ?? null;
    $devise = $_POST['devise'];
    $solde_initial = floatval($_POST['solde_initial']);
    $plafond_caisse = !empty($_POST['plafond_caisse']) ? floatval($_POST['plafond_caisse']) : null;
    $idAgent_responsable = intval($_POST['idAgent_responsable']);
    $localisation = $_POST['localisation'] ?? null;
    $est_actif = isset($_POST['est_actif']) ? 1 : 0;
    $idUser = $_SESSION['id'];

    // Si c'est une création
    if (!$id) {
        $sql = "INSERT INTO caisses (
                    designation, description, devise, solde_initial, solde_actuel, 
                    plafond_caisse, idAgent_responsable, localisation, est_actif, idUser
                ) VALUES (
                    :designation, :description, :devise, :solde_initial, :solde_initial, 
                    :plafond_caisse, :idAgent_responsable, :localisation, :est_actif, :idUser
                )";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':solde_initial', $solde_initial);
        $stmt->bindParam(':plafond_caisse', $plafond_caisse);
        $stmt->bindParam(':idAgent_responsable', $idAgent_responsable);
        $stmt->bindParam(':localisation', $localisation);
        $stmt->bindParam(':est_actif', $est_actif);
        $stmt->bindParam(':idUser', $idUser);
        
        $stmt->execute();
        
        $_SESSION['message'] = "La caisse a été créée avec succès.";
        $_SESSION['messageType'] = "success";
    }
    // Si c'est une modification
    else {
        // Vérifier si on met à jour le solde initial (ce qui affecte le solde actuel)
        $stmt = $connexion->prepare("SELECT solde_initial, solde_actuel FROM caisses WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $difference_solde = $solde_initial - $caisse['solde_initial'];
        $nouveau_solde_actuel = $caisse['solde_actuel'] + $difference_solde;
        
        $sql = "UPDATE caisses SET 
                    designation = :designation,
                    description = :description,
                    devise = :devise,
                    solde_initial = :solde_initial,
                    solde_actuel = :solde_actuel,
                    plafond_caisse = :plafond_caisse,
                    idAgent_responsable = :idAgent_responsable,
                    localisation = :localisation,
                    est_actif = :est_actif
                WHERE id = :id";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':solde_initial', $solde_initial);
        $stmt->bindParam(':solde_actuel', $nouveau_solde_actuel);
        $stmt->bindParam(':plafond_caisse', $plafond_caisse);
        $stmt->bindParam(':idAgent_responsable', $idAgent_responsable);
        $stmt->bindParam(':localisation', $localisation);
        $stmt->bindParam(':est_actif', $est_actif);
        
        $stmt->execute();
        
        $_SESSION['message'] = "La caisse a été mise à jour avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des caisses
header('Location: ../?view=finance/config_caisses');
exit;