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
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $est_actif = isset($_POST['est_actif']) ? 1 : 0;
    $commentaire = $_POST['commentaire'] ?? null;
    $idUser = $_SESSION['id'];

    // Vérifier que la date de fin est après la date de début
    if (strtotime($date_fin) <= strtotime($date_debut)) {
        throw new Exception("La date de fin doit être postérieure à la date de début");
    }

    // Si c'est une création
    if (!$id) {
        // Si on veut activer ce nouvel exercice, désactiver d'abord les autres
        if ($est_actif) {
            $stmt = $connexion->prepare("UPDATE exercices_budgetaires SET est_actif = 0");
            $stmt->execute();
        }

        $sql = "INSERT INTO exercices_budgetaires (
                    designation, date_debut, date_fin, est_actif, commentaire, \"idUser\"
                ) VALUES (
                    :designation, :date_debut, :date_fin, :est_actif, :commentaire, :idUser
                )";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':est_actif', $est_actif);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':idUser', $idUser);
        
        $stmt->execute();
        
        $_SESSION['message'] = "L'exercice budgétaire a été créé avec succès.";
        $_SESSION['messageType'] = "success";
    }
    // Si c'est une modification
    else {
        // Vérifier si l'exercice existe et n'est pas clôturé
        $stmt = $connexion->prepare("SELECT est_cloture FROM exercices_budgetaires WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exercice) {
            throw new Exception("Exercice budgétaire non trouvé");
        }
        
        if ($exercice['est_cloture']) {
            throw new Exception("Impossible de modifier un exercice clôturé");
        }
        
        // Si on veut activer cet exercice, désactiver d'abord les autres
        if ($est_actif) {
            $stmt = $connexion->prepare("UPDATE exercices_budgetaires SET est_actif = 0");
            $stmt->execute();
        }
        
        $sql = "UPDATE exercices_budgetaires SET 
                    designation = :designation,
                    date_debut = :date_debut,
                    date_fin = :date_fin,
                    est_actif = :est_actif,
                    commentaire = :commentaire
                WHERE id = :id";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':est_actif', $est_actif);
        $stmt->bindParam(':commentaire', $commentaire);
        
        $stmt->execute();
        
        $_SESSION['message'] = "L'exercice budgétaire a été mis à jour avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des exercices
header('Location: ../?view=finance/config_exercices_budgetaires');
exit;