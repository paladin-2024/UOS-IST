<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier que l'utilisateur est connecté et a les droits
if (!isset($_SESSION['id']) || !isset($_SESSION['idRole'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer les paramètres
$action = isset($_POST['action']) ? $_POST['action'] : '';
$reformulationId = isset($_POST['reformulation_id']) ? intval($_POST['reformulation_id']) : 0;

if (!$reformulationId) {
    echo json_encode(['success' => false, 'message' => 'ID de reformulation manquant']);
    exit;
}

// Vérifier que la reformulation existe et est en attente
$queryCheck = "SELECT sr.*, s.* 
               FROM sujet_reformulations sr
               JOIN sujets s ON sr.idsujets = s.idsujets
               WHERE sr.id_reformulation = :id AND sr.statut_reformulation = 'En attente'";
$stmtCheck = $connexion->prepare($queryCheck);
$stmtCheck->execute(['id' => $reformulationId]);
$reformulation = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$reformulation) {
    echo json_encode(['success' => false, 'message' => 'Reformulation introuvable ou déjà traitée']);
    exit;
}

try {
    $connexion->beginTransaction();
    
    if ($action === 'approve') {
        // Approuver la reformulation
        
        // 1. Mettre à jour le sujet avec les nouvelles informations
        $updateSujetQuery = "UPDATE sujets SET 
                            intitule = :intitule,
                            statut_validation = 'Validé'";
        
        $params = ['intitule' => $reformulation['intitule_propose']];
        
        // Ajouter les champs optionnels s'ils sont fournis
        if (!empty($reformulation['idSpecialisation_propose'])) {
            $updateSujetQuery .= ", idSpecialisation = :specialisation";
            $params['specialisation'] = $reformulation['idSpecialisation_propose'];
        }
        
        if (!empty($reformulation['idDirecteur_propose'])) {
            $updateSujetQuery .= ", idDirecteur = :directeur";
            $params['directeur'] = $reformulation['idDirecteur_propose'];
        }
        
        if (!empty($reformulation['idEncadreur_propose'])) {
            $updateSujetQuery .= ", idEncadreur = :encadreur";
            $params['encadreur'] = $reformulation['idEncadreur_propose'];
        }
        
        $updateSujetQuery .= " WHERE idsujets = :sujet_id";
        $params['sujet_id'] = $reformulation['idsujets'];
        
        $stmtUpdateSujet = $connexion->prepare($updateSujetQuery);
        $stmtUpdateSujet->execute($params);
        
        // 2. Mettre à jour le statut de la reformulation
        $updateReformulationQuery = "UPDATE sujet_reformulations SET 
                                    statut_reformulation = 'Acceptée',
                                    date_traitement = NOW(),
                                    traite_par = :user_id,
                                    commentaire_reponse = 'Votre proposition de reformulation a été acceptée.'
                                    WHERE id_reformulation = :id";
        
        $stmtUpdateReformulation = $connexion->prepare($updateReformulationQuery);
        $stmtUpdateReformulation->execute([
            'id' => $reformulationId,
            'user_id' => $_SESSION['id']
        ]);
        
        // 3. Marquer toutes les autres reformulations en attente pour ce sujet comme refusées
        $rejectOthersQuery = "UPDATE sujet_reformulations SET 
                             statut_reformulation = 'Refusée',
                             date_traitement = NOW(),
                             traite_par = :user_id,
                             commentaire_reponse = 'Une autre reformulation a été acceptée pour ce sujet.'
                             WHERE idsujets = :sujet_id 
                             AND id_reformulation != :current_id 
                             AND statut_reformulation = 'En attente'";
        
        $stmtRejectOthers = $connexion->prepare($rejectOthersQuery);
        $stmtRejectOthers->execute([
            'sujet_id' => $reformulation['idsujets'],
            'current_id' => $reformulationId,
            'user_id' => $_SESSION['id']
        ]);
        
        $connexion->commit();
        echo json_encode(['success' => true, 'message' => 'Reformulation approuvée avec succès']);
        
    } elseif ($action === 'reject') {
        // Refuser la reformulation
        $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : 'Votre proposition de reformulation a été refusée.';
        
        $updateReformulationQuery = "UPDATE sujet_reformulations SET 
                                    statut_reformulation = 'Refusée',
                                    date_traitement = NOW(),
                                    traite_par = :user_id,
                                    commentaire_reponse = :commentaire
                                    WHERE id_reformulation = :id";
        
        $stmtUpdateReformulation = $connexion->prepare($updateReformulationQuery);
        $stmtUpdateReformulation->execute([
            'id' => $reformulationId,
            'user_id' => $_SESSION['id'],
            'commentaire' => $commentaire
        ]);
        
        $connexion->commit();
        echo json_encode(['success' => true, 'message' => 'Reformulation refusée']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
    
} catch (Exception $e) {
    $connexion->rollBack();
    error_log("Erreur traitement reformulation: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors du traitement: ' . $e->getMessage()]);
}
?>