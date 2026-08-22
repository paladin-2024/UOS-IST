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
    $exercice_id = isset($_POST['exercice_id']) ? intval($_POST['exercice_id']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $montant_prevu = isset($_POST['montant_prevu']) ? floatval($_POST['montant_prevu']) : 0;
    $montant_revise = !empty($_POST['montant_revise']) ? floatval($_POST['montant_revise']) : null;
    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : null;
    $idUser = $_SESSION['id'];
    
    if (!$exercice_id || !$categorie_id) {
        throw new Exception("Données d'exercice ou de catégorie manquantes");
    }
    
    // Vérifier si l'exercice est clôturé
    $stmt = $connexion->prepare("SELECT est_cloture FROM exercices_budgetaires WHERE id = :id");
    $stmt->bindParam(':id', $exercice_id);
    $stmt->execute();
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($exercice && $exercice['est_cloture']) {
        throw new Exception("Impossible de modifier un budget dans un exercice clôturé");
    }
    
    // Vérifier si la catégorie a des sous-catégories
    $stmt = $connexion->prepare("
        SELECT COUNT(*) AS has_children 
        FROM categories_budget 
        WHERE parent_id = :categorie_id
    ");
    $stmt->bindParam(':categorie_id', $categorie_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['has_children'] > 0) {
        throw new Exception("Impossible de modifier directement le budget d'une catégorie qui possède des sous-catégories.");
    }
    
    // Vérifier si un budget existe déjà pour cette catégorie dans cet exercice
    $stmt = $connexion->prepare("
        SELECT id, montant_engage, montant_realise 
        FROM budget 
        WHERE exercice_id = :exercice_id AND categorie_id = :categorie_id
    ");
    $stmt->bindParam(':exercice_id', $exercice_id);
    $stmt->bindParam(':categorie_id', $categorie_id);
    $stmt->execute();
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculer le montant disponible
    $montant_engage = $budget ? floatval($budget['montant_engage']) : 0;
    $montant_realise = $budget ? floatval($budget['montant_realise']) : 0;
    
    $montant_effectif = $montant_revise !== null ? $montant_revise : $montant_prevu;
    $disponible = $montant_effectif - $montant_engage;
    
    if ($budget) {
        // Mise à jour du budget existant
        $sql = "UPDATE budget SET 
                    montant_prevu = :montant_prevu,
                    montant_revise = :montant_revise,
                    disponible = :disponible,
                    commentaire = :commentaire
                WHERE id = :id";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':id', $budget['id']);
        $stmt->bindParam(':montant_prevu', $montant_prevu);
        $stmt->bindParam(':montant_revise', $montant_revise);
        $stmt->bindParam(':disponible', $disponible);
        $stmt->bindParam(':commentaire', $commentaire);
        
        $stmt->execute();
        
        $_SESSION['message'] = "Le budget a été mis à jour avec succès.";
    } else {
        // Création d'un nouveau budget
        $sql = "INSERT INTO budget (
                    exercice_id, categorie_id, montant_prevu, montant_revise,
                    montant_engage, montant_realise, disponible, commentaire, \"idUser\"
                ) VALUES (
                    :exercice_id, :categorie_id, :montant_prevu, :montant_revise,
                    :montant_engage, :montant_realise, :disponible, :commentaire, :idUser
                )";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':exercice_id', $exercice_id);
        $stmt->bindParam(':categorie_id', $categorie_id);
        $stmt->bindParam(':montant_prevu', $montant_prevu);
        $stmt->bindParam(':montant_revise', $montant_revise);
        $stmt->bindParam(':montant_engage', $montant_engage);
        $stmt->bindParam(':montant_realise', $montant_realise);
        $stmt->bindParam(':disponible', $disponible);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':idUser', $idUser);
        
        $stmt->execute();
        
        $_SESSION['message'] = "Le budget a été créé avec succès.";
    }
    
    $_SESSION['messageType'] = "success";
    
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur lors de l'enregistrement du budget: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de configuration du budget
header('Location: ../?view=finance/config_budget&exercice_id=' . $exercice_id);
exit;
