<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/PlanTravail.php';

// Gestion des requêtes GET (pour AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_chapitre':
                $chapitreId = intval($_GET['id'] ?? 0);
                
                if ($chapitreId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'ID du chapitre invalide']);
                    exit;
                }
                
                $planModel = new PlanTravail();
                $chapitre = $planModel->getChapitreById($chapitreId);
                
                if (!$chapitre) {
                    echo json_encode(['success' => false, 'error' => 'Chapitre non trouvé']);
                    exit;
                }
                
                // Récupérer les échanges du chapitre
                $pdo = Connexion::getInstance()->getPDO();
                $queryEchanges = "SELECT ec.*, 
                                         CASE 
                                             WHEN ec.type_auteur = 'Etudiant' THEN e.noms
                                             WHEN ec.type_auteur = 'Directeur' THEN dir.noms
                                             WHEN ec.type_auteur = 'Encadreur' THEN enc.noms
                                             ELSE 'Utilisateur inconnu'
                                         END as auteur_nom
                                  FROM echange_chapitre ec
                                  LEFT JOIN sujets s ON (SELECT idsujets FROM plan_travail WHERE idplan_travail = 
                                                         (SELECT idplan_travail FROM chapitre_plan WHERE idchapitre_plan = ec.idchapitre_plan)) = s.idsujets
                                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant AND ec.type_auteur = 'Etudiant'
                                  LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent AND ec.type_auteur = 'Directeur'
                                  LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent AND ec.type_auteur = 'Encadreur'
                                  WHERE ec.idchapitre_plan = ?
                                  ORDER BY ec.date_echange DESC";
                
                $stmtEchanges = $pdo->prepare($queryEchanges);
                $stmtEchanges->execute([$chapitreId]);
                $echanges = $stmtEchanges->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true, 
                    'chapitre' => $chapitre,
                    'echanges' => $echanges
                ]);
                exit;
                
            case 'get_historique':
                $planId = intval($_GET['plan_id'] ?? 0);
                
                if ($planId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'ID du plan invalide']);
                    exit;
                }
                
                $planModel = new PlanTravail();
                $historique = $planModel->getHistoriquePlan($planId);
                
                echo json_encode([
                    'success' => true, 
                    'historique' => $historique
                ]);
                exit;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
                exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
        exit;
    }
}

