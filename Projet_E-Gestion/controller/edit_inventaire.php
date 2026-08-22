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
        // Récupérer l'ID de l'inventaire à modifier
        $idInventaire = isset($_POST['id_inventaire']) ? intval($_POST['id_inventaire']) : 0;
        
        if ($idInventaire <= 0) {
            throw new Exception("ID d'inventaire invalide.");
        }
        
        // Vérifier si l'inventaire existe et n'est pas déjà validé
        $stmt = $db->prepare("SELECT * FROM inventaire WHERE id_inventaire = :id_inventaire");
        $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmt->execute();
        $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inventaire) {
            throw new Exception("L'inventaire spécifié n'existe pas.");
        }
        
        if ($inventaire['etat'] != 'En cours') {
            throw new Exception("Impossible de modifier cet inventaire car il n'est plus en cours.");
        }
        
        // Récupérer les données du formulaire
        $depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        $dateInventaire = isset($_POST['date_inventaire']) ? $_POST['date_inventaire'] : date('Y-m-d');
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : '';
        $idUser = $_SESSION['id'];
        
        $produits = isset($_POST['produits']) ? $_POST['produits'] : [];
        $lots = isset($_POST['lots']) ? $_POST['lots'] : [];
        $stockTheorique = isset($_POST['stock_theorique']) ? $_POST['stock_theorique'] : [];
        $stockPhysique = isset($_POST['stock_physique']) ? $_POST['stock_physique'] : [];
        $ecart = isset($_POST['ecart']) ? $_POST['ecart'] : [];
        $prixUnitaire = isset($_POST['prix_unitaire']) ? $_POST['prix_unitaire'] : [];
        $detailIds = isset($_POST['detail_id']) ? $_POST['detail_id'] : [];
        
        // Validation des données
        if ($depot <= 0) {
            throw new Exception("Veuillez sélectionner un dépôt valide.");
        }
        
        if (empty($produits) || empty($lots)) {
            throw new Exception("Veuillez ajouter au moins un produit à l'inventaire.");
        }
        
        // Commencer une transaction
        $db->beginTransaction();
        
        // Mettre à jour l'en-tête de l'inventaire
        $stmt = $db->prepare("UPDATE inventaire 
                             SET date_inventaire = :date_inventaire, 
                                 observation = :observation
                             WHERE id_inventaire = :id_inventaire");
        $stmt->bindParam(':date_inventaire', $dateInventaire);
        $stmt->bindParam(':observation', $observation);
        $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmt->execute();
        
        // Supprimer les détails existants qui ne sont plus présents
        if (!empty($detailIds)) {
            $placeholders = implode(',', array_fill(0, count($detailIds), '?'));
            $stmt = $db->prepare("DELETE FROM detail_inventaire 
                                 WHERE id_inventaire = ? AND id_detail_inventaire NOT IN ($placeholders)");
            $params = array_merge([$idInventaire], $detailIds);
            $stmt->execute($params);
        } else {
            // Si aucun détail n'est conservé, supprimer tous les détails
            $stmt = $db->prepare("DELETE FROM detail_inventaire WHERE id_inventaire = ?");
            $stmt->execute([$idInventaire]);
        }
        
        // Mise à jour ou insertion des détails de l'inventaire
        for ($i = 0; $i < count($produits); $i++) {
            // Vérifier que les données nécessaires existent
            if (!isset($produits[$i]) || !isset($lots[$i]) || !isset($stockTheorique[$i]) || !isset($stockPhysique[$i])) {
                continue; // Passer à l'itération suivante si les données sont incomplètes
            }
            
            $idProduit = intval($produits[$i]);
            $idLot = intval($lots[$i]);
            $stockTheo = floatval($stockTheorique[$i]);
            $stockPhys = floatval($stockPhysique[$i]);
            $ecartValue = floatval($stockPhys - $stockTheo);
            $prix = floatval($prixUnitaire[$i]);
            $detailId = isset($detailIds[$i]) ? intval($detailIds[$i]) : 0;
            
            if ($detailId > 0) {
                // Mettre à jour un détail existant
                $stmt = $db->prepare("UPDATE detail_inventaire 
                                     SET id_produit = :id_produit, 
                                         id_lot = :id_lot, 
                                         stock_theorique = :stock_theorique, 
                                         stock_physique = :stock_physique, 
                                         ecart = :ecart, 
                                         prix_unitaire = :prix_unitaire
                                     WHERE id_detail_inventaire = :id_detail_inventaire 
                                     AND id_inventaire = :id_inventaire");
                $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
                $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
                $stmt->bindParam(':stock_theorique', $stockTheo);
                $stmt->bindParam(':stock_physique', $stockPhys);
                $stmt->bindParam(':ecart', $ecartValue);
                $stmt->bindParam(':prix_unitaire', $prix);
                $stmt->bindParam(':id_detail_inventaire', $detailId, PDO::PARAM_INT);
                $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // Insérer un nouveau détail
                $stmt = $db->prepare("INSERT INTO detail_inventaire 
                                     (id_inventaire, id_produit, id_lot, stock_theorique, 
                                      stock_physique, ecart, prix_unitaire, 
                                      id_user_creation, date_creation) 
                                     VALUES 
                                     (:id_inventaire, :id_produit, :id_lot, :stock_theorique, 
                                      :stock_physique, :ecart, :prix_unitaire, 
                                      :id_user_creation, NOW())");
                $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
                $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
                $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
                $stmt->bindParam(':stock_theorique', $stockTheo);
                $stmt->bindParam(':stock_physique', $stockPhys);
                $stmt->bindParam(':ecart', $ecartValue);
                $stmt->bindParam(':prix_unitaire', $prix);
                $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'L\'inventaire a été modifié avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../stock/inventaire.view&id=" . $idInventaire . "';
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
    header('Location: ../stock/inventaire.list');
    exit();
}
