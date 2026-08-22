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
        $depotSource = isset($_POST['id_depot_source']) ? intval($_POST['id_depot_source']) : 0;
        $depotDestination = isset($_POST['id_depot_destination']) ? intval($_POST['id_depot_destination']) : 0;
        $dateTransfert = isset($_POST['date_transfert']) ? $_POST['date_transfert'] : date('Y-m-d');
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : '';

        // Initialiser les tableaux pour traiter les produits
        $produits = [];
        $lots = [];
        $quantites = [];

        // Extraire les données des produits depuis la structure imbriquée
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $product) {
                if (isset($product['id_produit']) && isset($product['id_lot']) && isset($product['quantite'])) {
                    $produits[] = $product['id_produit'];
                    $lots[] = $product['id_lot'];
                    $quantites[] = $product['quantite'];
                }
            }
        }

        $idUser = $_SESSION['id'];
        
        // Validation des données
        if ($depotSource <= 0 || $depotDestination <= 0) {
            throw new Exception("Veuillez sélectionner les dépôts source et destination.");
        }
        
        if ($depotSource == $depotDestination) {
            throw new Exception("Les dépôts source et destination ne peuvent pas être identiques.");
        }
        
        if (empty($produits) || empty($lots) || empty($quantites)) {
            throw new Exception("Veuillez ajouter au moins un produit au transfert.");
        }
        
        // Vérifier que la date n'est pas dans le futur
        if (strtotime($dateTransfert) > strtotime(date('Y-m-d'))) {
            throw new Exception("La date de transfert ne peut pas être dans le futur.");
        }
        
        // Commencer une transaction
        $db->beginTransaction();
        
        // Générer le numéro de transfert (format: TR-YYYYMMDD-XXX)
        $date = date('Ymd');
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_transfert, 13) AS UNSIGNED)) as last_num 
                             FROM transfert_stock 
                             WHERE numero_transfert LIKE :prefix");
        $prefix = "TR-{$date}-%";
        $stmt->bindParam(':prefix', $prefix);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastNum = $result['last_num'] ?? 0;
        $newNum = $lastNum + 1;
        $numeroTransfert = "TR-{$date}-" . str_pad($newNum, 3, '0', STR_PAD_LEFT);
        
        // Insérer l'en-tête du transfert
        $stmt = $db->prepare("INSERT INTO transfert_stock 
                             (numero_transfert, date_transfert, id_depot_source, id_depot_destination, 
                              observation, etat, id_user_creation, date_creation) 
                             VALUES 
                             (:numero_transfert, :date_transfert, :id_depot_source, :id_depot_destination, 
                              :observation, 'En cours', :id_user_creation, NOW())");
        $stmt->bindParam(':numero_transfert', $numeroTransfert);
        $stmt->bindParam(':date_transfert', $dateTransfert);
        $stmt->bindParam(':id_depot_source', $depotSource, PDO::PARAM_INT);
        $stmt->bindParam(':id_depot_destination', $depotDestination, PDO::PARAM_INT);
        $stmt->bindParam(':observation', $observation);
        $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
        $stmt->execute();
        
        $idTransfert = $db->lastInsertId();
        
        // Insérer les détails du transfert
        for ($i = 0; $i < count($produits); $i++) {
            $idProduit = intval($produits[$i]);
            $idLot = intval($lots[$i]);
            $quantite = floatval($quantites[$i]);
            
            // Vérifier que le produit, le lot et la quantité sont valides
            if ($idProduit <= 0 || $idLot <= 0 || $quantite <= 0) {
                throw new Exception("Produit, lot ou quantité invalide à la ligne " . ($i + 1));
            }
            
            // Vérifier si le lot existe et appartient au dépôt source
            $stmt = $db->prepare("SELECT lp.*, de.id_entree 
                                 FROM lot_produit lp
                                 LEFT JOIN detail_entree_stock de ON lp.id_detail_entree = de.id_detail_entree
                                 LEFT JOIN entree_stock e ON de.id_entree = e.id_entree
                                 WHERE lp.id_lot = :id_lot 
                                 AND lp.id_produit = :id_produit 
                                 AND e.id_depot = :id_depot");
            $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
            $stmt->bindParam(':id_depot', $depotSource, PDO::PARAM_INT);
            $stmt->execute();
            $lot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lot) {
                throw new Exception("Le lot sélectionné n'existe pas ou n'appartient pas au dépôt source.");
            }
            
            // Vérifier si la quantité disponible est suffisante
            if ($lot['quantite_disponible'] < $quantite) {
                throw new Exception("Quantité insuffisante pour le produit à la ligne " . ($i + 1) . ". Disponible: " . $lot['quantite_disponible']);
            }
            
            // Insérer le détail du transfert
            $stmt = $db->prepare("INSERT INTO detail_transfert_stock 
                                 (id_transfert, id_produit, id_lot, quantite, id_user_creation) 
                                 VALUES 
                                 (:id_transfert, :id_produit, :id_lot, :quantite, :id_user_creation)");
            $stmt->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $idProduit, PDO::PARAM_INT);
            $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
            $stmt->bindParam(':quantite', $quantite);
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le transfert de stock a été créé avec succès.',
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
    // Redirection si accès direct au fichier
    header('Location: ../stock/transfert.add');
    exit();
}
