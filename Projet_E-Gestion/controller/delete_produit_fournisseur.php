<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if (isset($_GET['id']) && isset($_GET['fournisseur'])) {
    try {
        $id_produit_fournisseur = intval($_GET['id']);
        $id_fournisseur = intval($_GET['fournisseur']);
        
        if ($id_produit_fournisseur <= 0) {
            throw new Exception("Identifiant invalide.");
        }
        
        // Récupérer les informations pour la journalisation
        $stmt = $db->prepare("SELECT pf.id_produit, p.libelle_produit, f.nom_fournisseur 
                             FROM produit_fournisseur pf
                             JOIN produit p ON pf.id_produit = p.id_produit
                             JOIN fournisseur f ON pf.id_fournisseur = f.id_fournisseur
                             WHERE pf.id_produit_fournisseur = :id");
        $stmt->bindParam(':id', $id_produit_fournisseur, PDO::PARAM_INT);
        $stmt->execute();
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$info) {
            throw new Exception("Cette association produit-fournisseur n'existe pas.");
        }
        
        // Suppression de l'association
        $stmt = $db->prepare("DELETE FROM produit_fournisseur WHERE id_produit_fournisseur = :id");
        $stmt->bindParam(':id', $id_produit_fournisseur, PDO::PARAM_INT);
        $stmt->execute();
        
        // Journalisation de l'action
        $id_user = $_SESSION['id'];
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'suppression', 'produit_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Suppression de l'association du produit '{$info['libelle_produit']}' au fournisseur '{$info['nom_fournisseur']}'";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_produit_fournisseur, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Redirection vers la page du fournisseur
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'L\'association a été supprimée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../fournisseurs/fournisseurs.view&id={$id_fournisseur}';
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../fournisseurs/fournisseurs.view&id={$id_fournisseur}';
            });
        </script>";
        exit;
    }
} else {
    // Redirection si accès direct au fichier sans paramètres
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Paramètres manquants',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../fournisseurs/fournisseurs.list';
        });
    </script>";
    exit;
}
