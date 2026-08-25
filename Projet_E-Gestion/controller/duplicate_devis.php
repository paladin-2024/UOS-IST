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

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $id_devis_source = intval($_GET['id']);
    $id_user = $_SESSION['id'];
    
    try {
        // Démarrer une transaction
        $db->beginTransaction();
        
        // 1. Récupérer les informations du devis source
        $query = "SELECT * FROM devis WHERE id_devis = :id_devis";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_devis', $id_devis_source, PDO::PARAM_INT);
        $stmt->execute();
        $devis_source = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$devis_source) {
            throw new Exception("Le devis source n'existe pas.");
        }
        
        // 2. Générer un nouveau numéro de devis
        $year = date('y');
        $query = "SELECT MAX(CAST(SUBSTRING(numero_devis, 6) AS INTEGER)) as max_num
                  FROM devis
                  WHERE numero_devis LIKE 'DEV" . $year . "%'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $nouveau_numero = 'DEV' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
        // 3. Créer le nouveau devis
        $query = "INSERT INTO devis 
                  (numero_devis, date_devis, id_client, montant_ht, taux_tva, montant_tva, 
                   montant_ttc, validite, observation, etat, id_user_creation, date_creation) 
                  VALUES
                  (:numero_devis, CURRENT_DATE, :id_client, :montant_ht, :taux_tva, :montant_tva,
                   :montant_ttc, :validite, :observation, 'En cours', :id_user_creation, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':numero_devis', $nouveau_numero, PDO::PARAM_STR);
        $stmt->bindParam(':id_client', $devis_source['id_client'], PDO::PARAM_INT);
        $stmt->bindParam(':montant_ht', $devis_source['montant_ht'], PDO::PARAM_STR);
        $stmt->bindParam(':taux_tva', $devis_source['taux_tva'], PDO::PARAM_STR);
        $stmt->bindParam(':montant_tva', $devis_source['montant_tva'], PDO::PARAM_STR);
        $stmt->bindParam(':montant_ttc', $devis_source['montant_ttc'], PDO::PARAM_STR);
        $stmt->bindParam(':validite', $devis_source['validite'], PDO::PARAM_INT);
        
        // Ajouter une note indiquant qu'il s'agit d'une copie
        $observation = "Copie du devis " . $devis_source['numero_devis'] . " du " . 
                       date('d/m/Y', strtotime($devis_source['date_devis'])) . ".\n";
        if (!empty($devis_source['observation'])) {
            $observation .= $devis_source['observation'];
        }
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
        
        $stmt->execute();
        $id_nouveau_devis = $db->lastInsertId();
        
        // 4. Copier les lignes du devis source vers le nouveau devis
        $query = "SELECT * FROM ligne_devis WHERE id_devis = :id_devis";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_devis', $id_devis_source, PDO::PARAM_INT);
        $stmt->execute();
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($lignes as $ligne) {
            $query = "INSERT INTO ligne_devis 
                      (id_devis, id_produit, designation, quantite, prix_unitaire, remise, 
                       montant_remise, montant_ht, taux_tva, montant_tva, montant_ttc, id_user_creation) 
                      VALUES 
                      (:id_devis, :id_produit, :designation, :quantite, :prix_unitaire, :remise, 
                       :montant_remise, :montant_ht, :taux_tva, :montant_tva, :montant_ttc, :id_user_creation)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_devis', $id_nouveau_devis, PDO::PARAM_INT);
            $stmt->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
            $stmt->bindParam(':designation', $ligne['designation'], PDO::PARAM_STR);
            $stmt->bindParam(':quantite', $ligne['quantite'], PDO::PARAM_STR);
            $stmt->bindParam(':prix_unitaire', $ligne['prix_unitaire'], PDO::PARAM_STR);
            $stmt->bindParam(':remise', $ligne['remise'], PDO::PARAM_STR);
            $stmt->bindParam(':montant_remise', $ligne['montant_remise'], PDO::PARAM_STR);
            $stmt->bindParam(':montant_ht', $ligne['montant_ht'], PDO::PARAM_STR);
            $stmt->bindParam(':taux_tva', $ligne['taux_tva'], PDO::PARAM_STR);
            $stmt->bindParam(':montant_tva', $ligne['montant_tva'], PDO::PARAM_STR);
            $stmt->bindParam(':montant_ttc', $ligne['montant_ttc'], PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            
            $stmt->execute();
        }
        
        // 5. Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'duplication', 'devis', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        // Récupérer les informations du client pour le log
        $query = "SELECT c.nom_client 
                  FROM devis d 
                  JOIN client c ON d.id_client = c.id_client 
                  WHERE d.id_devis = :id_devis";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_devis', $id_nouveau_devis, PDO::PARAM_INT);
        $stmt->execute();
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $description = "Duplication du devis " . $devis_source['numero_devis'] . " vers " . $nouveau_numero . 
                       " pour le client: " . $info['nom_client'];
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_nouveau_devis, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger vers le nouveau devis avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le devis a été dupliqué avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../ventes/devis/devis.view&id=" . $id_nouveau_devis . "';
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
                window.location.href = '../ventes/devis/devis.view&id=" . $id_devis_source . "';
            });
        </script>";
        exit;
    }
} else {
    // Redirection si l'ID n'est pas fourni
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'ID du devis non spécifié',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../ventes/devis/devis.list';
        });
    </script>";
    exit;
}
