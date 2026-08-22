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
        $id_commande = isset($_POST['id_commande']) ? intval($_POST['id_commande']) : 0;
        $numero_commande = isset($_POST['numero_commande']) ? trim($_POST['numero_commande']) : '';
        $date_commande = isset($_POST['date_commande']) ? trim($_POST['date_commande']) : '';
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $date_livraison_prevue = !empty($_POST['date_livraison_prevue']) ? trim($_POST['date_livraison_prevue']) : null;
        $taux_tva = isset($_POST['taux_tva']) ? floatval($_POST['taux_tva']) : 0.00;
        $montant_ht = isset($_POST['montant_ht']) ? floatval($_POST['montant_ht']) : 0.00;
        $montant_tva = isset($_POST['montant_tva']) ? floatval($_POST['montant_tva']) : 0.00;
        $montant_ttc = isset($_POST['montant_ttc']) ? floatval($_POST['montant_ttc']) : 0.00;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $products = isset($_POST['products']) ? $_POST['products'] : [];
        
        // Validation des données
        if (empty($numero_commande) || empty($date_commande) || $id_fournisseur <= 0) {
            throw new Exception("Les champs Numéro de commande, Date et Fournisseur sont obligatoires.");
        }
        
        if (empty($products)) {
            throw new Exception("Vous devez ajouter au moins un produit à la commande.");
        }
        
        // Vérifier si la commande existe et est modifiable
        $queryCheck = "SELECT etat FROM commande_fournisseur WHERE id_commande = :id";
        $stmtCheck = $db->prepare($queryCheck);
        $stmtCheck->bindParam(':id', $id_commande, PDO::PARAM_INT);
        $stmtCheck->execute();
        $commande = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande) {
            throw new Exception("Commande non trouvée.");
        }
        
        if ($commande['etat'] !== 'En cours') {
            throw new Exception("Seules les commandes en état 'En cours' peuvent être modifiées.");
        }
        
        // Vérifier si le numéro de commande existe déjà pour une autre commande
        $queryNumero = "SELECT id_commande FROM commande_fournisseur 
                        WHERE numero_commande = :numero_commande 
                        AND id_commande != :id_commande";
        $stmtNumero = $db->prepare($queryNumero);
        $stmtNumero->bindParam(':numero_commande', $numero_commande, PDO::PARAM_STR);
        $stmtNumero->bindParam(':id_commande', $id_commande, PDO::PARAM_INT);
        $stmtNumero->execute();
        
        if ($stmtNumero->rowCount() > 0) {
            throw new Exception("Ce numéro de commande existe déjà. Veuillez en choisir un autre.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Mettre à jour la commande
        $updateQuery = "UPDATE commande_fournisseur 
                       SET numero_commande = :numero_commande,
                           date_commande = :date_commande,
                                                      id_fournisseur = :id_fournisseur,
                           date_livraison_prevue = :date_livraison_prevue,
                           taux_tva = :taux_tva,
                           montant_ht = :montant_ht,
                           montant_tva = :montant_tva,
                           montant_ttc = :montant_ttc,
                           observation = :observation
                       WHERE id_commande = :id_commande";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':numero_commande', $numero_commande, PDO::PARAM_STR);
        $updateStmt->bindParam(':date_commande', $date_commande, PDO::PARAM_STR);
        $updateStmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $updateStmt->bindParam(':date_livraison_prevue', $date_livraison_prevue, PDO::PARAM_STR);
        $updateStmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
        $updateStmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
        $updateStmt->bindParam(':montant_tva', $montant_tva, PDO::PARAM_STR);
        $updateStmt->bindParam(':montant_ttc', $montant_ttc, PDO::PARAM_STR);
        $updateStmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $updateStmt->bindParam(':id_commande', $id_commande, PDO::PARAM_INT);
        
        $updateStmt->execute();
        
        // Récupérer les lignes existantes pour pouvoir supprimer celles qui ne sont plus présentes
        $queryExistingLines = "SELECT id_ligne_commande FROM ligne_commande_fournisseur WHERE id_commande = :id_commande";
        $stmtExistingLines = $db->prepare($queryExistingLines);
        $stmtExistingLines->bindParam(':id_commande', $id_commande, PDO::PARAM_INT);
        $stmtExistingLines->execute();
        $existingLines = $stmtExistingLines->fetchAll(PDO::FETCH_COLUMN);
        
        // Tableau pour stocker les IDs des lignes à conserver
        $linesToKeep = [];
        
        // Traiter chaque produit
        foreach ($products as $product) {
            $id_ligne_commande = isset($product['id_ligne_commande']) ? intval($product['id_ligne_commande']) : 0;
            $id_produit = isset($product['id_produit']) ? intval($product['id_produit']) : 0;
            $designation = isset($product['designation']) ? trim($product['designation']) : '';
            $quantite = isset($product['quantite']) ? floatval($product['quantite']) : 0;
            $prix_unitaire = isset($product['prix_unitaire']) ? floatval($product['prix_unitaire']) : 0;
            $remise = isset($product['remise']) ? floatval($product['remise']) : 0;
            $montant_remise = ($quantite * $prix_unitaire) * ($remise / 100);
            $montant_ht = isset($product['montant_ht']) ? floatval($product['montant_ht']) : 0;
            $montant_tva = $montant_ht * ($taux_tva / 100);
            $montant_ttc = $montant_ht + $montant_tva;
            
            // Vérifier les données obligatoires
            if ($id_produit <= 0 || empty($designation) || $quantite <= 0 || $prix_unitaire <= 0) {
                throw new Exception("Tous les champs des produits doivent être remplis correctement.");
            }
            
            if ($id_ligne_commande > 0) {
                // Mettre à jour une ligne existante
                $linesToKeep[] = $id_ligne_commande;
                
                $updateLineQuery = "UPDATE ligne_commande_fournisseur 
                                   SET id_produit = :id_produit,
                                       designation = :designation,
                                       quantite = :quantite,
                                       prix_unitaire = :prix_unitaire,
                                       remise = :remise,
                                       montant_remise = :montant_remise,
                                       montant_ht = :montant_ht,
                                       taux_tva = :taux_tva,
                                       montant_tva = :montant_tva,
                                       montant_ttc = :montant_ttc
                                   WHERE id_ligne_commande = :id_ligne_commande";
                
                $updateLineStmt = $db->prepare($updateLineQuery);
                $updateLineStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $updateLineStmt->bindParam(':designation', $designation, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':remise', $remise, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':montant_remise', $montant_remise, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':montant_tva', $montant_tva, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':montant_ttc', $montant_ttc, PDO::PARAM_STR);
                $updateLineStmt->bindParam(':id_ligne_commande', $id_ligne_commande, PDO::PARAM_INT);
                
                $updateLineStmt->execute();
            } else {
                // Ajouter une nouvelle ligne
                $insertLineQuery = "INSERT INTO ligne_commande_fournisseur 
                                   (id_commande, id_produit, designation, quantite, prix_unitaire, 
                                    remise, montant_remise, montant_ht, taux_tva, montant_tva, montant_ttc, 
                                    id_user_creation, date_creation) 
                                   VALUES 
                                   (:id_commande, :id_produit, :designation, :quantite, :prix_unitaire, 
                                    :remise, :montant_remise, :montant_ht, :taux_tva, :montant_tva, :montant_ttc, 
                                    :id_user_creation, NOW())";
                
                $insertLineStmt = $db->prepare($insertLineQuery);
                $insertLineStmt->bindParam(':id_commande', $id_commande, PDO::PARAM_INT);
                $insertLineStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $insertLineStmt->bindParam(':designation', $designation, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':remise', $remise, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':montant_remise', $montant_remise, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':montant_tva', $montant_tva, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':montant_ttc', $montant_ttc, PDO::PARAM_STR);
                $insertLineStmt->bindParam(':id_user_creation', $_SESSION['id'], PDO::PARAM_INT);
                
                $insertLineStmt->execute();
                
                // Récupérer l'ID de la nouvelle ligne
                $newLineId = $db->lastInsertId();
                $linesToKeep[] = $newLineId;
            }
        }
        
        // Supprimer les lignes qui ne sont plus présentes
        $linesToDelete = array_diff($existingLines, $linesToKeep);
        if (!empty($linesToDelete)) {
            $placeholders = implode(',', array_fill(0, count($linesToDelete), '?'));
            $deleteQuery = "DELETE FROM ligne_commande_fournisseur WHERE id_ligne_commande IN ($placeholders)";
            $deleteStmt = $db->prepare($deleteQuery);
            
            // Binder chaque ID à supprimer
            foreach ($linesToDelete as $index => $lineId) {
                $deleteStmt->bindValue($index + 1, $lineId, PDO::PARAM_INT);
            }
            
            $deleteStmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'commande_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Modification de la commande fournisseur: $numero_commande";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $_SESSION['id'], PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_commande, PDO::PARAM_INT);
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
                text: 'La commande a été modifiée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../achats/commandes/commandes.view&id=$id_commande';
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
                window.location.href = '../achats/commandes/commandes.edit&id=$id_commande';
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
            window.location.href = '../achats/commandes/commandes.list';
        });
    </script>";
    exit;
}

