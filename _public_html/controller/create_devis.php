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
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Récupérer les données du formulaire
        $numero_devis = isset($_POST['numero_devis']) ? trim($_POST['numero_devis']) : '';
        $date_devis = isset($_POST['date_devis']) ? trim($_POST['date_devis']) : date('Y-m-d');
        $id_client = isset($_POST['id_client']) ? intval($_POST['id_client']) : 0;
        $validite = isset($_POST['validite']) ? intval($_POST['validite']) : 30;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $taux_tva = isset($_POST['taux_tva']) ? floatval($_POST['taux_tva']) : 0;
        $montant_ht = isset($_POST['montant_ht']) ? floatval($_POST['montant_ht']) : 0;
        $montant_tva = isset($_POST['montant_tva']) ? floatval($_POST['montant_tva']) : 0;
        $montant_ttc = isset($_POST['montant_ttc']) ? floatval($_POST['montant_ttc']) : 0;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if (empty($numero_devis) || empty($date_devis) || $id_client <= 0) {
            throw new Exception("Les champs Numéro de devis, Date et Client sont obligatoires.");
        }
        
        // Vérifier si le numéro de devis existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM devis WHERE numero_devis = :numero_devis");
        $stmt->bindParam(':numero_devis', $numero_devis, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce numéro de devis existe déjà. Veuillez en choisir un autre.");
        }
        
        // Vérifier si le client existe
        $stmt = $db->prepare("SELECT COUNT(*) FROM client WHERE id_client = :id_client");
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Le client sélectionné n'existe pas.");
        }
        
        // Vérifier si des produits ont été ajoutés
        if (!isset($_POST['products']) || !is_array($_POST['products']) || count($_POST['products']) == 0) {
            throw new Exception("Vous devez ajouter au moins un produit au devis.");
        }
        
        // Insertion du devis dans la base de données
        $stmt = $db->prepare("INSERT INTO devis 
            (numero_devis, date_devis, id_client, montant_ht, taux_tva, montant_tva, montant_ttc, 
            validite, observation, etat, id_user_creation) 
            VALUES 
            (:numero_devis, :date_devis, :id_client, :montant_ht, :taux_tva, :montant_tva, :montant_ttc, 
            :validite, :observation, 'En cours', :id_user_creation)");
        
        $stmt->bindParam(':numero_devis', $numero_devis, PDO::PARAM_STR);
        $stmt->bindParam(':date_devis', $date_devis, PDO::PARAM_STR);
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
        $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_tva', $montant_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_ttc', $montant_ttc, PDO::PARAM_STR);
        $stmt->bindParam(':validite', $validite, PDO::PARAM_INT);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        
        $stmt->execute();
        $idDevis = $db->lastInsertId();
        
        // Insertion des lignes de devis
        foreach ($_POST['products'] as $product) {
            $id_produit = isset($product['id_produit']) ? intval($product['id_produit']) : 0;
            $designation = isset($product['designation']) ? trim($product['designation']) : '';
            $quantite = isset($product['quantite']) ? floatval($product['quantite']) : 0;
            $prix_unitaire = isset($product['prix_unitaire']) ? floatval($product['prix_unitaire']) : 0;
            $remise = isset($product['remise']) ? floatval($product['remise']) : 0;
            $montant_remise = $prix_unitaire * $quantite * ($remise / 100);
            $montant_ht_ligne = $prix_unitaire * $quantite - $montant_remise;
            $montant_tva_ligne = $montant_ht_ligne * ($taux_tva / 100);
            $montant_ttc_ligne = $montant_ht_ligne + $montant_tva_ligne;
            
            // Vérifier si le produit existe
            if ($id_produit > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM produit WHERE id_produit = :id_produit");
                $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception("Un des produits sélectionnés n'existe pas.");
                }
            } else {
                throw new Exception("Veuillez sélectionner un produit valide.");
            }
            
            // Insertion de la ligne de devis
            $stmt = $db->prepare("INSERT INTO ligne_devis 
                (id_devis, id_produit, designation, quantite, prix_unitaire, remise, montant_remise, 
                montant_ht, taux_tva, montant_tva, montant_ttc, id_user_creation) 
                VALUES 
                (:id_devis, :id_produit, :designation, :quantite, :prix_unitaire, :remise, :montant_remise, 
                :montant_ht, :taux_tva, :montant_tva, :montant_ttc, :id_user_creation)");
            
            $stmt->bindParam(':id_devis', $idDevis, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
            $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
            $stmt->bindParam(':remise', $remise, PDO::PARAM_STR);
            $stmt->bindParam(':montant_remise', $montant_remise, PDO::PARAM_STR);
            $stmt->bindParam(':montant_ht', $montant_ht_ligne, PDO::PARAM_STR);
            $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
            $stmt->bindParam(':montant_tva', $montant_tva_ligne, PDO::PARAM_STR);
            $stmt->bindParam(':montant_ttc', $montant_ttc_ligne, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            
            $stmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'devis', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        // Récupérer le nom du client pour le log
        $stmt = $db->prepare("SELECT nom_client FROM client WHERE id_client = :id_client");
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->execute();
        $nomClient = $stmt->fetchColumn();
        
        $description = "Création du devis: $numero_devis pour le client: $nomClient";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $idDevis, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le devis a été créé avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../ventes/devis/devis.view&id=" . $idDevis . "';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.history.back();
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
            window.location.href = '../ventes/devis/devis.list';
        });
    </script>";
    exit;
}
