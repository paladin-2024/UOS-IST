<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$idUser = $_SESSION['id'];
$connexion = Connexion::getInstance()->getPDO();

// Vérifier que l'utilisateur a des droits sur les caisses
$stmt = $connexion->prepare("
    SELECT COUNT(*) as count
    FROM droits_acces_finances 
    WHERE \"idUser\" = :idUser AND type = 'Caisse' 
    AND est_actif = 1 
    AND (date_debut IS NULL OR date_debut <= CURRENT_DATE) 
    AND (date_fin IS NULL OR date_fin >= CURRENT_DATE)
");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$acces_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($acces_count == 0) {
    $_SESSION['message'] = "Vous n'avez pas les droits nécessaires pour effectuer cette action.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/sessions_caisse');
    exit;
}

try {
    // Déterminer l'action à effectuer
    $action = $_POST['action'] ?? '';
    
    // Ouvrir une nouvelle session
    if ($action === 'ouvrir') {
        $caisse_id = intval($_POST['caisse_id']);
        $idAgent = intval($_POST['idAgent']);
        $montant_ouverture = floatval($_POST['montant_ouverture']);
        $commentaire = $_POST['commentaire'] ?? null;
        
        // Vérifier les droits spécifiques pour cette caisse
        $stmt = $connexion->prepare("
            SELECT niveau 
            FROM droits_acces_finances 
            WHERE \"idUser\" = :idUser AND type = 'Caisse' 
            AND (entite_id = :caisse_id OR entite_id IS NULL)
            AND est_actif = 1
            ORDER BY entite_id DESC, niveau DESC
            LIMIT 1
        ");
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':caisse_id', $caisse_id);
        $stmt->execute();
        $droit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$droit || $droit['niveau'] === 'Lecture') {
            $_SESSION['message'] = "Vous n'avez pas les droits nécessaires pour ouvrir une session sur cette caisse.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Vérifier si une session est déjà ouverte pour cette caisse
        $stmt = $connexion->prepare("
            SELECT COUNT(*) as count 
            FROM sessions_caisse 
            WHERE caisse_id = :caisse_id AND statut = 'Ouverte'
        ");
        $stmt->bindParam(':caisse_id', $caisse_id);
        $stmt->execute();
        $session_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($session_count > 0) {
            $_SESSION['message'] = "Une session est déjà ouverte pour cette caisse.";
            $_SESSION['messageType'] = "warning";
            header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Vérifier que l'agent est bien l'utilisateur connecté
        $stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
        $stmt->bindParam(':idUser', $idUser);
        $stmt->execute();
        $user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_agent || $user_agent['idAgent'] != $idAgent) {
            $_SESSION['message'] = "Vous ne pouvez pas ouvrir une session pour un autre agent.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Ouvrir la session
        $stmt = $connexion->prepare("
            INSERT INTO sessions_caisse (
                caisse_id, \"idAgent\", date_ouverture, montant_ouverture, statut, commentaire
            ) VALUES (
                :caisse_id, :idAgent, NOW(), :montant_ouverture, 'Ouverte', :commentaire
            )
        ");
        $stmt->bindParam(':caisse_id', $caisse_id);
        $stmt->bindParam(':idAgent', $idAgent);
        $stmt->bindParam(':montant_ouverture', $montant_ouverture);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->execute();
        
        $_SESSION['message'] = "La session de caisse a été ouverte avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
    // Fermer une session existante
    elseif ($action === 'fermer') {
        $session_id = intval($_POST['session_id']);
        $caisse_id = intval($_POST['caisse_id']);
        $montant_fermeture = floatval($_POST['montant_fermeture']);
        $explication_difference = $_POST['explication_difference'] ?? null;
        
        // Vérifier que la session existe et est ouverte
        $stmt = $connexion->prepare("
            SELECT s.*, c.designation as caisse_nom, c.devise 
            FROM sessions_caisse s
            JOIN caisses c ON s.caisse_id = c.id
            WHERE s.id = :session_id AND s.statut = 'Ouverte'
        ");
        $stmt->bindParam(':session_id', $session_id);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            $_SESSION['message'] = "La session demandée n'existe pas ou n'est pas ouverte.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Vérifier que l'utilisateur est bien l'agent qui a ouvert la session
        $stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
        $stmt->bindParam(':idUser', $idUser);
        $stmt->execute();
        $user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_agent || $user_agent['idAgent'] != $session['idAgent']) {
            $_SESSION['message'] = "Vous ne pouvez pas fermer une session ouverte par un autre agent.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Calculer la différence
        $montant_calcule = $session['montant_ouverture']; // À remplacer par un calcul basé sur les transactions
        $difference = $montant_fermeture - $montant_calcule;
        
        // Si différence et pas d'explication, exiger une explication
        if ($difference != 0 && empty($explication_difference)) {
            $_SESSION['message'] = "Veuillez fournir une explication pour la différence de " . number_format($difference, 2) . " " . $session['devise'];
            $_SESSION['messageType'] = "warning";
            header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Fermer la session
        $stmt = $connexion->prepare("
            UPDATE sessions_caisse SET
                date_fermeture = NOW(),
                montant_fermeture = :montant_fermeture,
                montant_calcule = :montant_calcule,
                difference = :difference,
                explication_difference = :explication_difference,
                statut = 'Fermée'
            WHERE id = :session_id
        ");
        $stmt->bindParam(':session_id', $session_id);
        $stmt->bindParam(':montant_fermeture', $montant_fermeture);
        $stmt->bindParam(':montant_calcule', $montant_calcule);
        $stmt->bindParam(':difference', $difference);
        $stmt->bindParam(':explication_difference', $explication_difference);
        $stmt->execute();
        
        // Mettre à jour le solde de la caisse
        $stmt = $connexion->prepare("
            UPDATE caisses SET
                solde_actuel = :montant_fermeture
            WHERE id = :caisse_id
        ");
        $stmt->bindParam(':montant_fermeture', $montant_fermeture);
        $stmt->bindParam(':caisse_id', $caisse_id);
        $stmt->execute();
        
        $_SESSION['message'] = "La session de caisse a été fermée avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
    // Valider une session fermée
    elseif ($action === 'valider') {
        $session_id = intval($_POST['session_id']);
        $commentaire_validation = $_POST['commentaire_validation'] ?? null;
        
        // Récupérer l'agent ID de l'utilisateur connecté (pour la validation)
        $stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
        $stmt->bindParam(':idUser', $idUser);
        $stmt->execute();
        $user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
        $idValidateur = $user_agent['idAgent'] ?? null;
        
        if (!$idValidateur) {
            $_SESSION['message'] = "Erreur: Aucun agent associé à votre compte utilisateur.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/sessions_caisse');
            exit;
        }
        
        // Vérifier que la session existe et est fermée
        $stmt = $connexion->prepare("
            SELECT s.*, c.id as caisse_id 
            FROM sessions_caisse s
            JOIN caisses c ON s.caisse_id = c.id
            WHERE s.id = :session_id AND s.statut = 'Fermée'
        ");
        $stmt->bindParam(':session_id', $session_id);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            $_SESSION['message'] = "La session demandée n'existe pas ou n'est pas en statut 'Fermée'.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/sessions_caisse');
            exit;
        }
        
        // Vérifier que l'utilisateur a les droits de validation pour cette caisse
        $stmt = $connexion->prepare("
            SELECT niveau 
            FROM droits_acces_finances 
            WHERE \"idUser\" = :idUser AND type = 'Caisse' 
            AND (entite_id = :caisse_id OR entite_id IS NULL)
            AND est_actif = 1
            ORDER BY entite_id DESC, niveau DESC
            LIMIT 1
        ");
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':caisse_id', $session['caisse_id']);
        $stmt->execute();
        $droit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$droit || ($droit['niveau'] !== 'Validation' && $droit['niveau'] !== 'Administration')) {
            $_SESSION['message'] = "Vous n'avez pas les droits nécessaires pour valider cette session.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $session['caisse_id']);
            exit;
        }
        
        // Valider la session
        $stmt = $connexion->prepare("
            UPDATE sessions_caisse SET
                date_validation = NOW(),
                \"idValidateur\" = :idValidateur,
                commentaire = CONCAT(COALESCE(commentaire, ''), '\n\n--- Commentaire de validation ---\n', :commentaire_validation)
            WHERE id = :session_id
        ");
        $stmt->bindParam(':session_id', $session_id);
        $stmt->bindParam(':idValidateur', $idValidateur);
        $stmt->bindParam(':commentaire_validation', $commentaire_validation);
        $stmt->execute();
        
        $_SESSION['message'] = "La session de caisse a été validée avec succès.";
        $_SESSION['messageType'] = "success";
        
        // Redirection
        header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $session['caisse_id']);
        exit;
    }
    
    else {
        $_SESSION['message'] = "Action non reconnue.";
        $_SESSION['messageType'] = "danger";
    }
    
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de l'opération: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection par défaut
if (isset($_POST['caisse_id'])) {
    header('Location: ../?view=finance/sessions_caisse&caisse_id=' . $_POST['caisse_id']);
} else {
    header('Location: ../?view=finance/sessions_caisse');
}
exit;
