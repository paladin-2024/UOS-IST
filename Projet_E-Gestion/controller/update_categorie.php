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
        $id_categorie = isset($_POST['id_categorie']) ? intval($_POST['id_categorie']) : 0;
        $code_categorie = isset($_POST['code_categorie']) ? trim($_POST['code_categorie']) : '';
        $libelle_categorie = isset($_POST['libelle_categorie']) ? trim($_POST['libelle_categorie']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if ($id_categorie <= 0 || empty($code_categorie) || empty($libelle_categorie)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier si la catégorie existe
        $stmt = $db->prepare("SELECT * FROM categorie_produit WHERE id_categorie = :id_categorie");
        $stmt->bindParam(':id_categorie', $id_categorie, PDO::PARAM_INT);
        $stmt->execute();
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categorie) {
            throw new Exception("Catégorie non trouvée.");
        }
        
        // Vérifier si le code existe déjà pour une autre catégorie
        $stmt = $db->prepare("SELECT COUNT(*) FROM categorie_produit WHERE code_categorie = :code_categorie AND id_categorie != :id_categorie");
        $stmt->bindParam(':code_categorie', $code_categorie, PDO::PARAM_STR);
        $stmt->bindParam(':id_categorie', $id_categorie, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce code de catégorie est déjà utilisé par une autre catégorie.");
        }
        
        // Mise à jour dans la base de données
        $stmt = $db->prepare("UPDATE categorie_produit 
            SET code_categorie = :code_categorie, 
                libelle_categorie = :libelle_categorie, 
                description = :description 
            WHERE id_categorie = :id_categorie");
        
        $stmt->bindParam(':code_categorie', $code_categorie, PDO::PARAM_STR);
        $stmt->bindParam(':libelle_categorie', $libelle_categorie, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':id_categorie', $id_categorie, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'categorie_produit', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Modification de la catégorie: $libelle_categorie (Code: $code_categorie)";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_categorie, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La catégorie a été modifiée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../produits/categories.list';
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
                window.location.href = '../produits/categories.list';
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
            window.location.href = '../produits/categories.list';
        });
    </script>";
    exit;
}
