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
        $id_sortie = isset($_POST['id_sortie']) ? intval($_POST['id_sortie']) : 0;
        $date_sortie = isset($_POST['date_sortie']) ? trim($_POST['date_sortie']) : '';
        $id_depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        $type_sortie = isset($_POST['type_sortie']) ? trim($_POST['type_sortie']) : '';
        $reference_document = isset($_POST['reference_document']) ? trim($_POST['reference_document']) : null;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $products = isset($_POST['products']) ? $_POST['products'] : [];
        $delete_details = isset($_POST['delete_details']) ? $_POST['delete_details'] : [];
        $id_user = $_SESSION['id'];
        
        // Validation des données principales
        if ($id_sortie <= 0 || empty($date_sortie) || $id_depot <= 0 || empty($type_sortie)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Validation des produits
        if (empty($products)) {
            throw new Exception("Veuillez ajouter au moins un produit.");
        }
        
        // Vérifier si la sortie existe et si elle est en état "En cours"
        $stmt = $db->prepare("SELECT * FROM sortie_stock WHERE id_sortie = :id_sortie");
        $stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
        $stmt->execute();
        $sortie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sortie) {
            throw new Exception("Sortie de stock non trouvée.");
        }
        
        if ($sortie['etat'] != 'En cours') {
            throw new Exception("Cette sortie de stock ne peut pas être modifiée car elle n'est pas en état 'En cours'.");
        }
        
        // Calculer le montant total de la sortie
        $montant_total = 0;
        foreach ($products as $row) {
            $quantite = isset($row['quantite']) ? floatval($row['quantite']) : 0;
            $prix_unitaire = isset($row['prix_unitaire']) ? floatval($row['prix_unitaire']) : 0;
            $montant_total += $quantite * $prix_unitaire;
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Mettre à jour l'entête de la sortie avec le montant total
        $stmt = $db->prepare("UPDATE sortie_stock 
                              SET date_sortie = :date_sortie,
                                  id_depot = :id_depot,
                                  type_sortie = :type_sortie,
                                  reference_document = :reference_document,
                                  observation = :observation,
                                  montant_total = :montant_total
                              WHERE id_sortie = :id_sortie");
        
        $stmt->bindParam(':date_sortie', $date_sortie, PDO::PARAM_STR);
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmt->bindParam(':type_sortie', $type_sortie, PDO::PARAM_STR);
        $stmt->bindParam(':reference_document', $reference_document, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
        $stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Supprimer les détails à supprimer
        if (!empty($delete_details)) {
            foreach ($delete_details as $id_detail_sortie) {
                // Supprimer d'abord les détails par lot associés
                $stmt = $db->prepare("DELETE FROM detail_sortie_lot WHERE id_detail_sortie = :id_detail_sortie");
                $stmt->bindParam(':id_detail_sortie', $id_detail_sortie, PDO::PARAM_INT);
                $stmt->execute();
                
                // Puis supprimer le détail de sortie
                $stmt = $db->prepare("DELETE FROM detail_sortie_stock WHERE id_detail_sortie = :id_detail_sortie");
                $stmt->bindParam(':id_detail_sortie', $id_detail_sortie, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        
        // Mettre à jour ou insérer les détails des produits
        foreach ($products as $row) {
            $id_detail_sortie = isset($row['id_detail_sortie']) ? intval($row['id_detail_sortie']) : 0;
            $id_produit = isset($row['id_produit']) ? intval($row['id_produit']) : 0;
            $id_lot = isset($row['id_lot']) ? intval($row['id_lot']) : 0;
            $quantite = isset($row['quantite']) ? floatval($row['quantite']) : 0;
            $prix_unitaire = isset($row['prix_unitaire']) ? floatval($row['prix_unitaire']) : 0;
            $montant_total_ligne = $quantite * $prix_unitaire;
            
            // Validation des données du produit
            if ($id_produit <= 0 || $id_lot <= 0 || $quantite <= 0 || $prix_unitaire <= 0) {
                throw new Exception("Données de produit invalides. Veuillez vérifier tous les champs.");
            }
            
            // Vérifier la disponibilité du stock
            $stmt = $db->prepare("SELECT quantite_disponible FROM lot_produit WHERE id_lot = :id_lot");
            $stmt->bindParam(':id_lot', $id_lot, PDO::PARAM_INT);
            $stmt->execute();
            $lot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lot) {
                throw new Exception("Lot non trouvé (ID: $id_lot).");
            }
            
            // Si c'est un détail existant, récupérer la quantité actuelle pour l'ajouter au stock dispo
            $current_quantity = 0;
            if ($id_detail_sortie > 0) {
                $stmt = $db->prepare("SELECT dl.quantite 
                                     FROM detail_sortie_lot dl
                                     WHERE dl.id_detail_sortie = :id_detail_sortie AND dl.id_lot = :id_lot");
                $stmt->bindParam(':id_detail_sortie', $id_detail_sortie, PDO::PARAM_INT);
                $stmt->bindParam(':id_lot', $id_lot, PDO::PARAM_INT);
                $stmt->execute();
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current) {
                    $current_quantity = floatval($current['quantite']);
                }
            }
            
            // Vérifier si la nouvelle quantité dépasse le stock disponible
            $stock_reel = $lot['quantite_disponible'] + $current_quantity;
            if ($quantite > $stock_reel) {
                throw new Exception("Stock insuffisant pour le lot ID: $id_lot. Stock disponible: $stock_reel, Quantité demandée: $quantite");
            }
            
            if ($id_detail_sortie > 0) {
                // Mettre à jour le détail existant
                $stmt = $db->prepare("UPDATE detail_sortie_stock 
                                     SET id_produit = :id_produit,
                                         quantite = :quantite,
                                         prix_unitaire = :prix_unitaire,
                                         montant_total = :montant_total
                                     WHERE id_detail_sortie = :id_detail_sortie");
                
                $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmt->bindParam(':montant_total', $montant_total_ligne, PDO::PARAM_STR);
                $stmt->bindParam(':id_detail_sortie', $id_detail_sortie, PDO::PARAM_INT);
                
                $stmt->execute();
                
                // Mettre à jour le détail du lot
                $stmt = $db->prepare("UPDATE detail_sortie_lot 
                                     SET id_lot = :id_lot,
                                         quantite = :quantite,
                                         prix_unitaire = :prix_unitaire,
                                         montant_total = :montant_total
                                     WHERE id_detail_sortie = :id_detail_sortie");
                
                $stmt->bindParam(':id_lot', $id_lot, PDO::PARAM_INT);
                $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmt->bindParam(':montant_total', $montant_total_ligne, PDO::PARAM_STR);
                $stmt->bindParam(':id_detail_sortie', $id_detail_sortie, PDO::PARAM_INT);
                
                $stmt->execute();
            } else {
                // Insérer un nouveau détail
                $stmt = $db->prepare("INSERT INTO detail_sortie_stock 
                                     (id_sortie, id_produit, quantite, prix_unitaire, montant_total, id_user_creation) 
                                     VALUES 
                                     (:id_sortie, :id_produit, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
                
                $stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
                $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmt->bindParam(':montant_total', $montant_total_ligne, PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
                
                $stmt->execute();
                $id_detail_sortie = $db->lastInsertId();
                
                // Insérer le détail du lot
                $stmt = $db->prepare("INSERT INTO detail_sortie_lot 
                                     (id_detail_sortie, id_lot, quantite, prix_unitaire, montant_total, id_user_creation) 
                                     VALUES 
                                     (:id_detail_sortie, :id_lot, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
                
                $stmt->bindParam(':id_detail_sortie', $id_detail_sortie, PDO::PARAM_INT);
                $stmt->bindParam(':id_lot', $id_lot, PDO::PARAM_INT);
                $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmt->bindParam(':montant_total', $montant_total_ligne, PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
                
                $stmt->execute();
            }
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'sortie_stock', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Modification de la sortie de stock: {$sortie['numero_sortie']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_sortie, PDO::PARAM_INT);
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
                text: 'La sortie de stock a été mise à jour avec succès.',
                                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../stock/stock.sortie.view&id=" . $id_sortie . "';
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
                window.location.href = '../stock/stock.sortie.edit&id=" . $id_sortie . "';
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
            window.location.href = '../stock/stock.sortie.list';
        });
    </script>";
    exit;
}
