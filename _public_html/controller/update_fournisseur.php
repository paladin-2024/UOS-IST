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
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $nom_fournisseur = isset($_POST['nom_fournisseur']) ? trim($_POST['nom_fournisseur']) : '';
        $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : null;
        $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : null;
        $email = isset($_POST['email']) ? trim($_POST['email']) : null;
        $nif = isset($_POST['nif']) ? trim($_POST['nif']) : null;
        $rccm = isset($_POST['rccm']) ? trim($_POST['rccm']) : null;
        $id_compte_comptable = isset($_POST['id_compte_comptable']) ? intval($_POST['id_compte_comptable']) : 0;
        $delai_paiement = isset($_POST['delai_paiement']) ? intval($_POST['delai_paiement']) : 0;
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        // Validation des données
        if ($id_fournisseur <= 0 || empty($nom_fournisseur) || $id_compte_comptable <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier si le fournisseur existe
        $stmt = $db->prepare("SELECT code_fournisseur FROM fournisseur WHERE id_fournisseur = :id");
        $stmt->bindParam(':id', $id_fournisseur, PDO::PARAM_INT);
        $stmt->execute();
        $codeFournisseur = $stmt->fetchColumn();
        
        if (!$codeFournisseur) {
            throw new Exception("Ce fournisseur n'existe pas.");
        }
        
        // Validation de l'email
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Le format de l'adresse email est invalide.");
        }
        
        // Mise à jour du fournisseur dans la base de données
        $stmt = $db->prepare("UPDATE fournisseur SET 
                                nom_fournisseur = :nom_fournisseur,
                                adresse = :adresse,
                                telephone = :telephone,
                                email = :email,
                                nif = :nif,
                                rccm = :rccm,
                                id_compte_comptable = :id_compte_comptable,
                                delai_paiement = :delai_paiement,
                                actif = :actif
                            WHERE id_fournisseur = :id_fournisseur");
        
        $stmt->bindParam(':nom_fournisseur', $nom_fournisseur, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':nif', $nif, PDO::PARAM_STR);
        $stmt->bindParam(':rccm', $rccm, PDO::PARAM_STR);
        $stmt->bindParam(':id_compte_comptable', $id_compte_comptable, PDO::PARAM_INT);
        $stmt->bindParam(':delai_paiement', $delai_paiement, PDO::PARAM_INT);
        $stmt->bindParam(':actif', $actif, PDO::PARAM_INT);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Journalisation de l'action
        $id_user = $_SESSION['id'];
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Mise à jour du fournisseur: $nom_fournisseur (Code: $codeFournisseur)";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_fournisseur, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Redirection avec message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le fournisseur a été modifié avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../fournisseurs/fournisseurs.view&id={$id_fournisseur}';
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
        }).then((result) => {
            window.location.href = '../fournisseurs/fournisseurs.list';
        });
    </script>";
    exit;
}
