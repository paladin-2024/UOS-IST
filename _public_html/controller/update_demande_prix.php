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
        $id_demande_prix = isset($_POST['id_demande_prix']) ? intval($_POST['id_demande_prix']) : 0;
        $date_demande = isset($_POST['date_demande']) ? trim($_POST['date_demande']) : '';
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if ($id_demande_prix <= 0 || empty($date_demande) || $id_fournisseur <= 0) {
            throw new Exception("Les champs ID, Date et Fournisseur sont obligatoires.");
        }
        
        // Vérifier si la demande existe et est modifiable
        $stmt = $db->prepare("SELECT * FROM demande_prix WHERE id_demande_prix = :id");
        $stmt->bindParam(':id', $id_demande_prix, PDO::PARAM_INT);
        $stmt->execute();
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande) {
            throw new Exception("Demande de prix non trouvée.");
        }
        
        if ($demande['etat'] != 'En cours') {
            throw new Exception("Seules les demandes en cours peuvent être modifiées.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Mise à jour de la demande
        $stmt = $db->prepare("UPDATE demande_prix 
            SET date_demande = :date_demande, 
                id_fournisseur = :id_fournisseur, 
                observation = :observation 
            WHERE id_demande_prix = :id_demande_prix");
        
        $stmt->bindParam(':date_demande', $date_demande, PDO::PARAM_STR);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_demande_prix', $id_demande_prix, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Vérifier si des produits ont été soumis
        if (!isset($_POST['products']) || !is_array($_POST['products']) || count($_POST['products']) == 0) {
            throw new Exception("Aucun produit n'a été ajouté à la demande.");
        }
        
        // Traitement des lignes de produits
        foreach ($_POST['products'] as $product) {
            $id_ligne_demande = isset($product['id_ligne_demande']) ? intval($product['id_ligne_demande']) : 0;
            $id_produit = intval($product['id_produit']);
            $quantite = floatval($product['quantite']);
            $designation = trim($product['designation']);
            
            if ($id_produit <= 0 || $quantite <= 0 || empty($designation)) {
                throw new Exception("Données de produit invalides.");
            }
            
            if ($id_ligne_demande > 0) {
                // Mise à jour d'une ligne existante
                $stmt = $db->prepare("UPDATE ligne_demande_prix 
                    SET id_produit = :id_produit, 
                        designation = :designation, 
                        quantite = :quantite 
                    WHERE id_ligne_demande = :id_ligne_demande 
                    AND id_demande_prix = :id_demande_prix");
                
                $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
                $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmt->bindParam(':id_ligne_demande', $id_ligne_demande, PDO::PARAM_INT);
                $stmt->bindParam(':id_demande_prix', $id_demande_prix, PDO::PARAM_INT);
                
                $stmt->execute();
            } else {
                // Ajout d'une nouvelle ligne
                $stmt = $db->prepare("INSERT INTO ligne_demande_prix 
                    (id_demande_prix, id_produit, designation, quantite, id_user_creation) 
                    VALUES 
                    (:id_demande_prix, :id_produit, :designation, :quantite, :id_user_creation)");
                
                $stmt->bindParam(':id_demande_prix', $id_demande_prix, PDO::PARAM_INT);
                $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
                $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                
                $stmt->execute();
            }
        }
        
        // Récupérer toutes les lignes existantes
        $stmt = $db->prepare("SELECT id_ligne_demande FROM ligne_demande_prix WHERE id_demande_prix = :id_demande_prix");
        $stmt->bindParam(':id_demande_prix', $id_demande_prix, PDO::PARAM_INT);
        $stmt->execute();
        $existingLines = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Déterminer les lignes à supprimer (celles qui ne sont plus dans le formulaire)
        $submittedLines = array_filter(array_column($_POST['products'], 'id_ligne_demande'));
        $linesToDelete = array_diff($existingLines, $submittedLines);
        
        // Supprimer les lignes qui ne sont plus présentes
        if (!empty($linesToDelete)) {
            $placeholders = implode(',', array_fill(0, count($linesToDelete), '?'));
            $stmt = $db->prepare("DELETE FROM ligne_demande_prix 
                                  WHERE id_ligne_demande IN ($placeholders) 
                                  AND id_demande_prix = ?");
            
            $params = array_merge($linesToDelete, [$id_demande_prix]);
            $stmt->execute($params);
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'demande_prix', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Modification de la demande de prix: {$demande['numero_demande']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_demande_prix, PDO::PARAM_INT);
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
                text: 'La demande de prix a été mise à jour avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../achats/demandes/demandes.view&id=" . $id_demande_prix . "';
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
                window.location.href = '../achats/demandes/demandes.edit&id=" . $id_demande_prix . "';
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
            window.location.href = '../achats/demandes/demandes.list';
        });
    </script>";
    exit;
}
