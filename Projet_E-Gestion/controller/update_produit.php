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
        // Récupérer l'ID du produit
        $id_produit = isset($_POST['id_produit']) ? intval($_POST['id_produit']) : 0;
        
        if ($id_produit <= 0) {
            throw new Exception("ID produit invalide.");
        }
        
        // Vérifier si le produit existe
        $checkStmt = $db->prepare("SELECT * FROM produit WHERE id_produit = :id_produit");
        $checkStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() == 0) {
            throw new Exception("Le produit demandé n'existe pas.");
        }
        
        $produitActuel = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les données du formulaire
        $code_produit = isset($_POST['code_produit']) ? trim($_POST['code_produit']) : '';
        $libelle_produit = isset($_POST['libelle_produit']) ? trim($_POST['libelle_produit']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $id_categorie = isset($_POST['id_categorie']) ? intval($_POST['id_categorie']) : 0;
        $type_produit = isset($_POST['type_produit']) ? trim($_POST['type_produit']) : '';
        $famille = isset($_POST['famille']) ? trim($_POST['famille']) : null;
        $id_unite_stockage = isset($_POST['id_unite_stockage']) ? intval($_POST['id_unite_stockage']) : 0;
        $id_unite_vente = isset($_POST['id_unite_vente']) ? intval($_POST['id_unite_vente']) : 0;
        $conditionnement = isset($_POST['conditionnement']) ? floatval($_POST['conditionnement']) : 1.00;
        $marge_beneficiaire = isset($_POST['marge_beneficiaire']) ? floatval($_POST['marge_beneficiaire']) : 0.00;
        $poids = isset($_POST['poids']) && $_POST['poids'] !== '' ? floatval($_POST['poids']) : null;
        $volume = isset($_POST['volume']) && $_POST['volume'] !== '' ? floatval($_POST['volume']) : null;
        $id_compte_comptable = isset($_POST['id_compte_comptable']) ? intval($_POST['id_compte_comptable']) : 0;
        $est_stock_suivi = isset($_POST['est_stock_suivi']) ? 1 : 0;
        $est_peremption_suivi = isset($_POST['est_peremption_suivi']) ? 1 : 0;
        $actif = isset($_POST['actif']) ? 1 : 0;
        $id_user_modification = $_SESSION['id'];
        
        // Validation des données
        if (empty($code_produit) || empty($libelle_produit) || $id_categorie <= 0 || 
            empty($type_produit) || $id_unite_stockage <= 0 || $id_unite_vente <= 0 || 
            $id_compte_comptable <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier si le code produit existe déjà pour un autre produit
        $stmt = $db->prepare("SELECT COUNT(*) FROM produit WHERE code_produit = :code_produit AND id_produit != :id_produit");
        $stmt->bindParam(':code_produit', $code_produit, PDO::PARAM_STR);
        $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce code produit existe déjà. Veuillez en choisir un autre.");
        }
        
        // Traitement de l'image
        $image_produit = $produitActuel['image_produit']; // Conserver l'image actuelle par défaut
        
        // Si demande de suppression de l'image
        if (isset($_POST['supprimer_image']) && $_POST['supprimer_image'] == 'on') {
            if (!empty($image_produit)) {
                $imagePath = dirname(__DIR__) . '/uploads/produits/' . $image_produit;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                $image_produit = null;
            }
        }
        // Si upload d'une nouvelle image
        elseif (isset($_FILES['image_produit']) && $_FILES['image_produit']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['image_produit']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Format de fichier non autorisé. Formats acceptés: JPG, PNG.");
            }
            
            if ($_FILES['image_produit']['size'] > 2 * 1024 * 1024) { // 2 Mo
                throw new Exception("L'image est trop volumineuse. Taille maximale: 2 Mo.");
            }
            
            $newFilename = 'prod_' . time() . '_' . $code_produit . '.' . $ext;
            $uploadDir = dirname(__DIR__) . '/uploads/produits/';
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadFile = $uploadDir . $newFilename;
            
            if (!move_uploaded_file($_FILES['image_produit']['tmp_name'], $uploadFile)) {
                throw new Exception("Erreur lors du téléchargement de l'image.");
            }
            
            // Supprimer l'ancienne image si elle existe
            if (!empty($produitActuel['image_produit'])) {
                $oldImagePath = $uploadDir . $produitActuel['image_produit'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            $image_produit = $newFilename;
        }
        
        // Mise à jour dans la base de données
        $stmt = $db->prepare("UPDATE produit SET 
            code_produit = :code_produit,
            libelle_produit = :libelle_produit,
            description = :description,
            id_categorie = :id_categorie,
            type_produit = :type_produit,
            famille = :famille,
            id_unite_stockage = :id_unite_stockage,
            id_unite_vente = :id_unite_vente,
            conditionnement = :conditionnement,
            marge_beneficiaire = :marge_beneficiaire,
            image_produit = :image_produit,
            poids = :poids,
            volume = :volume,
            id_compte_comptable = :id_compte_comptable,
            est_stock_suivi = :est_stock_suivi,
            est_peremption_suivi = :est_peremption_suivi,
            actif = :actif
            WHERE id_produit = :id_produit");
        
        $stmt->bindParam(':code_produit', $code_produit, PDO::PARAM_STR);
        $stmt->bindParam(':libelle_produit', $libelle_produit, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':id_categorie', $id_categorie, PDO::PARAM_INT);
        $stmt->bindParam(':type_produit', $type_produit, PDO::PARAM_STR);
        $stmt->bindParam(':famille', $famille, PDO::PARAM_STR);
        $stmt->bindParam(':id_unite_stockage', $id_unite_stockage, PDO::PARAM_INT);
        $stmt->bindParam(':id_unite_vente', $id_unite_vente, PDO::PARAM_INT);
        $stmt->bindParam(':conditionnement', $conditionnement, PDO::PARAM_STR);
        $stmt->bindParam(':marge_beneficiaire', $marge_beneficiaire, PDO::PARAM_STR);
        $stmt->bindParam(':image_produit', $image_produit, PDO::PARAM_STR);
        $stmt->bindParam(':poids', $poids, PDO::PARAM_STR);
        $stmt->bindParam(':volume', $volume, PDO::PARAM_STR);
        $stmt->bindParam(':id_compte_comptable', $id_compte_comptable, PDO::PARAM_INT);
        $stmt->bindParam(':est_stock_suivi', $est_stock_suivi, PDO::PARAM_INT);
        $stmt->bindParam(':est_peremption_suivi', $est_peremption_suivi, PDO::PARAM_INT);
        $stmt->bindParam(':actif', $actif, PDO::PARAM_INT);
        $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Gestion des fournisseurs
        if (isset($_POST['fournisseur_id']) && is_array($_POST['fournisseur_id'])) {
            // Récupérer l'index du fournisseur principal
            $fournisseurPrincipalIndex = isset($_POST['fournisseur_principal']) ? intval($_POST['fournisseur_principal']) : -1;
            
            // Supprimer les associations existantes
            $deleteStmt = $db->prepare("DELETE FROM produit_fournisseur WHERE id_produit = :id_produit");
            $deleteStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Insérer les nouvelles associations
            $insertStmt = $db->prepare("INSERT INTO produit_fournisseur 
                (id_produit, id_fournisseur, prix_achat, delai_livraison, est_fournisseur_principal, id_user_creation) 
                VALUES 
                (:id_produit, :id_fournisseur, :prix_achat, :delai_livraison, :est_fournisseur_principal, :id_user_creation)");
            
            foreach ($_POST['fournisseur_id'] as $index => $fournisseurId) {
                $prixAchat = isset($_POST['prix_achat'][$index]) ? floatval($_POST['prix_achat'][$index]) : 0;
                $delaiLivraison = isset($_POST['delai_livraison'][$index]) ? intval($_POST['delai_livraison'][$index]) : null;
                $estPrincipal = ($index == $fournisseurPrincipalIndex) ? 1 : 0;
                
                $insertStmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $insertStmt->bindParam(':id_fournisseur', $fournisseurId, PDO::PARAM_INT);
                $insertStmt->bindParam(':prix_achat', $prixAchat, PDO::PARAM_STR);
                $insertStmt->bindParam(':delai_livraison', $delaiLivraison, PDO::PARAM_INT);
                $insertStmt->bindParam(':est_fournisseur_principal', $estPrincipal, PDO::PARAM_INT);
                $insertStmt->bindParam(':id_user_creation', $id_user_modification, PDO::PARAM_INT);
                
                $insertStmt->execute();
            }
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'modification', 'produit', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Modification du produit: $libelle_produit (Code: $code_produit)";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_modification, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_produit, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le produit a été modifié avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../produits/produits.view&id=$id_produit';
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
                window.location.href = '../produits/produits.edit&id=$id_produit';
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
            window.location.href = '../produits/produits.list';
        });
    </script>";
    exit;
}
