<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

if (isset($_GET['id'])) {
    try {
        $db = Connexion::getInstance()->getPDO();
        $id_compte = intval($_GET['id']);
        
        // Vérifier si le compte existe
        $check_stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE id_compte = :id_compte");
        $check_stmt->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() == 0) {
            throw new Exception("Ce compte n'existe pas.");
        }
        
        // Vérifier si ce compte est utilisé comme compte parent
        $check_parent = $db->prepare("SELECT id_compte FROM compte_comptable WHERE compte_parent = :id_compte");
        $check_parent->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_parent->execute();
        
        if ($check_parent->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est utilisé comme compte parent pour d'autres comptes.");
        }
        
        // Vérifier si ce compte est utilisé dans la table client
        $check_client = $db->prepare("SELECT id_client FROM client WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_client->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_client->execute();
        
        if ($check_client->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est associé à des clients.");
        }
        
        // Vérifier si ce compte est utilisé dans la table fournisseur
        $check_fournisseur = $db->prepare("SELECT id_fournisseur FROM fournisseur WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_fournisseur->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_fournisseur->execute();
        
        if ($check_fournisseur->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est associé à des fournisseurs.");
        }
        
        // Vérifier si ce compte est utilisé dans la table compte_bancaire
        $check_compte_bancaire = $db->prepare("SELECT id_compte_bancaire FROM compte_bancaire WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_compte_bancaire->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_compte_bancaire->execute();
        
        if ($check_compte_bancaire->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est associé à des comptes bancaires.");
        }
        
        // Vérifier si ce compte est utilisé dans la table ligne_ecriture_comptable
        $check_ligne_ecriture = $db->prepare("SELECT id_ligne_ecriture FROM ligne_ecriture_comptable WHERE id_compte = :id_compte LIMIT 1");
        $check_ligne_ecriture->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_ligne_ecriture->execute();
        
        if ($check_ligne_ecriture->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est utilisé dans des écritures comptables.");
        }
        
        // Vérifier si ce compte est utilisé dans la table operation_bancaire
        $check_op_bancaire = $db->prepare("SELECT id_operation FROM operation_bancaire WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_op_bancaire->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_op_bancaire->execute();
        
        if ($check_op_bancaire->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est utilisé dans des opérations bancaires.");
        }
        
        // Vérifier si ce compte est utilisé dans la table operation_caisse
        $check_op_caisse = $db->prepare("SELECT id_operation FROM operation_caisse WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_op_caisse->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_op_caisse->execute();
        
        if ($check_op_caisse->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est utilisé dans des opérations de caisse.");
        }
        
        // Vérifier si ce compte est utilisé dans la table produit
        $check_produit = $db->prepare("SELECT id_produit FROM produit WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_produit->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_produit->execute();
        
        if ($check_produit->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est associé à des produits.");
        }
        
        // Vérifier si ce compte est utilisé dans la table detail_rapport_financier
        $check_rapport = $db->prepare("SELECT id_detail_rapport FROM detail_rapport_financier WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_rapport->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_rapport->execute();
        
        if ($check_rapport->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est utilisé dans des rapports financiers.");
        }
        
        // Vérifier si ce compte est utilisé dans la table ligne_budget
        $check_budget = $db->prepare("SELECT id_ligne_budget FROM ligne_budget WHERE id_compte_comptable = :id_compte LIMIT 1");
        $check_budget->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $check_budget->execute();
        
        if ($check_budget->rowCount() > 0) {
            throw new Exception("Ce compte ne peut pas être supprimé car il est utilisé dans des lignes budgétaires.");
        }
        
        // Si toutes les vérifications sont passées, supprimer le compte
        $delete_stmt = $db->prepare("DELETE FROM compte_comptable WHERE id_compte = :id_compte");
        $delete_stmt->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $delete_stmt->execute();
        
        // Enregistrer dans les logs
        $user_id = $_SESSION['id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $browser = $_SERVER['HTTP_USER_AGENT'];
        $log_stmt = $db->prepare("INSERT INTO log_operation (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
                                 VALUES (:user_id, 'Suppression', 'compte_comptable', :id_compte, 'Suppression d''un compte comptable', :ip, :browser)");
        $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':id_compte', $id_compte, PDO::PARAM_INT);
        $log_stmt->bindParam(':ip', $ip_address);
        $log_stmt->bindParam(':browser', $browser);
        $log_stmt->execute();
        
        $_SESSION['success_message'] = "Compte comptable supprimé avec succès.";
        header('Location: ../comptabilite/compte.list');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../comptabilite/compte.list');
        exit();
    }
} else {
    $_SESSION['error_message'] = "Identifiant de compte non spécifié.";
    header('Location: ../comptabilite/compte.list');
    exit();
}
