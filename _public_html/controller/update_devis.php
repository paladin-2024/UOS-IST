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
        $id_devis = isset($_POST['id_devis']) ? intval($_POST['id_devis']) : 0;
        $date_devis = isset($_POST['date_devis']) ? trim($_POST['date_devis']) : '';
        $id_client = isset($_POST['id_client']) ? intval($_POST['id_client']) : 0;
        $validite = isset($_POST['validite']) ? intval($_POST['validite']) : 30;
        $taux_tva = isset($_POST['taux_tva']) ? floatval($_POST['taux_tva']) : 0.00;
        $montant_ht = isset($_POST['montant_ht']) ? floatval($_POST['montant_ht']) : 0.00;
        $montant_tva = isset($_POST['montant_tva']) ? floatval($_POST['montant_tva']) : 0.00;
        $montant_ttc = isset($_POST['montant_ttc']) ? floatval($_POST['montant_ttc']) : 0.00;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if ($id_devis <= 0 || empty($date_devis) || $id_client <= 0) {
            throw new Exception("Les champs ID devis, Date et Client sont obligatoires.");
        }
        
        // Vérifier si le devis existe et s'il est en état "En cours"
        $query = "SELECT * FROM devis WHERE id_devis = :id_devis";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
        $stmt->execute();
        $devis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$devis) {
            throw new Exception("Le devis demandé n'existe pas.");
        }
        
        if ($devis['etat'] != 'En cours') {
            throw new Exception("Seuls les devis en état 'En cours' peuvent être modifiés.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Mise à jour du devis
        $query = "UPDATE devis SET 
                  date_devis = :date_devis,
                  id_client = :id_client,
                  validite = :validite,
                  taux_tva = :taux_tva,
                  montant_ht = :montant_ht,
                  montant_tva = :montant_tva,
                  montant_ttc = :montant_ttc,
                  observation = :observation
                  WHERE id_devis = :id_devis";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':date_devis', $date_devis, PDO::PARAM_STR);
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->bindParam(':validite', $validite, PDO::PARAM_INT);
        $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
        $stmt->bindParam(':montant_tva', $montant_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_ttc', $montant_ttc, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Traitement des lignes de devis
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            $products = $_POST['products'];
            
            // Récupérer les IDs des lignes existantes
            $query = "SELECT id_ligne_devis FROM ligne_devis WHERE id_devis = :id_devis";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
            $stmt->execute();
            $existingLines = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Suivre les lignes traitées pour identifier celles à supprimer
            $processedLines = [];
            
            foreach ($products as $product) {
                $id_ligne_devis = isset($product['id_ligne_devis']) ? intval($product['id_ligne_devis']) : 0;
                $id_produit = isset($product['id_produit']) ? intval($product['id_produit']) : 0;
                $designation = isset($product['designation']) ? trim($product['designation']) : '';
                $quantite = isset($product['quantite']) ? floatval($product['quantite']) : 0;
                $prix_unitaire = isset($product['prix_unitaire']) ? floatval($product['prix_unitaire']) : 0;
                $remise = isset($product['remise']) ? floatval($product['remise']) : 0;
                $montant_ht = isset($product['montant_ht']) ? floatval($product['montant_ht']) : 0;
                
                // Calculer les montants
                $montant_remise = ($prix_unitaire * $quantite * $remise) / 100;
                $montant_ht = ($prix_unitaire * $quantite) - $montant_remise;
                $montant_tva_ligne = ($montant_ht * $taux_tva) / 100;
                $montant_ttc_ligne = $montant_ht + $montant_tva_ligne;
                
                if ($id_ligne_devis > 0 && in_array($id_ligne_devis, $existingLines)) {
                    // Mettre à jour une ligne existante
                    $query = "UPDATE ligne_devis SET 
                              id_produit = :id_produit,
                              designation = :designation,
                              quantite = :quantite,
                              prix_unitaire = :prix_unitaire,
                              remise = :remise,
                              montant_remise = :montant_remise,
                              montant_ht = :montant_ht,
                              taux_tva = :taux_tva,
                              montant_tva = :montant_tva,
                              montant_ttc = :montant_ttc
                                                            WHERE id_ligne_devis = :id_ligne_devis";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
                    $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                    $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                    $stmt->bindParam(':remise', $remise, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_remise', $montant_remise, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
                    $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_tva', $montant_tva_ligne, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_ttc', $montant_ttc_ligne, PDO::PARAM_STR);
                    $stmt->bindParam(':id_ligne_devis', $id_ligne_devis, PDO::PARAM_INT);
                    
                    $stmt->execute();
                    
                    // Marquer cette ligne comme traitée
                    $processedLines[] = $id_ligne_devis;
                } else {
                    // Ajouter une nouvelle ligne
                    $query = "INSERT INTO ligne_devis 
                              (id_devis, id_produit, designation, quantite, prix_unitaire, remise, montant_remise, 
                              montant_ht, taux_tva, montant_tva, montant_ttc, id_user_creation) 
                              VALUES 
                              (:id_devis, :id_produit, :designation, :quantite, :prix_unitaire, :remise, :montant_remise, 
                              :montant_ht, :taux_tva, :montant_tva, :montant_ttc, :id_user_creation)";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
                    $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
                    $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                    $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                    $stmt->bindParam(':remise', $remise, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_remise', $montant_remise, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
                    $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_tva', $montant_tva_ligne, PDO::PARAM_STR);
                    $stmt->bindParam(':montant_ttc', $montant_ttc_ligne, PDO::PARAM_STR);
                    $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
                    
                    $stmt->execute();
                    
                    // Récupérer l'ID de la nouvelle ligne
                    $newLineId = $db->lastInsertId();
                    $processedLines[] = $newLineId;
                }
            }
            
            // Supprimer les lignes qui n'ont pas été traitées (supprimées par l'utilisateur)
            $linesToDelete = array_diff($existingLines, $processedLines);
            if (!empty($linesToDelete)) {
                $placeholders = implode(',', array_fill(0, count($linesToDelete), '?'));
                $query = "DELETE FROM ligne_devis WHERE id_ligne_devis IN ($placeholders)";
                $stmt = $db->prepare($query);
                
                // Binder les valeurs
                foreach ($linesToDelete as $index => $lineId) {
                    $stmt->bindValue($index + 1, $lineId, PDO::PARAM_INT);
                }
                
                $stmt->execute();
            }
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'devis', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        // Récupérer les informations du client pour le log
        $query = "SELECT c.nom_client, d.numero_devis 
                  FROM devis d 
                  JOIN client c ON d.id_client = c.id_client 
                  WHERE d.id_devis = :id_devis";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
        $stmt->execute();
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $description = "Modification du devis: " . $info['numero_devis'] . " pour le client: " . $info['nom_client'];
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_devis, PDO::PARAM_INT);
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
                text: 'Le devis a été mis à jour avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../ventes/devis/devis.view&id=" . $id_devis . "';
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
                window.location.href = '../ventes/devis/devis.edit&id=" . $id_devis . "';
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
