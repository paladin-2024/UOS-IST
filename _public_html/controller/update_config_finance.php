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
        $devise_principale = isset($_POST['devise_principale']) ? trim($_POST['devise_principale']) : 'USD';
        $devise_secondaire = isset($_POST['devise_secondaire']) ? trim($_POST['devise_secondaire']) : 'CDF';
        $taux_change = isset($_POST['taux_change']) ? floatval($_POST['taux_change']) : 2000.000000;
        $date_mise_a_jour_taux = isset($_POST['date_mise_a_jour_taux']) && !empty($_POST['date_mise_a_jour_taux']) 
            ? date('Y-m-d H:i:s', strtotime($_POST['date_mise_a_jour_taux'])) 
            : date('Y-m-d H:i:s');
        
        $annee_fiscale_debut = isset($_POST['annee_fiscale_debut']) && !empty($_POST['annee_fiscale_debut']) 
            ? date('Y-m-d', strtotime($_POST['annee_fiscale_debut'])) 
            : null;
        $annee_fiscale_fin = isset($_POST['annee_fiscale_fin']) && !empty($_POST['annee_fiscale_fin']) 
            ? date('Y-m-d', strtotime($_POST['annee_fiscale_fin'])) 
            : null;
        
        $format_facture = isset($_POST['format_facture']) ? trim($_POST['format_facture']) : 'INV-{YEAR}-{NUM}';
        $numero_facture_suivant = isset($_POST['numero_facture_suivant']) ? intval($_POST['numero_facture_suivant']) : 1;
        $termes_paiement = isset($_POST['termes_paiement']) ? trim($_POST['termes_paiement']) : null;
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        
        // Gestion des fichiers uploadés
        $logo_facture = null;
        $signature_comptable = null;
        $signature_finance = null;
        
        // Récupérer les valeurs actuelles si l'ID est fourni
        if ($id) {
            $stmt = $db->prepare("SELECT logo_facture, signature_comptable, signature_finance FROM config_finance WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current) {
                $logo_facture = $current['logo_facture'];
                $signature_comptable = $current['signature_comptable'];
                $signature_finance = $current['signature_finance'];
            }
        }
        
        // Traitement du logo de facture
        if (isset($_FILES['logo_facture']) && $_FILES['logo_facture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['logo_facture']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Format de fichier non autorisé pour le logo. Formats acceptés: JPG, PNG.");
            }
            
            if ($_FILES['logo_facture']['size'] > 2 * 1024 * 1024) { // 2 Mo
                throw new Exception("Le logo est trop volumineux. Taille maximale: 2 Mo.");
            }
            
            $newFilename = 'logo_facture_' . time() . '.' . $ext;
            $uploadDir = dirname(__DIR__) . '/uploads/finance/';
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadFile = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['logo_facture']['tmp_name'], $uploadFile)) {
                $logo_facture = $newFilename;
            } else {
                throw new Exception("Erreur lors du téléchargement du logo.");
            }
        }
        
        // Traitement de la signature du comptable
        if (isset($_FILES['signature_comptable']) && $_FILES['signature_comptable']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['signature_comptable']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Format de fichier non autorisé pour la signature du comptable. Formats acceptés: JPG, PNG.");
            }
            
            if ($_FILES['signature_comptable']['size'] > 2 * 1024 * 1024) { // 2 Mo
                throw new Exception("La signature est trop volumineuse. Taille maximale: 2 Mo.");
            }
            
            $newFilename = 'signature_comptable_' . time() . '.' . $ext;
            $uploadDir = dirname(__DIR__) . '/uploads/finance/';
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadFile = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['signature_comptable']['tmp_name'], $uploadFile)) {
                $signature_comptable = $newFilename;
            } else {
                throw new Exception("Erreur lors du téléchargement de la signature du comptable.");
            }
        }
        
        // Traitement de la signature du responsable financier
        if (isset($_FILES['signature_finance']) && $_FILES['signature_finance']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['signature_finance']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Format de fichier non autorisé pour la signature financière. Formats acceptés: JPG, PNG.");
            }
            
            if ($_FILES['signature_finance']['size'] > 2 * 1024 * 1024) { // 2 Mo
                throw new Exception("La signature est trop volumineuse. Taille maximale: 2 Mo.");
            }
            
            $newFilename = 'signature_finance_' . time() . '.' . $ext;
            $uploadDir = dirname(__DIR__) . '/uploads/finance/';
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadFile = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['signature_finance']['tmp_name'], $uploadFile)) {
                $signature_finance = $newFilename;
            } else {
                throw new Exception("Erreur lors du téléchargement de la signature financière.");
            }
        }
        
        // Désactiver toutes les configurations si celle-ci est active
        if ($est_actif) {
            $stmt = $db->prepare("UPDATE config_finance SET est_actif = 0");
            $stmt->execute();
        }
        
        // Vérifier si on doit mettre à jour ou insérer
        if ($id) {
            // Mise à jour
            $stmt = $db->prepare("
                UPDATE config_finance SET 
                    devise_principale = :devise_principale,
                    devise_secondaire = :devise_secondaire,
                    taux_change = :taux_change,
                    date_mise_a_jour_taux = :date_mise_a_jour_taux,
                    annee_fiscale_debut = :annee_fiscale_debut,
                    annee_fiscale_fin = :annee_fiscale_fin,
                    format_facture = :format_facture,
                    numero_facture_suivant = :numero_facture_suivant,
                    logo_facture = :logo_facture,
                    signature_comptable = :signature_comptable,
                    signature_finance = :signature_finance,
                    termes_paiement = :termes_paiement,
                    est_actif = :est_actif
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        } else {
            // Insertion
            $stmt = $db->prepare("
                INSERT INTO config_finance 
                (devise_principale, devise_secondaire, taux_change, date_mise_a_jour_taux, 
                annee_fiscale_debut, annee_fiscale_fin, format_facture, numero_facture_suivant, 
                logo_facture, signature_comptable, signature_finance, termes_paiement, est_actif) 
                VALUES 
                (:devise_principale, :devise_secondaire, :taux_change, :date_mise_a_jour_taux, 
                :annee_fiscale_debut, :annee_fiscale_fin, :format_facture, :numero_facture_suivant, 
                :logo_facture, :signature_comptable, :signature_finance, :termes_paiement, :est_actif)
            ");
        }
        
        $stmt->bindParam(':devise_principale', $devise_principale, PDO::PARAM_STR);
        $stmt->bindParam(':devise_secondaire', $devise_secondaire, PDO::PARAM_STR);
        $stmt->bindParam(':taux_change', $taux_change, PDO::PARAM_STR);
        $stmt->bindParam(':date_mise_a_jour_taux', $date_mise_a_jour_taux, PDO::PARAM_STR);
        $stmt->bindParam(':annee_fiscale_debut', $annee_fiscale_debut, PDO::PARAM_STR);
        $stmt->bindParam(':annee_fiscale_fin', $annee_fiscale_fin, PDO::PARAM_STR);
        $stmt->bindParam(':format_facture', $format_facture, PDO::PARAM_STR);
        $stmt->bindParam(':numero_facture_suivant', $numero_facture_suivant, PDO::PARAM_INT);
        $stmt->bindParam(':logo_facture', $logo_facture, PDO::PARAM_STR);
        $stmt->bindParam(':signature_comptable', $signature_comptable, PDO::PARAM_STR);
        $stmt->bindParam(':signature_finance', $signature_finance, PDO::PARAM_STR);
        $stmt->bindParam(':termes_paiement', $termes_paiement, PDO::PARAM_STR);
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
            ('admin', :user_id, 'configuration', :id_element, :description, NOW())");
        
        $description = "Mise à jour de la configuration financière";
        
        $logStmt->bindParam(':user_id', $_SESSION['id'], PDO::PARAM_INT);
        $logStmt->bindParam(':id_element', $id, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Définir le message de succès
        $_SESSION['message'] = "La configuration financière a été enregistrée avec succès.";
        $_SESSION['messageType'] = "success";
        
        header('Location: ../?view=finance/config_finance');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Erreur: " . $e->getMessage();
        $_SESSION['messageType'] = "danger";
        
        header('Location: ../?view=finance/config_finance');
        exit();
    }
} else {
    // Accès direct au contrôleur sans POST
    $_SESSION['message'] = "Accès non autorisé.";
    $_SESSION['messageType'] = "danger";
    
    header('Location: ../?view=finance/config_finance');
    exit();
}