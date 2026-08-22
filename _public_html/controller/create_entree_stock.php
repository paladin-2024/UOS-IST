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
        $numero_entree = isset($_POST['numero_entree']) ? trim($_POST['numero_entree']) : '';
        $date_entree = isset($_POST['date_entree']) ? trim($_POST['date_entree']) : '';
        $id_depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        $type_entree = isset($_POST['type_entree']) ? trim($_POST['type_entree']) : '';
        $reference_document = isset($_POST['reference_document']) ? trim($_POST['reference_document']) : null;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $products = isset($_POST['products']) ? $_POST['products'] : [];
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if (empty($numero_entree) || empty($date_entree) || $id_depot <= 0 || empty($type_entree) || empty($products)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier si des produits ont été ajoutés
        if (count($products) == 0) {
            throw new Exception("Veuillez ajouter au moins un produit à l'entrée de stock.");
        }
        
        // Vérifier si le numéro d'entrée existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM entree_stock WHERE numero_entree = :numero_entree");
        $stmt->bindParam(':numero_entree', $numero_entree, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce numéro d'entrée existe déjà. Veuillez en générer un nouveau.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Insertion de l'entête d'entrée de stock
        $stmt = $db->prepare("INSERT INTO entree_stock 
            (numero_entree, date_entree, id_depot, type_entree, reference_document, 
             observation, etat, id_user_creation) 
            VALUES 
            (:numero_entree, :date_entree, :id_depot, :type_entree, :reference_document, 
             :observation, 'En cours', :id_user_creation)");
        
        $stmt->bindParam(':numero_entree', $numero_entree, PDO::PARAM_STR);
        $stmt->bindParam(':date_entree', $date_entree, PDO::PARAM_STR);
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmt->bindParam(':type_entree', $type_entree, PDO::PARAM_STR);
        $stmt->bindParam(':reference_document', $reference_document, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
        
        $stmt->execute();
        $id_entree = $db->lastInsertId();
        
        // Insertion des détails de l'entrée de stock
        foreach ($products as $product) {
            $id_produit = intval($product['id_produit']);
            $quantite = floatval($product['quantite']);
            $prix_unitaire = floatval($product['prix_unitaire']);
            $montant_total = floatval($product['montant_total']);
            $numero_lot = trim($product['numero_lot']);
            $date_peremption = !empty($product['date_peremption']) ? trim($product['date_peremption']) : null;
            
            // Validation du produit
            if ($id_produit <= 0 || $quantite <= 0 || $prix_unitaire <= 0 || empty($numero_lot)) {
                throw new Exception("Données de produit invalides.");
            }
            
            // Insérer le détail d'entrée
            $stmt = $db->prepare("INSERT INTO detail_entree_stock 
                (id_entree, id_produit, quantite, prix_unitaire, montant_total, id_user_creation) 
                VALUES 
                (:id_entree, :id_produit, :quantite, :prix_unitaire, :montant_total, :id_user_creation)");
            
            $stmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
            $stmt->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            
            $stmt->execute();
            $id_detail_entree = $db->lastInsertId();
            
            // Créer le lot de produit
            $stmt = $db->prepare("INSERT INTO lot_produit 
                (numero_lot, id_produit, id_detail_entree, quantite_initiale, 
                 quantite_disponible, prix_unitaire_achat, prix_unitaire_vente, 
                 date_peremption) 
                VALUES 
                (:numero_lot, :id_produit, :id_detail_entree, :quantite_initiale, 
                 :quantite_disponible, :prix_unitaire_achat, :prix_unitaire_vente, 
                 :date_peremption)");
            
            // Calculer le prix de vente en fonction de la marge
            $stmtProduit = $db->prepare("SELECT marge_beneficiaire FROM produit WHERE id_produit = :id_produit");
            $stmtProduit->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $stmtProduit->execute();
            $produit = $stmtProduit->fetch(PDO::FETCH_ASSOC);
            
            $marge = floatval($produit['marge_beneficiaire'] ?? 0);
            $prix_vente = $prix_unitaire * (1 + ($marge / 100));
            
            $stmt->bindParam(':numero_lot', $numero_lot, PDO::PARAM_STR);
            $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $stmt->bindParam(':id_detail_entree', $id_detail_entree, PDO::PARAM_INT);
            $stmt->bindParam(':quantite_initiale', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':quantite_disponible', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':prix_unitaire_achat', $prix_unitaire, PDO::PARAM_STR);
            $stmt->bindParam(':prix_unitaire_vente', $prix_vente, PDO::PARAM_STR);
            $stmt->bindParam(':date_peremption', $date_peremption, PDO::PARAM_STR);
            
            $stmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'entree_stock', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Création d'une entrée de stock: $numero_entree (Type: $type_entree)";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_entree, PDO::PARAM_INT);
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
                text: 'L\'entrée de stock a été créée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../stock/stock.entree.view&id=" . $id_entree . "';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../stock/stock.entree.add';
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
            window.location.href = '../stock/stock.entree.list';
        });
    </script>";
    exit;
}

