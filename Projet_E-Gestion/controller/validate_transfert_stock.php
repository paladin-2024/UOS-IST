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

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idTransfert = intval($_GET['id']);
    $idUser = $_SESSION['id'];
    
    try {
        // Vérifier si le transfert existe et s'il est en état "En cours"
        $stmt = $db->prepare("SELECT t.*, d1.libelle_depot as depot_source, d2.libelle_depot as depot_destination 
                             FROM transfert_stock t
                             LEFT JOIN depot d1 ON t.id_depot_source = d1.id_depot
                             LEFT JOIN depot d2 ON t.id_depot_destination = d2.id_depot
                             WHERE t.id_transfert = :id_transfert");
        $stmt->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
        $stmt->execute();
        $transfert = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transfert) {
            throw new Exception("Transfert de stock non trouvé.");
        }
        
        if ($transfert['etat'] != 'En cours') {
            throw new Exception("Ce transfert de stock ne peut pas être validé car il n'est pas en état 'En cours'.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Récupérer tous les détails du transfert
        $stmt = $db->prepare("SELECT dt.*, p.libelle_produit, l.numero_lot, l.date_peremption, 
                                    l.prix_unitaire_achat, l.prix_unitaire_vente
                             FROM detail_transfert_stock dt
                             LEFT JOIN produit p ON dt.id_produit = p.id_produit
                                                          LEFT JOIN lot_produit l ON dt.id_lot = l.id_lot
                             WHERE dt.id_transfert = :id_transfert");
        $stmt->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
        $stmt->execute();
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($details)) {
            throw new Exception("Ce transfert ne contient aucun produit.");
        }
        
        // Créer une entrée de stock dans le dépôt de destination
        // Générer un numéro d'entrée (format: EN-YYYYMMDD-XXX)
        $date = date('Ymd');
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_entree, 13) AS UNSIGNED)) as last_num 
                             FROM entree_stock 
                             WHERE numero_entree LIKE :prefix");
        $prefix = "EN-{$date}-%";
        $stmt->bindParam(':prefix', $prefix);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastNum = $result['last_num'] ?? 0;
        $newNum = $lastNum + 1;
        $numeroEntree = "EN-{$date}-" . str_pad($newNum, 3, '0', STR_PAD_LEFT);
        
        // Créer l'en-tête de l'entrée
        $stmt = $db->prepare("INSERT INTO entree_stock 
                             (numero_entree, date_entree, id_depot, type_entree, reference_document,
                              observation, etat, id_user_creation, date_creation) 
                             VALUES 
                             (:numero_entree, :date_entree, :id_depot, 'Transfert', :reference_document,
                              :observation, 'Validé', :id_user_creation, NOW())");
        $stmt->bindParam(':numero_entree', $numeroEntree);
        $stmt->bindParam(':date_entree', $transfert['date_transfert']);
        $stmt->bindParam(':id_depot', $transfert['id_depot_destination'], PDO::PARAM_INT);
        $stmt->bindParam(':reference_document', $transfert['numero_transfert']);
        $observation = "Entrée créée automatiquement par transfert depuis " . $transfert['depot_source'];
        $stmt->bindParam(':observation', $observation);
        $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
        $stmt->execute();
        
        $idEntree = $db->lastInsertId();
        
        // Créer une sortie de stock dans le dépôt source
        // Générer un numéro de sortie (format: SO-YYYYMMDD-XXX)
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_sortie, 13) AS UNSIGNED)) as last_num 
                             FROM sortie_stock 
                             WHERE numero_sortie LIKE :prefix");
        $prefix = "SO-{$date}-%";
        $stmt->bindParam(':prefix', $prefix);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastNum = $result['last_num'] ?? 0;
        $newNum = $lastNum + 1;
        $numeroSortie = "SO-{$date}-" . str_pad($newNum, 3, '0', STR_PAD_LEFT);
        
        // Créer l'en-tête de la sortie
        $stmt = $db->prepare("INSERT INTO sortie_stock 
                             (numero_sortie, date_sortie, id_depot, type_sortie, reference_document,
                              observation, etat, id_user_creation, date_creation) 
                             VALUES 
                             (:numero_sortie, :date_sortie, :id_depot, 'Transfert', :reference_document,
                              :observation, 'Validé', :id_user_creation, NOW())");
        $stmt->bindParam(':numero_sortie', $numeroSortie);
        $stmt->bindParam(':date_sortie', $transfert['date_transfert']);
        $stmt->bindParam(':id_depot', $transfert['id_depot_source'], PDO::PARAM_INT);
        $stmt->bindParam(':reference_document', $transfert['numero_transfert']);
        $observation = "Sortie créée automatiquement par transfert vers " . $transfert['depot_destination'];
        $stmt->bindParam(':observation', $observation);
        $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
        $stmt->execute();
        
        $idSortie = $db->lastInsertId();
        
        // Traiter chaque détail du transfert
        foreach ($details as $detail) {
            $idProduit = $detail['id_produit'];
            $idLot = $detail['id_lot'];
            $quantite = $detail['quantite'];
            
            // Vérifier si la quantité disponible dans le lot est toujours suffisante
            $stmt = $db->prepare("SELECT * FROM lot_produit WHERE id_lot = :id_lot");
            $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
            $stmt->execute();
            $lot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lot || $lot['quantite_disponible'] < $quantite) {
                throw new Exception("La quantité disponible pour le produit " . $detail['libelle_produit'] . " (lot " . $detail['numero_lot'] . ") est insuffisante.");
            }
            
            // Créer un détail de sortie dans le dépôt source
            $stmt = $db->prepare("INSERT INTO detail_sortie_stock 
                                 (id_sortie, id_produit, quantite, prix_unitaire, montant_total, id_user_creation) 
                                 VALUES 
                                 (:id_sortie, :id_produit, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
            $stmt->bindParam(':id_sortie', $idSortie, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
            $stmt->bindParam(':quantite', $quantite);
            $stmt->bindParam(':prix_unitaire', $lot['prix_unitaire_achat']);
            $montantTotal = $quantite * $lot['prix_unitaire_achat'];
            $stmt->bindParam(':montant_total', $montantTotal);
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
            
            $idDetailSortie = $db->lastInsertId();
            
            // Créer un détail de sortie de lot
            $stmt = $db->prepare("INSERT INTO detail_sortie_lot 
                                 (id_detail_sortie, id_lot, quantite, prix_unitaire, montant_total, id_user_creation) 
                                 VALUES 
                                 (:id_detail_sortie, :id_lot, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
            $stmt->bindParam(':id_detail_sortie', $idDetailSortie, PDO::PARAM_INT);
            $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
            $stmt->bindParam(':quantite', $quantite);
            $stmt->bindParam(':prix_unitaire', $lot['prix_unitaire_achat']);
            $stmt->bindParam(':montant_total', $montantTotal);
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
            
            // Mettre à jour la quantité disponible dans le lot source
            $stmt = $db->prepare("UPDATE lot_produit 
                                 SET quantite_disponible = quantite_disponible - :quantite 
                                 WHERE id_lot = :id_lot");
            $stmt->bindParam(':quantite', $quantite);
            $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
            $stmt->execute();
            
            // Créer un détail d'entrée dans le dépôt destination
            $stmt = $db->prepare("INSERT INTO detail_entree_stock 
                                 (id_entree, id_produit, quantite, prix_unitaire, montant_total, id_user_creation) 
                                 VALUES 
                                 (:id_entree, :id_produit, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
            $stmt->bindParam(':id_entree', $idEntree, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
            $stmt->bindParam(':quantite', $quantite);
            $stmt->bindParam(':prix_unitaire', $lot['prix_unitaire_achat']);
            $stmt->bindParam(':montant_total', $montantTotal);
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
            
            $idDetailEntree = $db->lastInsertId();
            
            // Créer ou mettre à jour le lot dans le dépôt destination
            // D'abord, vérifier si un lot avec le même numéro existe déjà dans le dépôt destination
            $stmt = $db->prepare("SELECT l.* 
                                 FROM lot_produit l
                                 JOIN detail_entree_stock de ON l.id_detail_entree = de.id_detail_entree
                                 JOIN entree_stock e ON de.id_entree = e.id_entree
                                 WHERE l.numero_lot = :numero_lot 
                                 AND l.id_produit = :id_produit 
                                 AND e.id_depot = :id_depot");
            $stmt->bindParam(':numero_lot', $lot['numero_lot']);
            $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
            $stmt->bindParam(':id_depot', $transfert['id_depot_destination'], PDO::PARAM_INT);
            $stmt->execute();
            $lotExistant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lotExistant) {
                // Mettre à jour le lot existant
                $stmt = $db->prepare("UPDATE lot_produit 
                                     SET quantite_disponible = quantite_disponible + :quantite 
                                     WHERE id_lot = :id_lot");
                $stmt->bindParam(':quantite', $quantite);
                $stmt->bindParam(':id_lot', $lotExistant['id_lot'], PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // Créer un nouveau lot dans le dépôt destination
                $stmt = $db->prepare("INSERT INTO lot_produit 
                                     (numero_lot, id_produit, id_detail_entree, quantite_initiale, 
                                      quantite_disponible, prix_unitaire_achat, prix_unitaire_vente, 
                                      date_peremption, date_creation) 
                                     VALUES 
                                     (:numero_lot, :id_produit, :id_detail_entree, :quantite_initiale, 
                                      :quantite_disponible, :prix_unitaire_achat, :prix_unitaire_vente, 
                                      :date_peremption, NOW())");
                $stmt->bindParam(':numero_lot', $lot['numero_lot']);
                $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
                $stmt->bindParam(':id_detail_entree', $idDetailEntree, PDO::PARAM_INT);
                $stmt->bindParam(':quantite_initiale', $quantite);
                $stmt->bindParam(':quantite_disponible', $quantite);
                $stmt->bindParam(':prix_unitaire_achat', $lot['prix_unitaire_achat']);
                $stmt->bindParam(':prix_unitaire_vente', $lot['prix_unitaire_vente']);
                $stmt->bindParam(':date_peremption', $lot['date_peremption']);
                $stmt->execute();
            }
        }
        
        // Mettre à jour l'état du transfert
        $stmt = $db->prepare("UPDATE transfert_stock 
                             SET etat = 'Validé', id_user_validation = :id_user_validation, 
                             date_validation = NOW() 
                             WHERE id_transfert = :id_transfert");
        $stmt->bindParam(':id_user_validation', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
        $stmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le transfert de stock a été validé avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../stock/transfert.view&id=" . $idTransfert . "';
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
    // Redirection si l'ID n'est pas valide
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Identifiant de transfert invalide.',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../stock/transfert.list';
        });
    </script>";
    exit;
}
