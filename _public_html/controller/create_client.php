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
        $code_client = isset($_POST['code_client']) ? trim($_POST['code_client']) : '';
        $type_client = isset($_POST['type_client']) ? trim($_POST['type_client']) : '';
        $nom_client = isset($_POST['nom_client']) ? trim($_POST['nom_client']) : '';
        $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : null;
        $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : null;
        $email = isset($_POST['email']) ? trim($_POST['email']) : null;
        $nif = isset($_POST['nif']) ? trim($_POST['nif']) : null;
        $rccm = isset($_POST['rccm']) ? trim($_POST['rccm']) : null;
        $id_compte_comptable = isset($_POST['id_compte_comptable']) ? intval($_POST['id_compte_comptable']) : 0;
        $plafond_credit = isset($_POST['plafond_credit']) ? floatval($_POST['plafond_credit']) : 0.00;
        $delai_paiement = isset($_POST['delai_paiement']) ? intval($_POST['delai_paiement']) : 0;
        $actif = isset($_POST['actif']) ? 1 : 0;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if (empty($code_client) || empty($type_client) || empty($nom_client) || $id_compte_comptable <= 0) {
            throw new Exception("Les champs Code, Type, Nom et Compte comptable sont obligatoires.");
        }
        
        // Vérifier si le code existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM client WHERE code_client = :code_client");
        $stmt->bindParam(':code_client', $code_client, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce code client existe déjà. Veuillez en choisir un autre.");
        }
        
        // Validation email si fourni
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Le format de l'adresse email est invalide.");
        }
        
        // Insertion dans la base de données
        $stmt = $db->prepare("INSERT INTO client 
            (code_client, type_client, nom_client, adresse, telephone, email, nif, rccm, 
            id_compte_comptable, plafond_credit, delai_paiement, actif, id_user_creation) 
            VALUES 
            (:code_client, :type_client, :nom_client, :adresse, :telephone, :email, :nif, :rccm, 
            :id_compte_comptable, :plafond_credit, :delai_paiement, :actif, :id_user_creation)");
        
        $stmt->bindParam(':code_client', $code_client, PDO::PARAM_STR);
        $stmt->bindParam(':type_client', $type_client, PDO::PARAM_STR);
        $stmt->bindParam(':nom_client', $nom_client, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':nif', $nif, PDO::PARAM_STR);
        $stmt->bindParam(':rccm', $rccm, PDO::PARAM_STR);
        $stmt->bindParam(':id_compte_comptable', $id_compte_comptable, PDO::PARAM_INT);
        $stmt->bindParam(':plafond_credit', $plafond_credit, PDO::PARAM_STR);
        $stmt->bindParam(':delai_paiement', $delai_paiement, PDO::PARAM_INT);
        $stmt->bindParam(':actif', $actif, PDO::PARAM_INT);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        
        $stmt->execute();
        $idClient = $db->lastInsertId();
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'client', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Création du client: $nom_client (Code: $code_client)";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $idClient, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le client a été créé avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../clients/clients.list';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../clients/clients.add';
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
            window.location.href = '../clients/clients.list';
        });
    </script>";
    exit;
}