// Gestion des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = Connexion::getInstance()->getPDO();
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['id'] ?? 0;
    
    try {
        switch ($action) {
            case 'creer_plan':
                $planModel = new PlanTravail();
                
                $donnees = [
                    'sujet_id' => intval($_POST['sujet_id']),
                    'titre_plan' => $_POST['titre_plan'],
                    'introduction' => $_POST['introduction'] ?? null,
                    'problematique' => $_POST['problematique'] ?? null,
                    'objectifs' => $_POST['objectifs'] ?? null,
                    'methodologie' => $_POST['methodologie'] ?? null,
                    'user_id' => $userId
                ];
                
                // Traiter les chapitres s'ils existent
                if (isset($_POST['chapitres']) && is_array($_POST['chapitres'])) {
                    $donnees['chapitres'] = $_POST['chapitres'];
                }
                
                $planId = $planModel->creerPlan($donnees);
                
                $_SESSION['plan_message'] = 'Plan de travail créé avec succès et soumis pour validation.';
                $_SESSION['plan_message_type'] = 'success';
                break;
                
            case 'modifier_plan':
                $planModel = new PlanTravail();
                $planId = intval($_POST['plan_id']);
                
                $donnees = [
                    'titre_plan' => $_POST['titre_plan'],
                    'introduction' => $_POST['introduction'] ?? null,
                    'problematique' => $_POST['problematique'] ?? null,
                    'objectifs' => $_POST['objectifs'] ?? null,
                    'methodologie' => $_POST['methodologie'] ?? null,
                    'user_id' => $userId
                ];
                
                $planModel->modifierPlan($planId, $donnees);
                
                $_SESSION['plan_message'] = 'Plan de travail modifié avec succès et remis en attente de validation.';
                $_SESSION['plan_message_type'] = 'success';
                break;
                
            case 'validate_plan':
                $planId = intval($_POST['plan_id']);
                $statut = $_POST['statut'];
                $commentaire = $_POST['commentaire'] ?? '';
                $annee = intval($_POST['annee']);
                $redirect = $_POST['redirect'] ?? 'recherche/projet.taches';
                
                // Vérifier que l'utilisateur est bien directeur du sujet
                $queryVerif = "SELECT s.idDirecteur, s.idsujets 
                               FROM plan_travail pt 
                               JOIN sujets s ON pt.idsujets = s.idsujets 
                               JOIN agent a ON s.idDirecteur = a.idAgent 
                               JOIN t_users u ON a.idAgent = u.idAgent 
                               WHERE pt.idplan_travail = ? AND u.idUser = ?";
                $stmtVerif = $pdo->prepare($queryVerif);
                $stmtVerif->execute([$planId, $userId]);
                $verification = $stmtVerif->fetch(PDO::FETCH_ASSOC);
                
                if (!$verification) {
                    $_SESSION['error'] = "Vous n'êtes pas autorisé à valider ce plan de travail.";
                    header("Location: ../index.php?view=$redirect&annee=$annee");
                    exit();
                }
                
                // Mettre à jour le plan de travail
                $queryUpdate = "UPDATE plan_travail 
                                SET statut_validation = ?, 
                                    commentaire_directeur = ?, 
                                    date_validation = NOW(), 
                                    idValidateur = ? 
                                WHERE idplan_travail = ?";
                $stmtUpdate = $pdo->prepare($queryUpdate);
                $stmtUpdate->execute([$statut, $commentaire, $verification['idDirecteur'], $planId]);
                
                // Enregistrer dans l'historique
                $queryHistory = "INSERT INTO plan_validation_history 
                                 (idplan_travail, statut, commentaire, date_action, idUser, version_plan) 
                                 SELECT idplan_travail, ?, ?, NOW(), ?, version 
                                 FROM plan_travail 
                                 WHERE idplan_travail = ?";
                $stmtHistory = $pdo->prepare($queryHistory);
                $stmtHistory->execute([$statut, $commentaire, $verification['idDirecteur'], $planId]);
                
                // Créer une notification pour l'étudiant
                $queryNotif = "INSERT INTO notification_plan 
                               (destinataire_id, type_destinataire, type_notification, titre, message, idplan_travail, date_notification) 
                               SELECT e.idetudiant, 'Etudiant', 
                                      CASE 
                                        WHEN ? = 'Validé' THEN 'Plan validé'
                                        WHEN ? = 'Rejeté' THEN 'Plan rejeté'
                                        ELSE 'Plan à modifier'
                                      END,
                                      CONCAT('Plan de travail ', ?),
                                      CONCAT('Votre plan de travail a été ', LOWER(?), 
                                             CASE WHEN ? != '' THEN CONCAT('. Commentaire: ', ?) ELSE '' END),
                                      ?, NOW()
                               FROM sujets s 
                               JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant 
                               WHERE s.idsujets = ?";
                $stmtNotif = $pdo->prepare($queryNotif);
                $stmtNotif->execute([$statut, $statut, $statut, $statut, $commentaire, $commentaire, $planId, $verification['idsujets']]);
                
                $message = '';
                switch ($statut) {
                    case 'Validé':
                        $message = 'Plan de travail validé avec succès.';
                        break;
                    case 'Rejeté':
                        $message = 'Plan de travail rejeté.';
                        break;
                    case 'Modifié':
                        $message = 'Demande de modification envoyée à l\'étudiant.';
                        break;
                }
                
                $_SESSION['success'] = $message;
                break;
                
            default:
                $_SESSION['error'] = "Action non reconnue.";
                break;
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de l'opération : " . $e->getMessage();
    }
    
    // Redirection
    if (in_array($action, ['creer_plan', 'modifier_plan'])) {
        // Pour les actions de plan, rediriger vers le portail étudiant
        header("Location: ../portail/student");
    } else {
        // Pour les autres actions, utiliser la redirection par défaut
        $redirect = $_POST['redirect'] ?? 'recherche/projet.taches';
        $annee = $_POST['annee'] ?? '';
        header("Location: ../index.php?view=$redirect" . ($annee ? "&annee=$annee" : ""));
    }
    exit();
}

// Si ce n'est pas une requête POST ou GET, rediriger vers l'accueil
header("Location: ../index.php");
exit();
?>