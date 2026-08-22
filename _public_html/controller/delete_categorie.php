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

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    try {
        $id_categorie = intval($_GET['id']);
        $id_user = $_SESSION['id'];
        
        // Vérifier si la catégorie existe
        $stmt = $db->prepare("SELECT * FROM categorie_produit WHERE id_categorie = :id_categorie");
        $stmt->bindParam(':id_categorie', $id_categorie, PDO::PARAM_INT);
        $stmt->execute();
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categorie) {
            throw new Exception("Catégorie non trouvée.");
        }
        
        // Vérifier si la catégorie est utilisée par des produits
        $stmt = $db->prepare("SELECT COUNT(*) FROM produit WHERE id_categorie = :id_categorie");
        $stmt->bindParam(':id_categorie', $id_categorie, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Impossible de supprimer cette catégorie car elle est utilisée par un ou plusieurs produits.");
        }
        
        // Suppression de la catégorie
        $stmt = $db->prepare("DELETE FROM categorie_produit WHERE id_categorie = :id_categorie");
        $stmt->bindParam(':id_categorie', $id_categorie, PDO::PARAM_INT);
        $stmt->execute();
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'suppression', 'categorie_produit', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Suppression de la catégorie: {$categorie['libelle_categorie']} (Code: {$categorie['code_categorie']})";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_categorie, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La catégorie a été supprimée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                                        window.location.href = '../produits/categories.list';
                }
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
                window.location.href = '../produits/categories.list';
            });
        </script>";
        exit;
    }
} else {
    // Paramètre id manquant ou invalide
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Identifiant de catégorie invalide',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../produits/categories.list';
        });
    </script>";
    exit;
}
