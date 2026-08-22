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
        $numero_commande = isset($_POST['numero_commande']) ? trim($_POST['numero_commande']) : '';
        $date_commande = isset($_POST['date_commande']) ? trim($_POST['date_commande']) : '';
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $id_demande_prix = isset($_POST['id_demande_prix']) ? intval($_POST['id_demande_prix']) : null;
        $date_livraison_prevue = !empty($_POST['date_livraison_prevue']) ? trim($_POST['date_livraison_prevue']) : null;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $montant_ht = isset($_POST['montant_ht']) ? floatval($_POST['montant_ht']) : 0;
        $taux_tva = isset($_POST['taux_tva']) ? floatval($_POST['taux_tva']) : 0;
        $montant_tva = isset($_POST['montant_tva']) ? floatval($_POST['montant_tva']) : 0;
        $montant_ttc = isset($_POST['montant_ttc']) ? floatval($_POST['montant_ttc']) : 0;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if (empty($numero_commande) || empty($date_commande) || $id_fournisseur <= 0) {
            throw new Exception("Les champs Numéro de commande, Date et Fournisseur sont obligatoires.");
        }
        
        // Vérifier si le numéro de commande existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM commande_fournisseur WHERE numero_commande = :numero_commande");
        $stmt->bindParam(':numero_commande', $numero_commande, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce numéro de commande existe déjà. Veuillez en choisir un autre.");
        }
        
        // Vérifier si les produits sont fournis
        if (!isset($_POST['products']) || !is_array($_POST['products']) || count($_POST['products']) == 0) {
            throw new Exception("Aucun produit n'a été ajouté à la commande.");
        }
        
        // Insertion de la commande
        $stmt = $db->prepare("INSERT INTO commande_fournisseur 
            (numero_commande, date_commande, id_fournisseur, id_demande_prix, montant_ht, taux_tva, montant_tva, montant_ttc, 
            date_livraison_prevue, observation, etat, id_user_creation) 
            VALUES 
            (:numero_commande, :date_commande, :id_fournisseur, :id_demande_prix, :montant_ht, :taux_tva, :montant_tva, :montant_ttc, 
            :date_livraison_prevue, :observation, 'En cours', :id_user_creation)");
        
        $stmt->bindParam(':numero_commande', $numero_commande, PDO::PARAM_STR);
        $stmt->bindParam(':date_commande', $date_commande, PDO::PARAM_STR);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $stmt->bindParam(':id_demande_prix', $id_demande_prix, PDO::PARAM_INT);
        $stmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
        $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_tva', $montant_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_ttc', $montant_ttc, PDO::PARAM_STR);
        $stmt->bindParam(':date_livraison_prevue', $date_livraison_prevue, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        
        $stmt->execute();
        $idCommande = $db->lastInsertId();
        
        // Insertion des lignes de commande
        foreach ($_POST['products'] as $product) {
            $id_produit = intval($product['id_produit']);
            $designation = trim($product['designation']);
            $quantite = floatval($product['quantite']);
            $prix_unitaire = floatval($product['prix_unitaire']);
            $remise = floatval($product['remise']);
            $montant_remise = ($prix_unitaire * $quantite * $remise) / 100;
            $montant_ht_ligne = floatval($product['montant_ht']);
            $montant_tva_ligne = $montant_ht_ligne * ($taux_tva / 100);
            $montant_ttc_ligne = floatval($product['montant_ttc']);
            
            if ($id_produit <= 0 || $quantite <= 0 || $prix_unitaire <= 0) {
                throw new Exception("Données de produit invalides.");
            }
            
            $stmt = $db->prepare("INSERT INTO ligne_commande_fournisseur 
                (id_commande, id_produit, designation, quantite, prix_unitaire, remise, montant_remise, 
                montant_ht, taux_tva, montant_tva, montant_ttc, id_user_creation) 
                VALUES 
                (:id_commande, :id_produit, :designation, :quantite, :prix_unitaire, :remise, :montant_remise, 
                :montant_ht, :taux_tva, :montant_tva, :montant_ttc, :id_user_creation)");
            
            $stmt->bindParam(':id_commande', $idCommande, PDO::PARAM_INT);
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
        
        // Si la commande est créée à partir d'une demande de prix, mettre à jour l'état de la demande
        if ($id_demande_prix) {
            $stmt = $db->prepare("UPDATE demande_prix SET etat = 'Transformé' WHERE id_demande_prix = :id_demande_prix");
            $stmt->bindParam(':id_demande_prix', $id_demande_prix, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'commande_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Création de la commande fournisseur: $numero_commande";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $idCommande, PDO::PARAM_INT);
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
                text: 'La commande a été créée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../achats/commandes/commandes.view&id=" . $idCommande . "';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
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
    header('Location: ../achats/commandes/commandes.list');
    exit;
}
