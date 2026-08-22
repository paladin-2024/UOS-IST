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
        $id_reception = isset($_POST['id_reception']) ? intval($_POST['id_reception']) : 0;
        $date_reception = isset($_POST['date_reception']) ? trim($_POST['date_reception']) : '';
        $id_depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        $reference_bl = isset($_POST['reference_bl']) ? trim($_POST['reference_bl']) : '';
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : '';
        $id_user = $_SESSION['id'];
        
        // Validation des données de base
        if ($id_reception <= 0 || empty($date_reception) || $id_depot <= 0) {
            throw new Exception("Les champs ID, Date et Dépôt sont obligatoires.");
        }
        
        // Vérifier si la réception existe et est en état "En cours"
        $queryCheck = "SELECT r.*, f.nom_fournisseur 
                      FROM reception_fournisseur r 
                      JOIN fournisseur f ON r.id_fournisseur = f.id_fournisseur 
                      WHERE r.id_reception = :id_reception";
        $stmtCheck = $db->prepare($queryCheck);
        $stmtCheck->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
        $stmtCheck->execute();
        $reception = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$reception) {
            throw new Exception("Réception non trouvée.");
        }
        
        if ($reception['etat'] !== 'En cours') {
            throw new Exception("Cette réception ne peut pas être modifiée car elle n'est pas en état 'En cours'.");
        }
        
        // Mettre à jour la réception
        $queryUpdate = "UPDATE reception_fournisseur SET 
            date_reception = :date_reception,
            id_depot = :id_depot,
            reference_bl = :reference_bl,
            observation = :observation
            WHERE id_reception = :id_reception";
        
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->bindParam(':date_reception', $date_reception, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':reference_bl', $reference_bl, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
        $stmtUpdate->execute();
        
        // Traiter les lignes existantes
        if (isset($_POST['lignes'])) {
            foreach ($_POST['lignes'] as $ligne) {
                $id_ligne_reception = intval($ligne['id_ligne_reception']);
                $id_produit = intval($ligne['id_produit']);
                $quantite = floatval($ligne['quantite']);
                $prix_unitaire = floatval($ligne['prix_unitaire']);
                $montant_total = $quantite * $prix_unitaire;
                $numero_lot = $ligne['numero_lot'];
                $date_peremption = !empty($ligne['date_peremption']) ? $ligne['date_peremption'] : null;
                
                // Mettre à jour la ligne
                $queryUpdateLigne = "UPDATE ligne_reception_fournisseur SET 
                    quantite = :quantite,
                    prix_unitaire = :prix_unitaire,
                    montant_total = :montant_total,
                    numero_lot = :numero_lot,
                    date_peremption = :date_peremption
                    WHERE id_ligne_reception = :id_ligne_reception";
                
                $stmtUpdateLigne = $db->prepare($queryUpdateLigne);
                $stmtUpdateLigne->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmtUpdateLigne->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmtUpdateLigne->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
                $stmtUpdateLigne->bindParam(':numero_lot', $numero_lot, PDO::PARAM_STR);
                $stmtUpdateLigne->bindParam(':date_peremption', $date_peremption, $date_peremption === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtUpdateLigne->bindParam(':id_ligne_reception', $id_ligne_reception, PDO::PARAM_INT);
                $stmtUpdateLigne->execute();
            }
        }
        
        // Traiter les produits supplémentaires
        if (isset($_POST['produits_additionnels'])) {
            foreach ($_POST['produits_additionnels'] as $produit) {
                if (empty($produit['id_produit'])) {
                    continue; // Ignorer les lignes vides
                }
                
                $id_produit = intval($produit['id_produit']);
                $designation = isset($produit['designation']) ? $produit['designation'] : '';
                
                // Si la désignation est vide, récupérer le libellé du produit
                if (empty($designation)) {
                    $queryProduit = "SELECT libelle_produit FROM produit WHERE id_produit = :id_produit";
                    $stmtProduit = $db->prepare($queryProduit);
                    $stmtProduit->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                    $stmtProduit->execute();
                    $produitData = $stmtProduit->fetch(PDO::FETCH_ASSOC);
                    $designation = $produitData['libelle_produit'];
                }
                
                $quantite = floatval($produit['quantite']);
                $prix_unitaire = floatval($produit['prix_unitaire']);
                $montant_total = $quantite * $prix_unitaire;
                $numero_lot = $produit['numero_lot'];
                $date_peremption = !empty($produit['date_peremption']) ? $produit['date_peremption'] : null;
                
                // Insérer la nouvelle ligne
                $queryInsertLigne = "INSERT INTO ligne_reception_fournisseur (
                    id_reception, id_produit, designation, quantite, 
                    prix_unitaire, montant_total, numero_lot, date_peremption, id_user_creation
                ) VALUES (
                    :id_reception, :id_produit, :designation, :quantite, 
                    :prix_unitaire, :montant_total, :numero_lot, :date_peremption, :id_user_creation
                )";
                
                $stmtInsertLigne = $db->prepare($queryInsertLigne);
                $stmtInsertLigne->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
                $stmtInsertLigne->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmtInsertLigne->bindParam(':designation', $designation, PDO::PARAM_STR);
                $stmtInsertLigne->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmtInsertLigne->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmtInsertLigne->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
                $stmtInsertLigne->bindParam(':numero_lot', $numero_lot, PDO::PARAM_STR);
                $stmtInsertLigne->bindParam(':date_peremption', $date_peremption, $date_peremption === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtInsertLigne->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
                $stmtInsertLigne->execute();
            }
        }

        // Ajouter ici le code pour mettre à jour le montant total
        $queryUpdateMontantTotal = "UPDATE reception_fournisseur 
        SET montant_total = (
            SELECT COALESCE(SUM(montant_total), 0) 
            FROM ligne_reception_fournisseur 
            WHERE id_reception = :id_reception
        )
        WHERE id_reception = :id_reception";

        $stmtUpdateMontantTotal = $db->prepare($queryUpdateMontantTotal);
        $stmtUpdateMontantTotal->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
        $stmtUpdateMontantTotal->execute();
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'reception_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Modification de la réception N° {$reception['numero_reception']} pour le fournisseur {$reception['nom_fournisseur']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_reception, PDO::PARAM_INT);
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
                text: 'La réception a été modifiée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = '../achats/receptions/receptions.view&id=$id_reception';
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
            }).then(() => {
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
        }).then(() => {
            window.location.href = '../achats/receptions/receptions.list';
        });
    </script>";
    exit;
}
