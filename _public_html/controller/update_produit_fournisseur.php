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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Récupérer les données du formulaire
        $id_produit_fournisseur = isset($_POST['id_produit_fournisseur']) ? intval($_POST['id_produit_fournisseur']) : 0;
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $id_produit = isset($_POST['id_produit']) ? intval($_POST['id_produit']) : 0;
        $prix_achat = isset($_POST['prix_achat']) ? floatval($_POST['prix_achat']) : 0.00;
        $delai_livraison = isset($_POST['delai_livraison']) && $_POST['delai_livraison'] !== '' ? intval($_POST['delai_livraison']) : null;
        $est_fournisseur_principal = isset($_POST['est_fournisseur_principal']) ? 1 : 0;
        
        // Validation des données
        if ($id_produit_fournisseur <= 0 || $id_fournisseur <= 0 || $id_produit <= 0 || $prix_achat <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis correctement.");
        }
        
        // Vérifier que l'association existe
        $stmt = $db->prepare("SELECT COUNT(*) FROM produit_fournisseur 
                             WHERE id_produit_fournisseur = :id_produit_fournisseur");
        $stmt->bindParam(':id_produit_fournisseur', $id_produit_fournisseur, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Cette association produit-fournisseur n'existe pas.");
        }
        
        // Si ce produit devient le fournisseur principal, mettre à jour les autres enregistrements
        if ($est_fournisseur_principal) {
            $updateStmt = $db->prepare("UPDATE produit_fournisseur 
                                      SET est_fournisseur_principal = 0 
                                      WHERE id_produit = :id_produit 
                                      AND id_fournisseur != :id_fournisseur");
            $updateStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $updateStmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
            $updateStmt->execute();
        }
        
        // Mise à jour de l'association
        $stmt = $db->prepare("UPDATE produit_fournisseur 
                             SET prix_achat = :prix_achat, 
                                 delai_livraison = :delai_livraison,
                                 est_fournisseur_principal = :est_fournisseur_principal
                             WHERE id_produit_fournisseur = :id_produit_fournisseur");
        
        $stmt->bindParam(':prix_achat', $prix_achat, PDO::PARAM_STR);
        $stmt->bindParam(':delai_livraison', $delai_livraison, PDO::PARAM_INT);
        $stmt->bindParam(':est_fournisseur_principal', $est_fournisseur_principal, PDO::PARAM_INT);
        $stmt->bindParam(':id_produit_fournisseur', $id_produit_fournisseur, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Récupérer le nom du produit pour la journalisation
        $stmtProduit = $db->prepare("SELECT libelle_produit FROM produit WHERE id_produit = :id_produit");
        $stmtProduit->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
        $stmtProduit->execute();
        $nomProduit = $stmtProduit->fetchColumn();
        
        // Récupérer le nom du fournisseur pour la journalisation
        $stmtFournisseur = $db->prepare("SELECT nom_fournisseur FROM fournisseur WHERE id_fournisseur = :id_fournisseur");
        $stmtFournisseur->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $stmtFournisseur->execute();
        $nomFournisseur = $stmtFournisseur->fetchColumn();
        
        // Journalisation de l'action
        $id_user = $_SESSION['id'];
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'produit_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Mise à jour de l'association du produit '$nomProduit' au fournisseur '$nomFournisseur'. Nouveau prix: $prix_achat USD";
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
                text: 'Les informations du produit ont été mises à jour avec succès.',
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
    // Redirection si accès direct au fichier
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Accès non autorisé',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../fournisseurs/fournisseurs.list';
        });
    </script>";
    exit;
}
