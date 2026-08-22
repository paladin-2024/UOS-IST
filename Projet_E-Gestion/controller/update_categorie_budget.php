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
    $code = $_POST['code'];
    $designation = $_POST['designation'];
    $description = $_POST['description'] ?? null;
    $type = $_POST['type'];
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $compte_comptable_id = !empty($_POST['compte_comptable_id']) ? intval($_POST['compte_comptable_id']) : null;
    $est_actif = isset($_POST['est_actif']) ? 1 : 0;
    $idUser = $_SESSION['id'];

    // Vérifier si le code est unique
    $stmt = $connexion->prepare("SELECT id FROM categories_budget WHERE code = :code AND id != :id");
    $stmt->bindParam(':code', $code);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Le code de catégorie doit être unique. Ce code est déjà utilisé.";
        $_SESSION['messageType'] = "danger";
        header('Location: ../?view=finance/config_categories_budget');
        exit;
    }

    // Déterminer le niveau de la catégorie
    $niveau = 1; // Par défaut
    
    if ($parent_id) {
        $stmt = $connexion->prepare("SELECT niveau FROM categories_budget WHERE id = :id");
        $stmt->bindParam(':id', $parent_id);
        $stmt->execute();
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($parent) {
            $niveau = $parent['niveau'] + 1;
            
            // Vérifier si le niveau ne dépasse pas 3
            if ($niveau > 3) {
                $_SESSION['message'] = "Impossible de créer une catégorie de niveau supérieur à 3.";
                $_SESSION['messageType'] = "danger";
                header('Location: ../?view=finance/config_categories_budget');
                exit;
            }
        }
    }

    // Si c'est une création
    if (!$id) {
        $sql = "INSERT INTO categories_budget (
                    code, designation, description, type, 
                    parent_id, compte_comptable_id, niveau, est_actif, idUser
                ) VALUES (
                    :code, :designation, :description, :type, 
                    :parent_id, :compte_comptable_id, :niveau, :est_actif, :idUser
                )";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':parent_id', $parent_id);
        $stmt->bindParam(':compte_comptable_id', $compte_comptable_id);
        $stmt->bindParam(':niveau', $niveau);
        $stmt->bindParam(':est_actif', $est_actif);
        $stmt->bindParam(':idUser', $idUser);
        
        $stmt->execute();
        
        $_SESSION['message'] = "La catégorie budgétaire a été créée avec succès.";
        $_SESSION['messageType'] = "success";
    }
    // Si c'est une modification
    else {
        // Vérifier si cette catégorie a des enfants
        $stmt = $connexion->prepare("SELECT COUNT(*) as count FROM categories_budget WHERE parent_id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $hasSubs = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        // Vérifier si on essaie de modifier le type d'une catégorie qui a des sous-catégories
        if ($hasSubs) {
            $stmt = $connexion->prepare("SELECT type FROM categories_budget WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $oldType = $stmt->fetch(PDO::FETCH_ASSOC)['type'];
            
            if ($oldType !== $type) {
                $_SESSION['message'] = "Impossible de modifier le type d'une catégorie qui a des sous-catégories.";
                $_SESSION['messageType'] = "danger";
                header('Location: ../?view=finance/config_categories_budget');
                exit;
            }
        }
        
        $sql = "UPDATE categories_budget SET 
                    code = :code,
                    designation = :designation,
                    description = :description,
                    type = :type,
                    parent_id = :parent_id,
                    compte_comptable_id = :compte_comptable_id,
                    niveau = :niveau,
                    est_actif = :est_actif
                WHERE id = :id";
        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':parent_id', $parent_id);
        $stmt->bindParam(':compte_comptable_id', $compte_comptable_id);
        $stmt->bindParam(':niveau', $niveau);
        $stmt->bindParam(':est_actif', $est_actif);
        $stmt->bindParam(':id', $id);
        
        $stmt->execute();
        
        $_SESSION['message'] = "La catégorie budgétaire a été mise à jour avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de gestion des catégories budgétaires
header('Location: ../?view=finance/config_categories_budget');
exit;
