<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); // Ajustez selon votre logique de rôles

$db = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données du formulaire
        $id_entree = intval($_POST['id_entree'] ?? 0);
        $date_entree = $_POST['date_entree'] ?? '';
        $id_depot = intval($_POST['id_depot'] ?? 0);
        $type_entree = $_POST['type_entree'] ?? '';
        $reference_document = $_POST['reference_document'] ?? '';
        $observation = $_POST['observation'] ?? '';
        $products = $_POST['products'] ?? [];

        // Validation des données
        if ($id_entree <= 0 || empty($date_entree) || $id_depot <= 0 || empty($type_entree) || empty($products)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        // Récupérer les informations de l'entrée actuelle
        $queryEntree = "SELECT * FROM entree_stock WHERE id_entree = :id_entree";
        $stmtEntree = $db->prepare($queryEntree);
        $stmtEntree->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
        $stmtEntree->execute();
        $entree = $stmtEntree->fetch(PDO::FETCH_ASSOC);

        if (!$entree) {
            throw new Exception("L'entrée de stock spécifiée n'existe pas.");
        }

        // Vérifier si l'entrée est encore modifiable
        if ($entree['etat'] !== 'En cours') {
            throw new Exception("Cette entrée ne peut plus être modifiée car elle est déjà " . strtolower($entree['etat']) . ".");
        }

        // Vérifier les autorisations de l'utilisateur pour ce dépôt
        if (!$isAdmin) {
            $permQuery = "SELECT peut_modifier 
                         FROM autorisation_depot 
                         WHERE id_user = :user_id AND id_depot = :depot_id";
            $permStmt = $db->prepare($permQuery);
            $permStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $permStmt->bindParam(':depot_id', $id_depot, PDO::PARAM_INT);
            $permStmt->execute();
            $permission = $permStmt->fetch(PDO::FETCH_ASSOC);

            if (!$permission || $permission['peut_modifier'] != 1) {
                throw new Exception("Vous n'avez pas l'autorisation de modifier des entrées pour ce dépôt.");
            }
        }

        // Commencer la transaction
        $db->beginTransaction();

        // Mettre à jour l'entête d'entrée
        $updateQuery = "UPDATE entree_stock SET 
                        date_entree = :date_entree,
                        id_depot = :id_depot,
                        type_entree = :type_entree,
                        reference_document = :reference_document,
                        observation = :observation
                        WHERE id_entree = :id_entree";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':date_entree', $date_entree);
        $updateStmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $updateStmt->bindParam(':type_entree', $type_entree);
        $updateStmt->bindParam(':reference_document', $reference_document);
        $updateStmt->bindParam(':observation', $observation);
        $updateStmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
        $updateStmt->execute();

        // Récupérer les détails existants pour les comparer
        $queryExistingDetails = "SELECT id_detail_entree, id_produit FROM detail_entree_stock WHERE id_entree = :id_entree";
        $stmtExistingDetails = $db->prepare($queryExistingDetails);
        $stmtExistingDetails->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
        $stmtExistingDetails->execute();
        $existingDetails = $stmtExistingDetails->fetchAll(PDO::FETCH_KEY_PAIR);

        // Traiter chaque ligne de produit
        foreach ($products as $product) {
            $id_detail_entree = isset($product['id_detail_entree']) ? intval($product['id_detail_entree']) : 0;
            $id_produit = intval($product['id_produit']);
            $quantite = floatval($product['quantite']);
            $prix_unitaire = floatval($product['prix_unitaire']);
            $montant_total = $quantite * $prix_unitaire;
            $numero_lot = $product['numero_lot'];
            $date_peremption = empty($product['date_peremption']) ? null : $product['date_peremption'];

            // Si c'est une ligne existante, la mettre à jour
            if ($id_detail_entree > 0 && isset($existingDetails[$id_detail_entree])) {
                $updateDetailQuery = "UPDATE detail_entree_stock SET 
                                     id_produit = :id_produit,
                                     quantite = :quantite,
                                     prix_unitaire = :prix_unitaire,
                                     montant_total = :montant_total
                                     WHERE id_detail_entree = :id_detail_entree";
                
                $updateDetailStmt = $db->prepare($updateDetailQuery);
                $updateDetailStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $updateDetailStmt->bindParam(':quantite', $quantite);
                $updateDetailStmt->bindParam(':prix_unitaire', $prix_unitaire);
                $updateDetailStmt->bindParam(':montant_total', $montant_total);
                $updateDetailStmt->bindParam(':id_detail_entree', $id_detail_entree, PDO::PARAM_INT);
                $updateDetailStmt->execute();

                // Mettre à jour les informations du lot
                $updateLotQuery = "UPDATE lot_produit SET 
                                  numero_lot = :numero_lot,
                                  date_peremption = :date_peremption
                                  WHERE id_detail_entree = :id_detail_entree";
                
                $updateLotStmt = $db->prepare($updateLotQuery);
                $updateLotStmt->bindParam(':numero_lot', $numero_lot);
                $updateLotStmt->bindParam(':date_peremption', $date_peremption);
                $updateLotStmt->bindParam(':id_detail_entree', $id_detail_entree, PDO::PARAM_INT);
                $updateLotStmt->execute();

                                // Retirer cette ligne de la liste des existants pour identifier
                // celles qui doivent être supprimées
                unset($existingDetails[$id_detail_entree]);
            } 
            // Sinon, c'est une nouvelle ligne à ajouter
            else {
                // Insérer la nouvelle ligne de détail
                $insertDetailQuery = "INSERT INTO detail_entree_stock 
                                    (id_entree, id_produit, quantite, prix_unitaire, montant_total, id_user_creation)
                                    VALUES 
                                    (:id_entree, :id_produit, :quantite, :prix_unitaire, :montant_total, :id_user_creation)";
                
                $insertDetailStmt = $db->prepare($insertDetailQuery);
                $insertDetailStmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
                $insertDetailStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $insertDetailStmt->bindParam(':quantite', $quantite);
                $insertDetailStmt->bindParam(':prix_unitaire', $prix_unitaire);
                $insertDetailStmt->bindParam(':montant_total', $montant_total);
                $insertDetailStmt->bindParam(':id_user_creation', $userId, PDO::PARAM_INT);
                $insertDetailStmt->execute();
                
                $id_detail_entree = $db->lastInsertId();
                
                // Insérer les informations du lot
                $insertLotQuery = "INSERT INTO lot_produit 
                                 (numero_lot, id_produit, id_detail_entree, quantite_initiale, 
                                 quantite_disponible, prix_unitaire_achat, prix_unitaire_vente, date_peremption)
                                 VALUES 
                                 (:numero_lot, :id_produit, :id_detail_entree, :quantite, 
                                 :quantite, :prix_unitaire, :prix_unitaire_vente, :date_peremption)";
                
                // Calculer le prix de vente (en utilisant la marge bénéficiaire si disponible)
                $queryProduit = "SELECT marge_beneficiaire FROM produit WHERE id_produit = :id_produit";
                $stmtProduit = $db->prepare($queryProduit);
                $stmtProduit->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmtProduit->execute();
                $produit = $stmtProduit->fetch(PDO::FETCH_ASSOC);
                
                $marge = $produit ? floatval($produit['marge_beneficiaire']) : 0;
                $prix_unitaire_vente = $prix_unitaire * (1 + $marge / 100);
                
                $insertLotStmt = $db->prepare($insertLotQuery);
                $insertLotStmt->bindParam(':numero_lot', $numero_lot);
                $insertLotStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $insertLotStmt->bindParam(':id_detail_entree', $id_detail_entree, PDO::PARAM_INT);
                $insertLotStmt->bindParam(':quantite', $quantite);
                $insertLotStmt->bindParam(':prix_unitaire', $prix_unitaire);
                $insertLotStmt->bindParam(':prix_unitaire_vente', $prix_unitaire_vente);
                $insertLotStmt->bindParam(':date_peremption', $date_peremption);
                $insertLotStmt->execute();
            }
        }
        
        // Supprimer les lignes qui ne sont plus présentes
        if (!empty($existingDetails)) {
            // Vérifier d'abord si ces lignes n'ont pas déjà été utilisées dans des sorties
            $idsToCheck = array_keys($existingDetails);
            $placeholders = implode(',', array_fill(0, count($idsToCheck), '?'));
            
            $queryUsage = "SELECT id_lot FROM detail_sortie_lot dsl
                           INNER JOIN lot_produit lp ON dsl.id_lot = lp.id_lot
                           WHERE lp.id_detail_entree IN ($placeholders)";
            
            $stmtUsage = $db->prepare($queryUsage);
            foreach ($idsToCheck as $key => $id) {
                $stmtUsage->bindValue($key + 1, $id, PDO::PARAM_INT);
            }
            $stmtUsage->execute();
            
            if ($stmtUsage->rowCount() > 0) {
                throw new Exception("Certains produits ne peuvent pas être supprimés car ils ont déjà été utilisés dans des sorties de stock.");
            }
            
            // Supprimer d'abord les lots associés
            $deleteLotQuery = "DELETE FROM lot_produit WHERE id_detail_entree IN ($placeholders)";
            $deleteLotStmt = $db->prepare($deleteLotQuery);
            foreach ($idsToCheck as $key => $id) {
                $deleteLotStmt->bindValue($key + 1, $id, PDO::PARAM_INT);
            }
            $deleteLotStmt->execute();
            
            // Puis supprimer les détails
            $deleteDetailQuery = "DELETE FROM detail_entree_stock WHERE id_detail_entree IN ($placeholders)";
            $deleteDetailStmt = $db->prepare($deleteDetailQuery);
            foreach ($idsToCheck as $key => $id) {
                $deleteDetailStmt->bindValue($key + 1, $id, PDO::PARAM_INT);
            }
            $deleteDetailStmt->execute();
        }
        
        // Journalisation de l'action
        $logQuery = "INSERT INTO log_operation 
                    (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
                    VALUES 
                    (:id_user, 'modification', 'entree_stock', :id_enregistrement, :description, :adresse_ip, :navigateur)";
        
        $description = "Modification de l'entrée de stock numéro {$entree['numero_entree']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_entree, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description);
        $logStmt->bindParam(':adresse_ip', $adresse_ip);
        $logStmt->bindParam(':navigateur', $navigateur);
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'L\'entrée de stock a été mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../stock/stock.entree.list';
            });
        </script>";
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
} else {
    // Redirection si accès direct
    header("Location: ../stock/stock.entree.list");
    exit();
}
?>
