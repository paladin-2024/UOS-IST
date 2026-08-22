<?php
session_start();
require_once '../config/Connexion.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer les paramètres
$promotionId = isset($_GET['promotion_id']) ? intval($_GET['promotion_id']) : 0;
$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if ($promotionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de promotion invalide']);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer l'historique des chefs de promotion
    $query = "SELECT 
                cp.id_chef,
                cp.date_assignation,
                cp.date_retrait,
                cp.commentaire,
                cp.est_actif,
                e.noms as chef_nom,
                e.matricule as chef_matricule,
                e.idetudiant as chef_id,
                p.\"designationPromotion\",
                aa.designation as annee_designation,
                u_assign.nom as assigneur_nom,
                u_assign.prenom as assigneur_prenom,
                u_retrait.nom as retireur_nom,
                u_retrait.prenom as retireur_prenom
              FROM chef_promotion cp
              LEFT JOIN etudiant e ON cp.idetudiant = e.idetudiant
              LEFT JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
              LEFT JOIN annee_acad aa ON cp.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN user u_assign ON cp.user_assigneur = u_assign.\"idUser\"
              LEFT JOIN user u_retrait ON cp.user_retireur = u_retrait.\"idUser\"
              WHERE cp.promotion_idpromotion = :promotionId";
    
    $params = [':promotionId' => $promotionId];
    
    // Si une année spécifique est demandée, filtrer par année
    if ($anneeId > 0) {
        $query .= " AND cp.annee_acad_idannee_acad = :anneeId";
        $params[':anneeId'] = $anneeId;
    }
    
    $query .= " ORDER BY cp.date_assignation DESC, cp.id_chef DESC";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données pour l'affichage
    $historiqueFormate = [];
    foreach ($historique as $entry) {
        $periode = '';
        if ($entry['date_assignation']) {
            $dateAssign = new DateTime($entry['date_assignation']);
            $periode = 'Du ' . $dateAssign->format('d/m/Y');
            
            if ($entry['date_retrait']) {
                $dateRetrait = new DateTime($entry['date_retrait']);
                $periode .= ' au ' . $dateRetrait->format('d/m/Y');
            } else if ($entry['est_actif']) {
                $periode .= ' (Actuel)';
            }
        }
        
        $assigneur = '';
        if ($entry['assigneur_nom'] && $entry['assigneur_prenom']) {
            $assigneur = $entry['assigneur_prenom'] . ' ' . $entry['assigneur_nom'];
        }
        
        $retireur = '';
        if ($entry['retireur_nom'] && $entry['retireur_prenom']) {
            $retireur = $entry['retireur_prenom'] . ' ' . $entry['retireur_nom'];
        }
        
        $historiqueFormate[] = [
            'id' => $entry['id_chef'],
            'chef_nom' => $entry['chef_nom'],
            'chef_matricule' => $entry['chef_matricule'],
            'periode' => $periode,
            'statut' => $entry['est_actif'] ? 'Actif' : 'Inactif',
            'assigneur' => $assigneur,
            'retireur' => $retireur,
            'commentaire' => $entry['commentaire'],
            'annee' => $entry['annee_designation'],
            'date_assignation' => $entry['date_assignation'],
            'date_retrait' => $entry['date_retrait']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'historique' => $historiqueFormate,
        'promotion' => $historique[0]['designationPromotion'] ?? 'Promotion inconnue'
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur dans get_chef_promotion_history: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données']);
} catch (Exception $e) {
    error_log("Erreur générale dans get_chef_promotion_history: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>