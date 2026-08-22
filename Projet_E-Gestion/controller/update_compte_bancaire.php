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
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $nom_banque = isset($_POST['nom_banque']) ? trim($_POST['nom_banque']) : '';
        $numero_compte = isset($_POST['numero_compte']) ? trim($_POST['numero_compte']) : '';
        $intitule_compte = isset($_POST['intitule_compte']) ? trim($_POST['intitule_compte']) : '';
        $devise = isset($_POST['devise']) ? trim($_POST['devise']) : 'USD';
        $solde_initial = isset($_POST['solde_initial']) ? floatval($_POST['solde_initial']) : 0.00;
        $date_ouverture = isset($_POST['date_ouverture']) && !empty($_POST['date_ouverture']) 
            ? date('Y-m-d', strtotime($_POST['date_ouverture'])) 
            : null;
        
        $contact_banque = isset($_POST['contact_banque']) ? trim($_POST['contact_banque']) : null;
        $telephone_banque = isset($_POST['telephone_banque']) ? trim($_POST['telephone_banque']) : null;
        $email_banque = isset($_POST['email_banque']) ? trim($_POST['email_banque']) : null;
        $adresse_banque = isset($_POST['adresse_banque']) ? trim($_POST['adresse_banque']) : null;
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        
        // Validation des données obligatoires
        if (empty($nom_banque) || empty($numero_compte) || empty($intitule_compte)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Si c'est un nouveau compte ou si le numéro est modifié, vérifier l'unicité du numéro
        if (!$id || ($id && $numero_compte_original != $numero_compte)) {
            $stmt = $db->prepare("SELECT id FROM comptes_bancaires WHERE numero_compte = :numero_compte AND id != :id");
            $stmt->bindValue(':numero_compte', $numero_compte);
            $stmt->bindValue(':id', $id ?: 0, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("Ce numéro de compte existe déjà. Veuillez en choisir un autre.");
            }
        }
        
        $idUser = $_SESSION['id'];
        
        // Si c'est une mise à jour
        if ($id) {
            // Récupérer le solde actuel
            $stmt = $db->prepare("SELECT solde_actuel FROM comptes_bancaires WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si c'est une mise à jour qui modifie le solde initial
            if ($compte && $solde_initial != $compte['solde_actuel']) {
                // Calculer la différence à appliquer au solde actuel
                $diff = $solde_initial - $compte['solde_actuel'];
                $solde_actuel = $solde_initial;
            } else {
                $solde_actuel = $solde_initial;
            }
            
            // Mettre à jour le compte existant
            $stmt = $db->prepare("
                UPDATE comptes_bancaires SET 
                    nom_banque = :nom_banque,
                    numero_compte = :numero_compte,
                    intitule_compte = :intitule_compte,
                    devise = :devise,
                    solde_initial = :solde_initial,
                    solde_actuel = :solde_actuel,
                    date_ouverture = :date_ouverture,
                    contact_banque = :contact_banque,
                    telephone_banque = :telephone_banque,
                    email_banque = :email_banque,
                    adresse_banque = :adresse_banque,
                    est_actif = :est_actif
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':solde_actuel', $solde_actuel, PDO::PARAM_STR);
            $message = "Le compte bancaire a été mis à jour avec succès.";
        } else {
            // Pour un nouveau compte, le solde actuel est égal au solde initial
            $solde_actuel = $solde_initial;
            
            // Insérer le nouveau compte
            $stmt = $db->prepare("
                INSERT INTO comptes_bancaires 
                (nom_banque, numero_compte, intitule_compte, devise, 
                solde_initial, solde_actuel, date_ouverture, 
                contact_banque, telephone_banque, email_banque, 
                adresse_banque, est_actif, idUser) 
                VALUES 
                (:nom_banque, :numero_compte, :intitule_compte, :devise, 
                :solde_initial, :solde_actuel, :date_ouverture, 
                :contact_banque, :telephone_banque, :email_banque, 
                :adresse_banque, :est_actif, :idUser)
            ");
            
            $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
            $stmt->bindParam(':solde_actuel', $solde_actuel, PDO::PARAM_STR);
            $message = "Le compte bancaire a été créé avec succès.";
        }
        
        // Lier les paramètres communs
        $stmt->bindParam(':nom_banque', $nom_banque, PDO::PARAM_STR);
        $stmt->bindParam(':numero_compte', $numero_compte, PDO::PARAM_STR);
        $stmt->bindParam(':intitule_compte', $intitule_compte, PDO::PARAM_STR);
        $stmt->bindParam(':devise', $devise, PDO::PARAM_STR);
        $stmt->bindParam(':solde_initial', $solde_initial, PDO::PARAM_STR);
        $stmt->bindParam(':date_ouverture', $date_ouverture, PDO::PARAM_STR);
        $stmt->bindParam(':contact_banque', $contact_banque, PDO::PARAM_STR);
        $stmt->bindParam(':telephone_banque', $telephone_banque, PDO::PARAM_STR);
        $stmt->bindParam(':email_banque', $email_banque, PDO::PARAM_STR);
        $stmt->bindParam(':adresse_banque', $adresse_banque, PDO::PARAM_STR);
        $stmt->bindParam(':est_actif', $est_actif, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Récupérer l'ID si c'était une insertion
        if (!$id) {
            $id = $db->lastInsertId();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO journal_activites 
            (user_type, user_id, type_activite, id_element, description, date_activite) 
            VALUES 
            ('admin', :user_id, 'compte_bancaire', :id_element, :description, NOW())");
        
        $description = $id ? "Mise à jour du compte bancaire #$id" : "Création d'un nouveau compte bancaire #$id";
        
        $logStmt->bindParam(':user_id', $_SESSION['id'], PDO::PARAM_INT);
        $logStmt->bindParam(':id_element', $id, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Définir le message de succès
        $_SESSION['message'] = $message;
        $_SESSION['messageType'] = "success";
        
        header('Location: ../?view=finance/config_comptes_bancaires');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Erreur: " . $e->getMessage();
        $_SESSION['messageType'] = "danger";
        
        header('Location: ../?view=finance/config_comptes_bancaires');
        exit();
    }
} else {
    // Accès direct au contrôleur sans POST
    $_SESSION['message'] = "Accès non autorisé.";
    $_SESSION['messageType'] = "danger";
    
    header('Location: ../?view=finance/config_comptes_bancaires');
    exit();
}
