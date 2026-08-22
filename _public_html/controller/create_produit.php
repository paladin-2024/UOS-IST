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
        $id_compte_comptable = isset($_POST['id_compte_comptable']) ? intval($_POST['id_compte_comptable']) : null;
        $est_stock_suivi = isset($_POST['est_stock_suivi']) ? 1 : 0;
        $est_peremption_suivi = isset($_POST['est_peremption_suivi']) ? 1 : 0;
        $actif = isset($_POST['actif']) ? 1 : 0;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if (empty($code_produit) || empty($libelle_produit) || $id_categorie <= 0 || 
            empty($type_produit) || $id_unite_stockage <= 0 || $id_unite_vente <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier si le code produit existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM produit WHERE code_produit = :code_produit");
        $stmt->bindParam(':code_produit', $code_produit, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce code produit existe déjà. Veuillez en choisir un autre.");
        }
        
                // Traitement de l'image
                $image_produit = null;
                if (isset($_FILES['image_produit']) && $_FILES['image_produit']['error'] == 0) {
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
                    
                    $image_produit = $newFilename;
                }
                
                // Insertion dans la base de données
                $stmt = $db->prepare("INSERT INTO produit 
                    (code_produit, libelle_produit, description, id_categorie, type_produit, famille, 
                    id_unite_stockage, id_unite_vente, conditionnement, marge_beneficiaire, image_produit, 
                    poids, volume, id_compte_comptable, est_stock_suivi, est_peremption_suivi, actif, id_user_creation) 
                    VALUES 
                    (:code_produit, :libelle_produit, :description, :id_categorie, :type_produit, :famille, 
                    :id_unite_stockage, :id_unite_vente, :conditionnement, :marge_beneficiaire, :image_produit, 
                    :poids, :volume, :id_compte_comptable, :est_stock_suivi, :est_peremption_suivi, :actif, :id_user_creation)");
                
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
                $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                
                $stmt->execute();
                $idProduit = $db->lastInsertId();
                
                // Journalisation de l'action
                $logStmt = $db->prepare("INSERT INTO log_operation 
                    (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
                    VALUES 
                    (:id_user, 'création', 'produit', :id_enregistrement, :description, :adresse_ip, :navigateur)");
                
                $description = "Création du produit: $libelle_produit (Code: $code_produit)";
                $adresse_ip = $_SERVER['REMOTE_ADDR'];
                $navigateur = $_SERVER['HTTP_USER_AGENT'];
                
                $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
                $logStmt->bindParam(':id_enregistrement', $idProduit, PDO::PARAM_INT);
                $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
                $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
                $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
                
                $logStmt->execute();
                
                // Rediriger avec un message de succès
                echo "<script>
                    Swal.fire({
                        title: 'Succès',
                        text: 'Le produit a été créé avec succès.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '../produits/produits.list';
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
                        window.location.href = '../produits/produits.add';
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
        