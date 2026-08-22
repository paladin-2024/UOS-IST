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

// Récupérer les paramètres
$id_reception = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id_user = $_SESSION['id'];

if ($id_reception <= 0 || !in_array($action, ['validate', 'cancel'])) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Paramètres invalides',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

try {
    // Démarrer une transaction
    $db->beginTransaction();
    
    // Vérifier si la réception existe et est en état "En cours"
    $queryReception = "SELECT r.*, f.nom_fournisseur, d.libelle_depot 
                      FROM reception_fournisseur r 
                      JOIN fournisseur f ON r.id_fournisseur = f.id_fournisseur 
                      JOIN depot d ON r.id_depot = d.id_depot 
                      WHERE r.id_reception = :id_reception";
    $stmtReception = $db->prepare($queryReception);
    $stmtReception->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
    $stmtReception->execute();
    $reception = $stmtReception->fetch(PDO::FETCH_ASSOC);
    
    if (!$reception) {
        throw new Exception("Réception non trouvée.");
    }
    
    if ($reception['etat'] !== 'En cours') {
        throw new Exception("Cette réception ne peut pas être modifiée car elle n'est pas en état 'En cours'.");
    }
    
    if ($action === 'validate') {
        // Récupérer les lignes de la réception
        $queryLignes = "SELECT * FROM ligne_reception_fournisseur WHERE id_reception = :id_reception";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
        $stmtLignes->execute();
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($lignes)) {
            throw new Exception("Impossible de valider une réception sans produits.");
        }
        
        // Générer un numéro d'entrée automatique
        $year = date('y'); // Année courante en 2 chiffres
        $query = "SELECT MAX(CAST(SUBSTRING(numero_entree, 6) AS UNSIGNED)) as max_num 
                  FROM entree_stock 
                  WHERE numero_entree LIKE 'ENT" . $year . "%' 
                  AND YEAR(date_entree) = YEAR(CURRENT_DATE())";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $numero_entree = 'ENT' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
        // Créer l'entrée de stock
        $queryEntree = "INSERT INTO entree_stock (
            numero_entree, date_entree, id_depot, type_entree, reference_document, 
            observation, etat, id_user_creation, id_user_validation, date_validation
        ) VALUES (
            :numero_entree, :date_entree, :id_depot, 'Achat', :reference_document, 
            :observation, 'Validé', :id_user_creation, :id_user_validation, NOW()
        )";
        
        $stmtEntree = $db->prepare($queryEntree);
        $stmtEntree->bindParam(':numero_entree', $numero_entree, PDO::PARAM_STR);
        $stmtEntree->bindParam(':date_entree', $reception['date_reception'], PDO::PARAM_STR);
        $stmtEntree->bindParam(':id_depot', $reception['id_depot'], PDO::PARAM_INT);
        $stmtEntree->bindParam(':reference_document', $reception['numero_reception'], PDO::PARAM_STR);
        $stmtEntree->bindParam(':observation', $reception['observation'], PDO::PARAM_STR);
        $stmtEntree->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
        $stmtEntree->bindParam(':id_user_validation', $id_user, PDO::PARAM_INT);
        $stmtEntree->execute();
        
        $id_entree = $db->lastInsertId();
        
        // Créer les détails de l'entrée de stock et les lots
        foreach ($lignes as $ligne) {
            // Insérer le détail d'entrée
            $queryDetail = "INSERT INTO detail_entree_stock (
                id_entree, id_produit, quantite, prix_unitaire, montant_total, id_user_creation
            ) VALUES (
                :id_entree, :id_produit, :quantite, :prix_unitaire, :montant_total, :id_user_creation
            )";
            
            $stmtDetail = $db->prepare($queryDetail);
            $stmtDetail->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
            $stmtDetail->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
            $stmtDetail->bindParam(':quantite', $ligne['quantite'], PDO::PARAM_STR);
            $stmtDetail->bindParam(':prix_unitaire', $ligne['prix_unitaire'], PDO::PARAM_STR);
            $stmtDetail->bindParam(':montant_total', $ligne['montant_total'], PDO::PARAM_STR);
            $stmtDetail->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            $stmtDetail->execute();
            
            $id_detail_entree = $db->lastInsertId();
            
            // Créer le lot
            $queryLot = "INSERT INTO lot_produit (
                numero_lot, id_produit, id_detail_entree, quantite_initiale, quantite_disponible,
                prix_unitaire_achat, prix_unitaire_vente, date_peremption, date_creation
            ) VALUES (
                :numero_lot, :id_produit, :id_detail_entree, :quantite_initiale, :quantite_disponible,
                :prix_unitaire_achat, :prix_unitaire_vente, :date_peremption, NOW()
            )";
            
            // Calculer le prix de vente en fonction de la marge du produit
            $queryMarge = "SELECT marge_beneficiaire FROM produit WHERE id_produit = :id_produit";
            $stmtMarge = $db->prepare($queryMarge);
            $stmtMarge->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
            $stmtMarge->execute();
            $marge = $stmtMarge->fetchColumn();
            
            $prix_vente = $ligne['prix_unitaire'] * (1 + ($marge / 100));
            
            $stmtLot = $db->prepare($queryLot);
            $stmtLot->bindParam(':numero_lot', $ligne['numero_lot'], PDO::PARAM_STR);
            $stmtLot->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
            $stmtLot->bindParam(':id_detail_entree', $id_detail_entree, PDO::PARAM_INT);
            $stmtLot->bindParam(':quantite_initiale', $ligne['quantite'], PDO::PARAM_STR);
            $stmtLot->bindParam(':quantite_disponible', $ligne['quantite'], PDO::PARAM_STR);
            $stmtLot->bindParam(':prix_unitaire_achat', $ligne['prix_unitaire'], PDO::PARAM_STR);
            $stmtLot->bindParam(':prix_unitaire_vente', $prix_vente, PDO::PARAM_STR);
            $stmtLot->bindParam(':date_peremption', $ligne['date_peremption'], $ligne['date_peremption'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtLot->execute();
        }
        
        // Mettre à jour la réception avec l'ID de l'entrée de stock et l'état
        $queryUpdateReception = "UPDATE reception_fournisseur SET 
            etat = 'Validé', 
            id_entree_stock = :id_entree_stock,
            id_user_validation = :id_user_validation,
            date_validation = NOW()
            WHERE id_reception = :id_reception";
        
        $stmtUpdateReception = $db->prepare($queryUpdateReception);
        $stmtUpdateReception->bindParam(':id_entree_stock', $id_entree, PDO::PARAM_INT);
        $stmtUpdateReception->bindParam(':id_user_validation', $id_user, PDO::PARAM_INT);
        $stmtUpdateReception->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
        $stmtUpdateReception->execute();
        
        // Si la réception est liée à une commande, mettre à jour l'état de la commande
        if ($reception['id_commande']) {
            // Vérifier si toutes les quantités de la commande ont été reçues
            $queryCheckReception = "SELECT 
                lcf.id_produit,
                lcf.quantite as quantite_commandee,
                COALESCE(SUM(lrf.quantite), 0) as quantite_recue
                FROM ligne_commande_fournisseur lcf
                LEFT JOIN ligne_reception_fournisseur lrf ON lcf.id_produit = lrf.id_produit
                LEFT JOIN reception_fournisseur rf ON lrf.id_reception = rf.id_reception AND rf.etat = 'Validé'
                WHERE lcf.id_commande = :id_commande
                AND rf.id_commande = lcf.id_commande
                GROUP BY lcf.id_produit, lcf.quantite";
            
            $stmtCheckReception = $db->prepare($queryCheckReception);
            $stmtCheckReception->bindParam(':id_commande', $reception['id_commande'], PDO::PARAM_INT);
            $stmtCheckReception->execute();
            $receptionStatus = $stmtCheckReception->fetchAll(PDO::FETCH_ASSOC);
            
            $allReceived = true;
            foreach ($receptionStatus as $status) {
                if ($status['quantite_recue'] < $status['quantite_commandee']) {
                    $allReceived = false;
                    break;
                }
            }
            
            $nouvelEtat = $allReceived ? 'Réceptionné' : 'Réceptionné partiellement';
            
            $queryUpdateCommande = "UPDATE commande_fournisseur SET 
                etat = :etat
                WHERE id_commande = :id_commande";
            
            $stmtUpdateCommande = $db->prepare($queryUpdateCommande);
            $stmtUpdateCommande->bindParam(':etat', $nouvelEtat, PDO::PARAM_STR);
            $stmtUpdateCommande->bindParam(':id_commande', $reception['id_commande'], PDO::PARAM_INT);
            $stmtUpdateCommande->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'validation', 'reception_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Validation de la réception N° {$reception['numero_reception']} pour le fournisseur {$reception['nom_fournisseur']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_reception, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La réception a été validée avec succès et l\'entrée en stock a été créée.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../achats/receptions/receptions.view&id=$id_reception';
            });
        </script>";
        exit;
        
    } else if ($action === 'cancel') {
        // Annuler la réception
        $queryUpdateReception = "UPDATE reception_fournisseur SET 
            etat = 'Annulé', 
            id_user_validation = :id_user_validation,
            date_validation = NOW()
            WHERE id_reception = :id_reception";
        
        $stmtUpdateReception = $db->prepare($queryUpdateReception);
        $stmtUpdateReception->bindParam(':id_user_validation', $id_user, PDO::PARAM_INT);
        $stmtUpdateReception->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
        $stmtUpdateReception->execute();
        
                // Journalisation de l'action
                $logStmt = $db->prepare("INSERT INTO log_operation 
                (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
                VALUES 
                (:id_user, 'annulation', 'reception_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
            
            $description = "Annulation de la réception N° {$reception['numero_reception']} pour le fournisseur {$reception['nom_fournisseur']}";
            $adresse_ip = $_SERVER['REMOTE_ADDR'];
            $navigateur = $_SERVER['HTTP_USER_AGENT'];
            
            $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
            $logStmt->bindParam(':id_enregistrement', $id_reception, PDO::PARAM_INT);
            $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
            $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
            $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
            $logStmt->execute();
            
            // Valider la transaction
            $db->commit();
            
            // Message de succès
            echo "<script>
                Swal.fire({
                    title: 'Succès',
                    text: 'La réception a été annulée avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    window.location.href = '../achats/receptions/receptions.view&id=$id_reception';
                });
            </script>";
            exit;
        }
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        // Message d'erreur
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
    ?>
    
    