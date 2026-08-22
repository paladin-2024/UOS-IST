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
        // Récupérer l'ID du dépôt
        $id_depot = isset($_POST['id_depot']) ? intval($_POST['id_depot']) : 0;
        
        if ($id_depot <= 0) {
            throw new Exception("ID dépôt invalide.");
        }
        
        // Vérifier si le dépôt existe
        $checkStmt = $db->prepare("SELECT * FROM depot WHERE id_depot = :id_depot");
        $checkStmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() == 0) {
            throw new Exception("Le dépôt demandé n'existe pas.");
        }
        
        $depotActuel = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les données du formulaire
        $code_depot = isset($_POST['code_depot']) ? trim($_POST['code_depot']) : '';
        $libelle_depot = isset($_POST['libelle_depot']) ? trim($_POST['libelle_depot']) : '';
        $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : null;
        $responsable = isset($_POST['responsable']) ? trim($_POST['responsable']) : null;
        $actif = isset($_POST['actif']) ? 1 : 0;
        $id_user_modification = $_SESSION['id'];
        
        // Validation des données
        if (empty($code_depot) || empty($libelle_depot)) {
            throw new Exception("Le code et le libellé du dépôt sont obligatoires.");
        }
        
        // Vérifier si le code existe déjà pour un autre dépôt
        $stmt = $db->prepare("SELECT COUNT(*) FROM depot WHERE code_depot = :code_depot AND id_depot != :id_depot");
        $stmt->bindParam(':code_depot', $code_depot, PDO::PARAM_STR);
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce code de dépôt existe déjà. Veuillez en choisir un autre.");
        }
        
        // Mise à jour dans la base de données
        $stmt = $db->prepare("UPDATE depot SET 
            code_depot = :code_depot,
            libelle_depot = :libelle_depot,
            adresse = :adresse,
            responsable = :responsable,
            actif = :actif
            WHERE id_depot = :id_depot");
        
        $stmt->bindParam(':code_depot', $code_depot, PDO::PARAM_STR);
        $stmt->bindParam(':libelle_depot', $libelle_depot, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':responsable', $responsable, PDO::PARAM_STR);
        $stmt->bindParam(':actif', $actif, PDO::PARAM_INT);
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'depot', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Modification du dépôt: $libelle_depot (Code: $code_depot)";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_modification, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_depot, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le dépôt a été modifié avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../depots/depots.view&id=$id_depot';
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
                window.location.href = '../depots/depots.edit&id=" . (isset($id_depot) ? $id_depot : 0) . "';
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
            window.location.href = '../depots/depots.list';
        });
    </script>";
    exit;
}
