<?php
session_start();
require_once '../config/Connexion.php';

// Vérifier que l'étudiant est connecté
if (!isset($_SESSION['student_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../portail/frais_academiques');
    exit();
}

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: ../portail/frais_academiques');
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les données du formulaire
    $affectation_id = $_POST['affectation_id'] ?? null;
    $matricule_etudiant = $_POST['matricule_etudiant'] ?? '';
    $montant = $_POST['montant'] ?? 0;
    $date_paiement = $_POST['date_paiement'] ?? '';
    $mode_paiement = $_POST['mode_paiement'] ?? '';
    $lieu_paiement = $_POST['lieu_paiement'] ?? '';
    $reference_paiement = $_POST['reference_paiement'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';
    
    // Validation des données
    if (!$affectation_id || !$matricule_etudiant || !$montant || !$date_paiement || 
        !$mode_paiement || !$lieu_paiement || !$reference_paiement) {
        throw new Exception("Tous les champs obligatoires doivent être remplis.");
    }
    
    // Vérifier que le matricule correspond à l'étudiant connecté
    if ($matricule_etudiant !== $_SESSION['student_matricule']) {
        throw new Exception("Erreur d'authentification.");
    }
    
    // Vérifier qu'il n'existe pas déjà une déclaration en attente ou validée pour ce frais
    $stmt_doublon = $connexion->prepare("
        SELECT statut_validation FROM declarations_paiement 
        WHERE affectation_id = :affectation_id 
        AND matricule_etudiant = :matricule 
        AND statut_validation IN ('en_attente', 'validé')
        LIMIT 1
    ");
    $stmt_doublon->bindParam(':affectation_id', $affectation_id);
    $stmt_doublon->bindParam(':matricule', $matricule_etudiant);
    $stmt_doublon->execute();
    $existing = $stmt_doublon->fetchColumn();
    if ($existing === 'validé') {
        throw new Exception("Ce frais a déjà une déclaration validée.");
    } elseif ($existing === 'en_attente') {
        throw new Exception("Vous avez déjà une déclaration en attente pour ce frais. Veuillez attendre sa validation avant d'en soumettre une nouvelle.");
    }
    
    // Récupérer les informations du frais et la devise
    $stmt = $connexion->prepare("
        SELECT af.*, f.devise as devise_frais, f.designation as frais_designation
        FROM affectation_frais af
        INNER JOIN frais f ON af.frais_id = f.id
        WHERE af.id = :affectation_id
    ");
    $stmt->bindParam(':affectation_id', $affectation_id);
    $stmt->execute();
    $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$affectation) {
        throw new Exception("Frais non trouvé.");
    }
    
    $devise = $affectation['devise'] ?: $affectation['devise_frais'];
    
    // Gestion du fichier de preuve
    $preuve_paiement = null;
    if (isset($_FILES['preuve_paiement']) && $_FILES['preuve_paiement']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/preuves_paiement/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileInfo = pathinfo($_FILES['preuve_paiement']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Vérifier l'extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Format de fichier non autorisé. Utilisez JPG, PNG ou PDF.");
        }
        
        // Vérifier la taille (5 Mo max)
        if ($_FILES['preuve_paiement']['size'] > 5 * 1024 * 1024) {
            throw new Exception("Le fichier est trop volumineux (max 5 Mo).");
        }
        
        // Générer un nom unique
        $fileName = 'preuve_' . $matricule_etudiant . '_' . time() . '_' . uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . $fileName;
        
        // Déplacer le fichier
        if (move_uploaded_file($_FILES['preuve_paiement']['tmp_name'], $uploadPath)) {
            $preuve_paiement = $fileName;
        } else {
            throw new Exception("Erreur lors du téléchargement du fichier.");
        }
    } else {
        throw new Exception("La preuve de paiement est obligatoire.");
    }
    
    // Insérer la déclaration dans la base de données
    $stmt = $connexion->prepare("
        INSERT INTO declarations_paiement (
            affectation_id,
            matricule_etudiant,
            montant,
            devise,
            date_paiement,
            mode_paiement,
            lieu_paiement,
            reference_paiement,
            preuve_paiement,
            commentaire,
            statut_validation,
            date_declaration
        ) VALUES (
            :affectation_id,
            :matricule_etudiant,
            :montant,
            :devise,
            :date_paiement,
            :mode_paiement,
            :lieu_paiement,
            :reference_paiement,
            :preuve_paiement,
            :commentaire,
            'en_attente',
            NOW()
        )
    ");
    
    $stmt->bindParam(':affectation_id', $affectation_id);
    $stmt->bindParam(':matricule_etudiant', $matricule_etudiant);
    $stmt->bindParam(':montant', $montant);
    $stmt->bindParam(':devise', $devise);
    $stmt->bindParam(':date_paiement', $date_paiement);
    $stmt->bindParam(':mode_paiement', $mode_paiement);
    $stmt->bindParam(':lieu_paiement', $lieu_paiement);
    $stmt->bindParam(':reference_paiement', $reference_paiement);
    $stmt->bindParam(':preuve_paiement', $preuve_paiement);
    $stmt->bindParam(':commentaire', $commentaire);
    
    if ($stmt->execute()) {
        // Enregistrer une notification pour l'administration
        $notification_message = "Nouvelle déclaration de paiement de " . $_SESSION['student_name'] . 
                              " pour " . $affectation['frais_designation'] . 
                              " - Montant: " . number_format($montant, 2) . " " . $devise;
        
        $stmt_notif = $connexion->prepare("
            INSERT INTO notifications_admin (
                type_notification,
                message,
                reference_id,
                date_creation,
                est_lu
            ) VALUES (
                'declaration_paiement',
                :message,
                :reference_id,
                NOW(),
                0
            )
        ");
        $stmt_notif->bindParam(':message', $notification_message);
        $stmt_notif->bindParam(':reference_id', $connexion->lastInsertId());
        $stmt_notif->execute();
        
        $_SESSION['success'] = "Votre déclaration de paiement a été soumise avec succès. Elle sera vérifiée par l'administration.";
    } else {
        throw new Exception("Erreur lors de l'enregistrement de la déclaration.");
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Supprimer le fichier uploadé en cas d'erreur
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
}

// Redirection vers la page des frais académiques
header('Location: ../portail/frais_academiques');
exit();
?>