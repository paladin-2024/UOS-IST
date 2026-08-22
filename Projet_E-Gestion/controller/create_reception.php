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
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Récupérer les données du formulaire
        $numero_reception = isset($_POST['numero_reception']) ? trim($_POST['numero_reception']) : '';
        $date_reception = isset($_POST['date_reception']) ? trim($_POST['date_reception']) : '';
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $id_depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        $id_commande = isset($_POST['id_commande']) ? intval($_POST['id_commande']) : null;
        $reference_bl = isset($_POST['reference_bl']) ? trim($_POST['reference_bl']) : null;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if (empty($numero_reception) || empty($date_reception) || $id_fournisseur <= 0 || $id_depot <= 0) {
            throw new Exception("Les champs Numéro, Date, Fournisseur et Dépôt sont obligatoires.");
        }
        
        // Vérifier si le numéro existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM reception_fournisseur WHERE numero_reception = :numero_reception");
        $stmt->bindParam(':numero_reception', $numero_reception, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce numéro de réception existe déjà. Veuillez en choisir un autre.");
        }
        
        // Calculer le montant total
        $montant_total = 0;
        
        // Si basé sur une commande, récupérer les produits de la commande
        if ($id_commande) {
            if (isset($_POST['produits_commande'])) {
                foreach ($_POST['produits_commande'] as $produit) {
                    $quantite = floatval($produit['quantite']);
                    $prix_unitaire = floatval($produit['prix_unitaire']);
                    $montant_total += $quantite * $prix_unitaire;
                }
            }
        } else {
            // Sinon, calculer à partir des produits saisis directement
            if (isset($_POST['produits'])) {
                foreach ($_POST['produits'] as $produit) {
                    if (empty($produit['id_produit'])) continue;
                    $quantite = floatval($produit['quantite']);
                    $prix_unitaire = floatval($produit['prix_unitaire']);
                    $montant_total += $quantite * $prix_unitaire;
                }
            }
        }
        
        // Insertion dans la base de données
        $stmt = $db->prepare("INSERT INTO reception_fournisseur 
            (numero_reception, date_reception, id_fournisseur, id_depot, id_commande, 
            reference_bl, observation, montant_total, etat, id_user_creation) 
            VALUES 
            (:numero_reception, :date_reception, :id_fournisseur, :id_depot, :id_commande, 
            :reference_bl, :observation, :montant_total, 'En cours', :id_user_creation)");
        
        $stmt->bindParam(':numero_reception', $numero_reception, PDO::PARAM_STR);
        $stmt->bindParam(':date_reception', $date_reception, PDO::PARAM_STR);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmt->bindParam(':id_commande', $id_commande, $id_commande ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':reference_bl', $reference_bl, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        
        $stmt->execute();
        $id_reception = $db->lastInsertId();
        
        // Traiter les produits de la réception directe (sans commande)
        if (!$id_commande && isset($_POST['produits'])) {
            foreach ($_POST['produits'] as $produit) {
                if (empty($produit['id_produit'])) {
                    continue; // Ignorer les lignes vides
                }
                
                $id_produit = intval($produit['id_produit']);
                $designation = isset($produit['designation']) ? $produit['designation'] : '';
                
                // Si la désignation est vide, récupérer le libellé du produit
                if (empty($designation)) {
                    $queryProduit = "SELECT libelle_produit FROM produit WHERE id_produit = :id_produit";
                    $stmtProduit = $db->prepare($queryProduit);
                    $stmtProduit->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                    $stmtProduit->execute();
                    $produitData = $stmtProduit->fetch(PDO::FETCH_ASSOC);
                    $designation = $produitData['libelle_produit'];
                }
                
                $quantite = floatval($produit['quantite']);
                $prix_unitaire = floatval($produit['prix_unitaire']);
                $montant_total = $quantite * $prix_unitaire;
                $numero_lot = $produit['numero_lot'];
                $date_peremption = !empty($produit['date_peremption']) ? $produit['date_peremption'] : null;
                
                // Insérer la ligne de réception
                $queryLigne = "INSERT INTO ligne_reception_fournisseur (
                    id_reception, id_produit, designation, quantite, 
                    prix_unitaire, montant_total, numero_lot, date_peremption, id_user_creation
                ) VALUES (
                    :id_reception, :id_produit, :designation, :quantite, 
                    :prix_unitaire, :montant_total, :numero_lot, :date_peremption, :id_user_creation
                )";
                
                $stmtLigne = $db->prepare($queryLigne);
                $stmtLigne->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
                $stmtLigne->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmtLigne->bindParam(':designation', $designation, PDO::PARAM_STR);
                $stmtLigne->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmtLigne->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmtLigne->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
                $stmtLigne->bindParam(':numero_lot', $numero_lot, PDO::PARAM_STR);
                $stmtLigne->bindParam(':date_peremption', $date_peremption, $date_peremption === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtLigne->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                $stmtLigne->execute();
            }
        }
        
        // Traiter les produits de la commande (s'il y en a)
        if ($id_commande && isset($_POST['produits_commande'])) {
            foreach ($_POST['produits_commande'] as $produit) {
                $id_produit = intval($produit['id_produit']);
                $designation = $produit['designation'];
                $quantite = floatval($produit['quantite']);
                $prix_unitaire = floatval($produit['prix_unitaire']);
                $montant_total = $quantite * $prix_unitaire;
                $numero_lot = $produit['numero_lot'];
                $date_peremption = !empty($produit['date_peremption']) ? $produit['date_peremption'] : null;
                
                // Insérer la ligne de réception
                $queryLigne = "INSERT INTO ligne_reception_fournisseur (
                    id_reception, id_produit, designation, quantite, 
                    prix_unitaire, montant_total, numero_lot, date_peremption, id_user_creation
                ) VALUES (
                    :id_reception, :id_produit, :designation, :quantite, 
                    :prix_unitaire, :montant_total, :numero_lot, :date_peremption, :id_user_creation
                )";
                
                $stmtLigne = $db->prepare($queryLigne);
                $stmtLigne->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
                $stmtLigne->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmtLigne->bindParam(':designation', $designation, PDO::PARAM_STR);
                $stmtLigne->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmtLigne->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmtLigne->bindParam(':montant_total', $montant_total, PDO::PARAM_STR);
                $stmtLigne->bindParam(':numero_lot', $numero_lot, PDO::PARAM_STR);
                $stmtLigne->bindParam(':date_peremption', $date_peremption, $date_peremption === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtLigne->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                $stmtLigne->execute();
            }
            
            // Mettre à jour l'état de la commande si tous les produits ont été réceptionnés
            $queryCheckReception = "SELECT 
                SUM(lcf.quantite) as quantite_totale_commande,
                COALESCE(SUM(lrf.quantite), 0) as quantite_totale_recue
                FROM ligne_commande_fournisseur lcf
                LEFT JOIN ligne_reception_fournisseur lrf ON lcf.id_produit = lrf.id_produit
                LEFT JOIN reception_fournisseur rf ON lrf.id_reception = rf.id_reception 
                    AND rf.id_commande = lcf.id_commande AND rf.etat = 'Validé'
                WHERE lcf.id_commande = :id_commande";
            
            $stmtCheckReception = $db->prepare($queryCheckReception);
            $stmtCheckReception->bindParam(':id_commande', $id_commande, PDO::PARAM_INT);
            $stmtCheckReception->execute();
            $receptionData = $stmtCheckReception->fetch(PDO::FETCH_ASSOC);
            
            if ($receptionData['quantite_totale_recue'] > 0) {
                $nouvelEtat = ($receptionData['quantite_totale_recue'] >= $receptionData['quantite_totale_commande']) 
                    ? 'Réceptionné' 
                    : 'Réceptionné partiellement';
                
                $queryUpdateCommande = "UPDATE commande_fournisseur SET etat = :etat WHERE id_commande = :id_commande";
                $stmtUpdateCommande = $db->prepare($queryUpdateCommande);
                $stmtUpdateCommande->bindParam(':etat', $nouvelEtat, PDO::PARAM_STR);
                $stmtUpdateCommande->bindParam(':id_commande', $id_commande, PDO::PARAM_INT);
                $stmtUpdateCommande->execute();
            }
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'reception_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Création de la réception N° $numero_reception";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_reception, PDO::PARAM_INT);
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
                text: 'La réception a été créée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = '../achats/receptions/receptions.view&id=$id_reception';
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
            }).then(() => {
                window.history.back();
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
        }).then(() => {
            window.location.href = '../achats/receptions/receptions.list';
        });
    </script>";
    exit;
}

