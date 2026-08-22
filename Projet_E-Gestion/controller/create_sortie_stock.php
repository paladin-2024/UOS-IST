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
        $numero_sortie = isset($_POST['numero_sortie']) ? trim($_POST['numero_sortie']) : '';
        $date_sortie = isset($_POST['date_sortie']) ? trim($_POST['date_sortie']) : '';
        $id_depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        $type_sortie = isset($_POST['type_sortie']) ? trim($_POST['type_sortie']) : '';
        $reference_document = isset($_POST['reference_document']) ? trim($_POST['reference_document']) : null;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $products = isset($_POST['products']) ? $_POST['products'] : [];
        $id_user = $_SESSION['id'];
        
        // Validation des données principales
        if (empty($numero_sortie) || empty($date_sortie) || $id_depot <= 0 || empty($type_sortie)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Validation des produits
        if (empty($products)) {
            throw new Exception("Veuillez ajouter au moins un produit.");
        }
        
        // Calculer le montant total de la sortie
        $montant_total = 0;
        foreach ($products as $product) {
            $quantite = isset($product['quantite']) ? floatval($product['quantite']) : 0;
            $prix_unitaire = isset($product['prix_unitaire']) ? floatval($product['prix_unitaire']) : 0;
            $montant_total += $quantite * $prix_unitaire;
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Insérer l'entête de la sortie avec le montant total
        $stmt = $db->prepare("INSERT INTO sortie_stock 
            (numero_sortie, date_sortie, id_depot, type_sortie, reference_document, 
             observation, montant_total, etat, id_user_creation) 
            VALUES 
            (:numero_sortie, :date_sortie, :id_depot, :type_sortie, :reference_document, 
             :observation, :montant_total, 'En cours', :id_user_creation)");
        
        $stmt->bindParam(':numero_sortie', $numero_sortie, PDO::PARAM_STR);
        $stmt->bindParam(':date_sortie', $date_sortie, PDO::PARAM_STR);
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmt->bindParam(':type_sortie', $type_sortie, PDO::PARAM_STR);
        $stmt->bindParam(':reference_document', $reference_document, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
        
        $stmt->execute();
        $id_sortie = $db->lastInsertId();
        
        // Traiter chaque produit
        foreach ($products as $product) {
            $id_produit = isset($product['id_produit']) ? intval($product['id_produit']) : 0;
            $id_lot = isset($product['id_lot']) ? intval($product['id_lot']) : 0;
            $quantite = isset($product['quantite']) ? floatval($product['quantite']) : 0;
            $prix_unitaire = isset($product['prix_unitaire']) ? floatval($product['prix_unitaire']) : 0;
            $montant_total = $quantite * $prix_unitaire;
            
            // Vérifier si le lot existe et a suffisamment de stock
            $stmt = $db->prepare("SELECT l.*, p.libelle_produit 
                                  FROM lot_produit l 
                                  JOIN produit p ON l.id_produit = p.id_produit
                                  WHERE l.id_lot = :id_lot AND l.id_produit = :id_produit");
            $stmt->bindParam(':id_lot', $id_lot, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $stmt->execute();
            $lot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lot) {
                throw new Exception("Le lot sélectionné n'existe pas pour ce produit.");
            }
            
            if ($lot['quantite_disponible'] < $quantite) {
                throw new Exception("Stock insuffisant pour le produit '{$lot['libelle_produit']}', lot '{$lot['numero_lot']}'. Stock disponible: {$lot['quantite_disponible']}");
            }
            
            // Insérer le détail de la sortie
            $stmt = $db->prepare("INSERT INTO detail_sortie_stock 
                (id_sortie, id_produit, quantite, prix_unitaire, montant_total, id_user_creation) 
                VALUES 
                (:id_sortie, :id_produit, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
            
            $stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
            $stmt->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            
            $stmt->execute();
            $id_detail_sortie = $db->lastInsertId();
            
            // Insérer le détail de sortie par lot
            $stmt = $db->prepare("INSERT INTO detail_sortie_lot 
                (id_detail_sortie, id_lot, quantite, prix_unitaire, montant_total, id_user_creation) 
                VALUES 
                (:id_detail_sortie, :id_lot, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
            
            $stmt->bindParam(':id_detail_sortie', $id_detail_sortie, PDO::PARAM_INT);
            $stmt->bindParam(':id_lot', $id_lot, PDO::PARAM_INT);
            $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
            $stmt->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            
            $stmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'sortie_stock', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Création d'une sortie de stock: $numero_sortie (Type: $type_sortie)";
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
                text: 'La sortie de stock a été enregistrée avec succès.',
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
                window.location.href = '../stock/stock.sortie.add';
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
