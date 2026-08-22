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
        
        // Validation des données
        if ($depot <= 0) {
            throw new Exception("Veuillez sélectionner un dépôt valide.");
        }
        
        if (empty($produits) || empty($lots)) {
            throw new Exception("Veuillez ajouter au moins un produit à l'inventaire.");
        }
        
        // Commencer une transaction
        $db->beginTransaction();
        
        // Générer le numéro d'inventaire (format: INV-YYYYMMDD-XXX)
        $date = date('Ymd');
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_inventaire, 14) AS UNSIGNED)) as last_num 
                             FROM inventaire 
                             WHERE numero_inventaire LIKE :prefix");
        $prefix = "INV-{$date}-%";
        $stmt->bindParam(':prefix', $prefix);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastNum = $result['last_num'] ?? 0;
        $newNum = $lastNum + 1;
        $numeroInventaire = "INV-{$date}-" . str_pad($newNum, 3, '0', STR_PAD_LEFT);
        
        // Insérer l'en-tête de l'inventaire
        $stmt = $db->prepare("INSERT INTO inventaire 
                             (numero_inventaire, date_inventaire, id_depot, 
                              observation, etat, id_user_creation, date_creation) 
                             VALUES 
                             (:numero_inventaire, :date_inventaire, :id_depot, 
                              :observation, 'En cours', :id_user_creation, NOW())");
        $stmt->bindParam(':numero_inventaire', $numeroInventaire);
        $stmt->bindParam(':date_inventaire', $dateInventaire);
        $stmt->bindParam(':id_depot', $depot, PDO::PARAM_INT);
        $stmt->bindParam(':observation', $observation);
        $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
        $stmt->execute();
        
        $idInventaire = $db->lastInsertId();
        
        // Insérer les détails de l'inventaire
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
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'L\'inventaire a été créé avec succès.',
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
    header('Location: ../stock/inventaire.add');
    exit();
}

