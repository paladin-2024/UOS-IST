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
        $numero_demande = isset($_POST['numero_demande']) ? trim($_POST['numero_demande']) : '';
        $date_demande = isset($_POST['date_demande']) ? trim($_POST['date_demande']) : '';
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if (empty($numero_demande) || empty($date_demande) || $id_fournisseur <= 0) {
            throw new Exception("Les champs Numéro, Date et Fournisseur sont obligatoires.");
        }
        
        // Vérifier si le numéro existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM demande_prix WHERE numero_demande = :numero_demande");
        $stmt->bindParam(':numero_demande', $numero_demande, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce numéro de demande existe déjà. Veuillez en générer un nouveau.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Insertion dans la table demande_prix
        $stmt = $db->prepare("INSERT INTO demande_prix 
            (numero_demande, date_demande, id_fournisseur, observation, etat, id_user_creation) 
            VALUES 
            (:numero_demande, :date_demande, :id_fournisseur, :observation, 'En cours', :id_user_creation)");
        
        $stmt->bindParam(':numero_demande', $numero_demande, PDO::PARAM_STR);
        $stmt->bindParam(':date_demande', $date_demande, PDO::PARAM_STR);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        
        $stmt->execute();
        $idDemande = $db->lastInsertId();
        
        // Vérifier si des produits ont été soumis
        if (!isset($_POST['products']) || !is_array($_POST['products']) || count($_POST['products']) == 0) {
            throw new Exception("Aucun produit n'a été ajouté à la demande.");
        }
        
        // Insertion des lignes de demande
        foreach ($_POST['products'] as $product) {
            $id_produit = intval($product['id_produit']);
            $quantite = floatval($product['quantite']);
            $designation = trim($product['designation']);
            
            if ($id_produit <= 0 || $quantite <= 0 || empty($designation)) {
                throw new Exception("Données de produit invalides.");
            }
            
            $stmt = $db->prepare("INSERT INTO ligne_demande_prix 
                (id_demande_prix, id_produit, designation, quantite, id_user_creation) 
                VALUES 
                (:id_demande_prix, :id_produit, :designation, :quantite, :id_user_creation)");
            
            $stmt->bindParam(':id_demande_prix', $idDemande, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
            $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            
            $stmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'demande_prix', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Création de la demande de prix: $numero_demande";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $idDemande, PDO::PARAM_INT);
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
                text: 'La demande de prix a été créée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../achats/demandes/demandes.view&id=" . $idDemande . "';
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
                window.location.href = '../achats/demandes/demandes.add';
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
            window.location.href = '../achats/demandes/demandes.list';
        });
    </script>";
    exit;
}
