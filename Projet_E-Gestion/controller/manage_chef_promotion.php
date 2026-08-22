<?php
// Démarrer la session pour accéder aux variables de session
session_start();

// Inclure le fichier de connexion à la base de données
require_once dirname(__DIR__) . "/config/Connexion.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php?error=not_connected');
    exit;
}

// Vérifier si la requête est une méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?view=configuration/chef_promotion&error=invalid_request');
    exit;
}

// Récupérer l'action à effectuer
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Vérifier que l'ID de la promotion est fourni
if (!isset($_POST['promotion_id']) || empty($_POST['promotion_id']) || !is_numeric($_POST['promotion_id'])) {
    header('Location: ../index.php?view=configuration/chef_promotion&error=invalid_promotion');
    exit;
}

// Vérifier que l'ID de l'année académique est fourni
if (!isset($_POST['annee_id']) || empty($_POST['annee_id']) || !is_numeric($_POST['annee_id'])) {
    header('Location: ../index.php?view=configuration/chef_promotion&error=invalid_year');
    exit;
}

$promotionId = intval($_POST['promotion_id']);
$anneeId = intval($_POST['annee_id']);
$userId = $_SESSION['id'];
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

try {
    // Établir la connexion à la base de données
    $pdo = Connexion::getInstance()->getPDO();
    
    // Vérifier que la promotion existe et récupérer sa section
    $queryCheckPromotion = "SELECT p.*, o.section_idsection 
                           FROM promotion p
                           LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                           WHERE p.idpromotion = ?";
    $stmtCheckPromotion = $pdo->prepare($queryCheckPromotion);
    $stmtCheckPromotion->execute([$promotionId]);
    $promotion = $stmtCheckPromotion->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        header('Location: ../index.php?view=configuration/chef_promotion&error=promotion_not_found');
        exit;
    }
    
    // Vérifier les droits de l'utilisateur sur cette promotion
    $userSections = [];
    $isResponsableSection = false;
    $hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur
    
    if (!$hasFullAccess) {
        // Récupérer les sections dont l'utilisateur est responsable
        $queryUserSections = "SELECT section_idsection 
                             FROM responsable_section 
                             WHERE idUser = ? AND annee_acad_idannee_acad = ?";
        $stmtUserSections = $pdo->prepare($queryUserSections);
        $stmtUserSections->execute([$userId, $anneeId]);
        $userSections = $stmtUserSections->fetchAll(PDO::FETCH_COLUMN);
        $isResponsableSection = !empty($userSections);
        
        if ($isResponsableSection) {
            // Vérifier que la promotion appartient à une section gérée par l'utilisateur
            if (!in_array($promotion['section_idsection'], $userSections)) {
                header('Location: ../index.php?view=configuration/chef_promotion&error=access_denied');
                exit;
            }
        } else {
            header('Location: ../index.php?view=configuration/chef_promotion&error=access_denied');
            exit;
        }
    }
    
    // Traiter l'action demandée
    switch ($action) {
        case 'assign':
            // Vérifier que l'ID de l'étudiant est fourni
            if (!isset($_POST['etudiant_id']) || empty($_POST['etudiant_id']) || !is_numeric($_POST['etudiant_id'])) {
                header('Location: ../index.php?view=configuration/chef_promotion&error=invalid_student');
                exit;
            }
            
            $etudiantId = intval($_POST['etudiant_id']);
            
            // Vérifier que l'étudiant existe et est inscrit dans cette promotion pour cette année
            $queryCheckEtudiant = "SELECT e.* 
                                  FROM etudiant e
                                  WHERE e.idetudiant = ? 
                                  AND e.promotion_idpromotion = ? 
                                  AND e.annee_acad_idannee_acad = ?
                                  AND e.est_actif = 1";
            $stmtCheckEtudiant = $pdo->prepare($queryCheckEtudiant);
            $stmtCheckEtudiant->execute([$etudiantId, $promotionId, $anneeId]);
            $etudiant = $stmtCheckEtudiant->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiant) {
                header('Location: ../index.php?view=configuration/chef_promotion&error=student_not_in_promotion');
                exit;
            }
            
            // Vérifier si l'étudiant est déjà chef d'une autre promotion cette année
            $queryCheckExistingChef = "SELECT p.designationPromotion 
                                      FROM chef_promotion cp
                                      INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                                      WHERE cp.idetudiant = ? 
                                      AND cp.annee_acad_idannee_acad = ? 
                                      AND cp.promotion_idpromotion != ?
                                      AND cp.est_actif = 1";
            $stmtCheckExistingChef = $pdo->prepare($queryCheckExistingChef);
            $stmtCheckExistingChef->execute([$etudiantId, $anneeId, $promotionId]);
            $existingPromotion = $stmtCheckExistingChef->fetchColumn();
            
            if ($existingPromotion) {
                header('Location: ../index.php?view=configuration/chef_promotion&error=student_already_chef&promotion=' . urlencode($existingPromotion));
                exit;
            }
            
            // Commencer une transaction
            $pdo->beginTransaction();
            
            try {
                // Vérifier s'il y a déjà un chef actif pour cette promotion cette année
                $queryCheckExistingActive = "SELECT id_chef FROM chef_promotion 
                                            WHERE promotion_idpromotion = ? 
                                            AND annee_acad_idannee_acad = ? 
                                            AND est_actif = 1";
                $stmtCheckExistingActive = $pdo->prepare($queryCheckExistingActive);
                $stmtCheckExistingActive->execute([$promotionId, $anneeId]);
                $existingChef = $stmtCheckExistingActive->fetch(PDO::FETCH_ASSOC);
                
                if ($existingChef) {
                    // Désactiver l'ancien chef
                    $queryRemoveOld = "UPDATE chef_promotion 
                                      SET est_actif = 0 
                                      WHERE id_chef = ?";
                    $stmtRemoveOld = $pdo->prepare($queryRemoveOld);
                    $stmtRemoveOld->execute([$existingChef['id_chef']]);
                }
                
                // Insérer le nouveau chef avec est_actif explicitement défini à 1
                $queryInsert = "INSERT INTO chef_promotion 
                               (promotion_idpromotion, idetudiant, annee_acad_idannee_acad, date_nomination, est_actif, idUser) 
                               VALUES (?, ?, ?, CURDATE(), 1, ?)";
                $stmtInsert = $pdo->prepare($queryInsert);
                $stmtInsert->execute([$promotionId, $etudiantId, $anneeId, $userId]);
                
                // Valider la transaction
                $pdo->commit();
                
                // Rediriger avec un message de succès
                header('Location: ../index.php?view=configuration/chef_promotion&success=chef_assigned');
                
            } catch (Exception $e) {
                // Annuler la transaction en cas d'erreur
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case 'remove':
            // Vérifier qu'il y a bien un chef à retirer
            $queryCheckChef = "SELECT cp.id_chef, cp.idetudiant, cp.promotion_idpromotion, cp.annee_acad_idannee_acad, e.noms 
                              FROM chef_promotion cp
                              INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant
                              WHERE cp.promotion_idpromotion = ? AND cp.annee_acad_idannee_acad = ? AND cp.est_actif = 1";
            $stmtCheckChef = $pdo->prepare($queryCheckChef);
            $stmtCheckChef->execute([$promotionId, $anneeId]);
            $currentChef = $stmtCheckChef->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentChef) {
                header('Location: ../index.php?view=configuration/chef_promotion&error=no_chef_to_remove');
                exit;
            }
            
            // Commencer une transaction
            $pdo->beginTransaction();
            
            try {
                // Méthode alternative pour éviter le problème de contrainte d'unicité
                // Supprimer l'enregistrement actif au lieu de le désactiver
                $queryRemove = "DELETE FROM chef_promotion WHERE id_chef = ?";
                $stmtRemove = $pdo->prepare($queryRemove);
                $stmtRemove->execute([$currentChef['id_chef']]);
                
                // Optionnel : créer un enregistrement d'historique inactif
                // (seulement si on veut garder une trace)
                if (!empty($commentaire)) {
                    try {
                        $queryHistory = "INSERT INTO chef_promotion 
                                        (promotion_idpromotion, idetudiant, annee_acad_idannee_acad, date_nomination, est_actif, idUser) 
                                        VALUES (?, ?, ?, CURDATE(), 0, ?)";
                        $stmtHistory = $pdo->prepare($queryHistory);
                        $stmtHistory->execute([
                            $currentChef['promotion_idpromotion'],
                            $currentChef['idetudiant'],
                            $currentChef['annee_acad_idannee_acad'],
                            $userId
                        ]);
                    } catch (Exception $historyError) {
                        // Si l'historique échoue, on continue quand même
                        error_log("Erreur lors de la création de l'historique: " . $historyError->getMessage());
                    }
                }
                
                // Valider la transaction
                $pdo->commit();
                
                // Rediriger avec un message de succès
                header('Location: ../index.php?view=configuration/chef_promotion&success=chef_removed');
                
            } catch (Exception $e) {
                // Annuler la transaction en cas d'erreur
                $pdo->rollback();
                
                // Si c'est encore un problème de contrainte, essayer une approche différente
                if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), '1062') !== false) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Modifier la date pour rendre l'enregistrement unique
                        $queryRemoveAlt = "UPDATE chef_promotion 
                                          SET est_actif = 0, date_creation = NOW() 
                                          WHERE id_chef = ?";
                        $stmtRemoveAlt = $pdo->prepare($queryRemoveAlt);
                        $stmtRemoveAlt->execute([$currentChef['id_chef']]);
                        
                        $pdo->commit();
                        header('Location: ../index.php?view=configuration/chef_promotion&success=chef_removed');
                        
                    } catch (Exception $e2) {
                        $pdo->rollback();
                        error_log("Erreur alternative dans manage_chef_promotion.php: " . $e2->getMessage());
                        header('Location: ../index.php?view=configuration/chef_promotion&error=database_error&message=' . urlencode("Problème de contrainte de base de données. Veuillez contacter l'administrateur."));
                    }
                } else {
                    throw $e;
                }
            }
            break;
            
        default:
            // Action non reconnue
            header('Location: ../index.php?view=configuration/chef_promotion&error=invalid_action');
            break;
    }
    
} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    error_log("Erreur dans manage_chef_promotion.php: " . $e->getMessage());
    header('Location: ../index.php?view=configuration/chef_promotion&error=database_error&message=' . urlencode($e->getMessage()));
} catch (Exception $e) {
    // Gérer les autres erreurs
    error_log("Erreur générale dans manage_chef_promotion.php: " . $e->getMessage());
    header('Location: ../index.php?view=configuration/chef_promotion&error=general_error&message=' . urlencode($e->getMessage()));
}
?>