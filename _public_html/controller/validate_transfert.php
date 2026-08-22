<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

// Récupérer l'ID utilisateur et son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1);

// Valider l'ID du transfert
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Identifiant de transfert invalide'
        }).then(function() {
            window.location.href = '../stock/transfert.list';
        });
    </script>";
    exit();
}

$idTransfert = intval($_GET['id']);

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier si l'utilisateur a le droit de valider des transferts pour le dépôt source
    if (!$isAdmin) {
        $queryCheckPermission = "
            SELECT t.id_transfert 
            FROM transfert_stock t
            JOIN autorisation_depot ad ON t.id_depot_source = ad.id_depot
            WHERE t.id_transfert = :id_transfert
            AND ad.id_user = :id_user
            AND ad.peut_valider = 1";
        
        $stmtCheckPermission = $db->prepare($queryCheckPermission);
        $stmtCheckPermission->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
        $stmtCheckPermission->bindParam(':id_user', $userId, PDO::PARAM_INT);
        $stmtCheckPermission->execute();
        
        if ($stmtCheckPermission->rowCount() == 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Accès refusé',
                    text: 'Vous n\'avez pas les droits nécessaires pour valider ce transfert'
                }).then(function() {
                    window.location.href = '../stock/transfert.list';
                });
            </script>";
            exit();
        }
    }
    
    // Récupérer les informations du transfert
    $queryTransfert = "
        SELECT t.*, d1.libelle_depot as depot_source, d2.libelle_depot as depot_destination 
        FROM transfert_stock t
        JOIN depot d1 ON t.id_depot_source = d1.id_depot
        JOIN depot d2 ON t.id_depot_destination = d2.id_depot
        WHERE t.id_transfert = :id_transfert";
    
    $stmtTransfert = $db->prepare($queryTransfert);
    $stmtTransfert->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
    $stmtTransfert->execute();
    
    $transfert = $stmtTransfert->fetch(PDO::FETCH_ASSOC);
    
    if (!$transfert) {
        throw new Exception("Transfert introuvable");
    }
    
    if ($transfert['etat'] !== 'En cours') {
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Action impossible',
                text: 'Ce transfert a déjà été " . strtolower($transfert['etat']) . "'
            }).then(function() {
                window.location.href = '../stock/transfert.list';
            });
        </script>";
        exit();
    }
    
    // Récupérer les détails du transfert
    $queryDetails = "
        SELECT dt.*, p.libelle_produit, l.numero_lot, l.date_peremption,
               l.prix_unitaire_achat, l.prix_unitaire_vente, l.quantite_disponible
        FROM detail_transfert_stock dt
        JOIN produit p ON dt.id_produit = p.id_produit
        JOIN lot_produit l ON dt.id_lot = l.id_lot
        WHERE dt.id_transfert = :id_transfert";
    
    $stmtDetails = $db->prepare($queryDetails);
    $stmtDetails->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
    $stmtDetails->execute();
    
    $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($details)) {
        throw new Exception("Aucun produit trouvé dans ce transfert");
    }
    
    // Commencer la transaction
    $db->beginTransaction();
    
    // Créer une sortie de stock pour le dépôt source
    $numeroSortie = 'SOR' . date('ymd') . '-' . sprintf('%04d', rand(1, 9999));
    $dateSortie = date('Y-m-d');
    $depotSource = $transfert['id_depot_source'];
    $observation = "Sortie automatique pour transfert " . $transfert['numero_transfert'];
    
    $querySortie = "
        INSERT INTO sortie_stock (
            numero_sortie, date_sortie, id_depot, type_sortie, 
            reference_document, observation, etat, 
            id_user_creation, date_creation, id_user_validation, 
            date_validation, montant_total)
        VALUES (
            :numero_sortie, :date_sortie, :id_depot, 'Transfert',
            :reference_document, :observation, 'Validé',
            :id_user_creation, NOW(), :id_user_validation,
            NOW(), 0)";
    
    $stmtSortie = $db->prepare($querySortie);
    $stmtSortie->bindParam(':numero_sortie', $numeroSortie, PDO::PARAM_STR);
    $stmtSortie->bindParam(':date_sortie', $dateSortie, PDO::PARAM_STR);
    $stmtSortie->bindParam(':id_depot', $depotSource, PDO::PARAM_INT);
    $stmtSortie->bindParam(':reference_document', $transfert['numero_transfert'], PDO::PARAM_STR);
    $stmtSortie->bindParam(':observation', $observation, PDO::PARAM_STR);
    $stmtSortie->bindParam(':id_user_creation', $userId, PDO::PARAM_INT);
    $stmtSortie->bindParam(':id_user_validation', $userId, PDO::PARAM_INT);
    $stmtSortie->execute();
    
    $idSortie = $db->lastInsertId();
    $montantTotalSortie = 0;
    
    // Créer une entrée de stock pour le dépôt destination
    $numeroEntree = 'ENT' . date('ymd') . '-' . sprintf('%04d', rand(1, 9999));
    $dateEntree = date('Y-m-d');
    $depotDestination = $transfert['id_depot_destination'];
    $observationEntree = "Entrée automatique pour transfert " . $transfert['numero_transfert'];
    
    $queryEntree = "
        INSERT INTO entree_stock (
            numero_entree, date_entree, id_depot, type_entree, 
            reference_document, observation, etat, 
            id_user_creation, date_creation, id_user_validation, 
            date_validation)
        VALUES (
            :numero_entree, :date_entree, :id_depot, 'Transfert',
            :reference_document, :observation, 'Validé',
            :id_user_creation, NOW(), :id_user_validation,
            NOW())";
    
    $stmtEntree = $db->prepare($queryEntree);
    $stmtEntree->bindParam(':numero_entree', $numeroEntree, PDO::PARAM_STR);
    $stmtEntree->bindParam(':date_entree', $dateEntree, PDO::PARAM_STR);
    $stmtEntree->bindParam(':id_depot', $depotDestination, PDO::PARAM_INT);
    $stmtEntree->bindParam(':reference_document', $transfert['numero_transfert'], PDO::PARAM_STR);
    $stmtEntree->bindParam(':observation', $observationEntree, PDO::PARAM_STR);
    $stmtEntree->bindParam(':id_user_creation', $userId, PDO::PARAM_INT);
    $stmtEntree->bindParam(':id_user_validation', $userId, PDO::PARAM_INT);
    $stmtEntree->execute();
    
    $idEntree = $db->lastInsertId();
    
    // Traiter chaque produit du transfert
    foreach ($details as $detail) {
        // Vérifier si la quantité à transférer est disponible
        if ($detail['quantite'] > $detail['quantite_disponible']) {
            throw new Exception("Quantité insuffisante pour le lot " . $detail['numero_lot'] . " du produit " . $detail['libelle_produit']);
        }
        
        // 1. Mettre à jour la quantité du lot source
        $queryUpdateLotSource = "
            UPDATE lot_produit 
            SET quantite_disponible = quantite_disponible - :quantite 
            WHERE id_lot = :id_lot";
        
        $stmtUpdateLotSource = $db->prepare($queryUpdateLotSource);
        $stmtUpdateLotSource->bindParam(':quantite', $detail['quantite'], PDO::PARAM_STR);
        $stmtUpdateLotSource->bindParam(':id_lot', $detail['id_lot'], PDO::PARAM_INT);
        $stmtUpdateLotSource->execute();
        
        // 2. Créer un détail de sortie
        $montantTotalDetail = $detail['quantite'] * $detail['prix_unitaire_achat'];
        $montantTotalSortie += $montantTotalDetail;
        
        $queryDetailSortie = "
            INSERT INTO detail_sortie_stock (
                id_sortie, id_produit, quantite, prix_unitaire, 
                montant_total, id_user_creation, date_creation)
            VALUES (
                :id_sortie, :id_produit, :quantite, :prix_unitaire,
                :montant_total, :id_user_creation, NOW())";
        
        $stmtDetailSortie = $db->prepare($queryDetailSortie);
        $stmtDetailSortie->bindParam(':id_sortie', $idSortie, PDO::PARAM_INT);
        $stmtDetailSortie->bindParam(':id_produit', $detail['id_produit'], PDO::PARAM_INT);
        $stmtDetailSortie->bindParam(':quantite', $detail['quantite'], PDO::PARAM_STR);
        $stmtDetailSortie->bindParam(':prix_unitaire', $detail['prix_unitaire_achat'], PDO::PARAM_STR);
        $stmtDetailSortie->bindParam(':montant_total', $montantTotalDetail, PDO::PARAM_STR);
        $stmtDetailSortie->bindParam(':id_user_creation', $userId, PDO::PARAM_INT);
        $stmtDetailSortie->execute();
        
        $idDetailSortie = $db->lastInsertId();
        
        // 3. Ajouter le détail lot à la sortie
        $queryDetailSortieLot = "
            INSERT INTO detail_sortie_lot (
                id_detail_sortie, id_lot, quantite, prix_unitaire,
                montant_total, id_user_creation, date_creation)
            VALUES (
                :id_detail_sortie, :id_lot, :quantite, :prix_unitaire,
                :montant_total, :id_user_creation, NOW())";
        
        $stmtDetailSortieLot = $db->prepare($queryDetailSortieLot);
        $stmtDetailSortieLot->bindParam(':id_detail_sortie', $idDetailSortie, PDO::PARAM_INT);
        $stmtDetailSortieLot->bindParam(':id_lot', $detail['id_lot'], PDO::PARAM_INT);
        $stmtDetailSortieLot->bindParam(':quantite', $detail['quantite'], PDO::PARAM_STR);
        $stmtDetailSortieLot->bindParam(':prix_unitaire', $detail['prix_unitaire_achat'], PDO::PARAM_STR);
        $stmtDetailSortieLot->bindParam(':montant_total', $montantTotalDetail, PDO::PARAM_STR);
        $stmtDetailSortieLot->bindParam(':id_user_creation', $userId, PDO::PARAM_INT);
        $stmtDetailSortieLot->execute();
        
        // 4. Créer un détail d'entrée pour le dépôt destination
        $queryDetailEntree = "
            INSERT INTO detail_entree_stock (
                id_entree, id_produit, quantite, prix_unitaire,
                montant_total, id_user_creation, date_creation)
            VALUES (
                :id_entree, :id_produit, :quantite, :prix_unitaire,
                :montant_total, :id_user_creation, NOW())";
        
        $stmtDetailEntree = $db->prepare($queryDetailEntree);
        $stmtDetailEntree->bindParam(':id_entree', $idEntree, PDO::PARAM_INT);
        $stmtDetailEntree->bindParam(':id_produit', $detail['id_produit'], PDO::PARAM_INT);
        $stmtDetailEntree->bindParam(':quantite', $detail['quantite'], PDO::PARAM_STR);
        $stmtDetailEntree->bindParam(':prix_unitaire', $detail['prix_unitaire_achat'], PDO::PARAM_STR);
        $stmtDetailEntree->bindParam(':montant_total', $montantTotalDetail, PDO::PARAM_STR);
        $stmtDetailEntree->bindParam(':id_user_creation', $userId, PDO::PARAM_INT);
        $stmtDetailEntree->execute();
        
        $idDetailEntree = $db->lastInsertId();
        
        // 5. Créer un nouveau lot dans le dépôt destination
        // D'abord vérifier si un lot identique existe déjà dans le dépôt destination
        $queryCheckLot = "
            SELECT l.id_lot, l.quantite_disponible 
            FROM lot_produit l
            JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
            JOIN entree_stock es ON des.id_entree = es.id_entree
            WHERE l.id_produit = :id_produit
            AND l.numero_lot = :numero_lot
            AND es.id_depot = :id_depot
            AND es.etat = 'Validé'";
        
        $stmtCheckLot = $db->prepare($queryCheckLot);
        $stmtCheckLot->bindParam(':id_produit', $detail['id_produit'], PDO::PARAM_INT);
        $stmtCheckLot->bindParam(':numero_lot', $detail['numero_lot'], PDO::PARAM_STR);
        $stmtCheckLot->bindParam(':id_depot', $depotDestination, PDO::PARAM_INT);
        $stmtCheckLot->execute();
        
        $lotExistant = $stmtCheckLot->fetch(PDO::FETCH_ASSOC);
        
        if ($lotExistant) {
            // Mettre à jour la quantité du lot existant
            $queryUpdateLot = "
                UPDATE lot_produit 
                SET quantite_disponible = quantite_disponible + :quantite 
                WHERE id_lot = :id_lot";
            
            $stmtUpdateLot = $db->prepare($queryUpdateLot);
            $stmtUpdateLot->bindParam(':quantite', $detail['quantite'], PDO::PARAM_STR);
            $stmtUpdateLot->bindParam(':id_lot', $lotExistant['id_lot'], PDO::PARAM_INT);
            $stmtUpdateLot->execute();
        } else {
            // Créer un nouveau lot
            $queryNewLot = "
                INSERT INTO lot_produit (
                    numero_lot, id_produit, id_detail_entree, 
                    quantite_initiale, quantite_disponible, 
                    prix_unitaire_achat, prix_unitaire_vente, 
                    date_peremption, date_creation)
                VALUES (
                    :numero_lot, :id_produit, :id_detail_entree,
                    :quantite_initiale, :quantite_disponible,
                    :prix_unitaire_achat, :prix_unitaire_vente,
                    :date_peremption, NOW())";
            
            $stmtNewLot = $db->prepare($queryNewLot);
            $stmtNewLot->bindParam(':numero_lot', $detail['numero_lot'], PDO::PARAM_STR);
            $stmtNewLot->bindParam(':id_produit', $detail['id_produit'], PDO::PARAM_INT);
            $stmtNewLot->bindParam(':id_detail_entree', $idDetailEntree, PDO::PARAM_INT);
            $stmtNewLot->bindParam(':quantite_initiale', $detail['quantite'], PDO::PARAM_STR);
            $stmtNewLot->bindParam(':quantite_disponible', $detail['quantite'], PDO::PARAM_STR);
            $stmtNewLot->bindParam(':prix_unitaire_achat', $detail['prix_unitaire_achat'], PDO::PARAM_STR);
            $stmtNewLot->bindParam(':prix_unitaire_vente', $detail['prix_unitaire_vente'], PDO::PARAM_STR);
            $stmtNewLot->bindParam(':date_peremption', $detail['date_peremption'], PDO::PARAM_STR);
            $stmtNewLot->execute();
        }
    }
    
    // Mettre à jour le montant total de la sortie
    $queryUpdateSortie = "
        UPDATE sortie_stock 
        SET montant_total = :montant_total 
        WHERE id_sortie = :id_sortie";
    
    $stmtUpdateSortie = $db->prepare($queryUpdateSortie);
    $stmtUpdateSortie->bindParam(':montant_total', $montantTotalSortie, PDO::PARAM_STR);
    $stmtUpdateSortie->bindParam(':id_sortie', $idSortie, PDO::PARAM_INT);
    $stmtUpdateSortie->execute();
    
    // Mettre à jour le statut du transfert à "Validé"
    $queryUpdateTransfert = "
        UPDATE transfert_stock 
        SET etat = 'Validé', 
            id_user_validation = :id_user_validation, 
            date_validation = NOW() 
        WHERE id_transfert = :id_transfert";
    
    $stmtUpdateTransfert = $db->prepare($queryUpdateTransfert);
    $stmtUpdateTransfert->bindParam(':id_user_validation', $userId, PDO::PARAM_INT);
    $stmtUpdateTransfert->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
    $stmtUpdateTransfert->execute();
    
    // Ajouter une entrée dans le journal des opérations
    $queryLog = "
        INSERT INTO log_operation (
            id_user, date_operation, type_operation, 
            table_concernee, id_enregistrement, description, 
            adresse_ip, navigateur)
        VALUES (
            :id_user, NOW(), 'Validation', 
            'transfert_stock', :id_enregistrement, :description, 
            :adresse_ip, :navigateur)";
    
    $description = "Validation du transfert {$transfert['numero_transfert']} du dépôt {$transfert['depot_source']} vers {$transfert['depot_destination']}";
    $adresseIp = $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
    $navigateur = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';
    
    $stmtLog = $db->prepare($queryLog);
    $stmtLog->bindParam(':id_user', $userId, PDO::PARAM_INT);
    $stmtLog->bindParam(':id_enregistrement', $idTransfert, PDO::PARAM_INT);
    $stmtLog->bindParam(':description', $description, PDO::PARAM_STR);
    $stmtLog->bindParam(':adresse_ip', $adresseIp, PDO::PARAM_STR);
    $stmtLog->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
    $stmtLog->execute();
    
    // Valider la transaction
    $db->commit();
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Transfert validé',
            text: 'Le transfert a été validé avec succès. Une entrée et une sortie de stock ont été automatiquement créées.',
            showConfirmButton: true
        }).then(function() {
            window.location.href = '../stock/transfert.list';
        });
    </script>";
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $db->rollBack();
    
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la validation du transfert: " . addslashes($e->getMessage()) . "',
            showConfirmButton: true
        }).then(function() {
            window.location.href = '../stock/transfert.list';
        });
    </script>";
}

