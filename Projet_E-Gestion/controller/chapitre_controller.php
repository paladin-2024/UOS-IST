<?php
session_start();
require_once '../config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = Connexion::getInstance()->getPDO();
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['id'] ?? 0;
    
    try {
        switch ($action) {
            case 'validate_chapitre':
                $chapitreId = intval($_POST['chapitre_id']);
                $statut = $_POST['statut'];
                $pourcentage = intval($_POST['pourcentage']);
                $commentaire = $_POST['commentaire'] ?? '';
                $annee = intval($_POST['annee']);
                $redirect = $_POST['redirect'] ?? 'recherche/projet.taches';
                
                // Vérifier que l'utilisateur est bien directeur ou encadreur du sujet
                $queryVerif = "SELECT s.\"idDirecteur\", s.\"idEncadreur\", s.idsujets, a.\"idAgent\"
                               FROM chapitre_plan cp 
                               JOIN plan_travail pt ON cp.idplan_travail = pt.idplan_travail
                               JOIN sujets s ON pt.idsujets = s.idsujets 
                               JOIN agent a ON (s.\"idDirecteur\" = a.\"idAgent\" OR s.\"idEncadreur\" = a.\"idAgent\")
                               JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
                               WHERE cp.idchapitre_plan = ? AND u.\"idUser\" = ?";
                $stmtVerif = $pdo->prepare($queryVerif);
                $stmtVerif->execute([$chapitreId, $userId]);
                $verification = $stmtVerif->fetch(PDO::FETCH_ASSOC);
                
                if (!$verification) {
                    $_SESSION['error'] = "Vous n'êtes pas autorisé à valider ce chapitre.";
                    header("Location: ../index.php?view=$redirect&annee=$annee");
                    exit();
                }
                
                // Mettre à jour le chapitre
                $queryUpdate = "UPDATE chapitre_plan 
                                SET statut = ?, 
                                    pourcentage_avancement = ?, 
                                    commentaire_directeur = ?
                                WHERE idchapitre_plan = ?";
                $stmtUpdate = $pdo->prepare($queryUpdate);
                $stmtUpdate->execute([$statut, $pourcentage, $commentaire, $chapitreId]);
                
                // Ajouter un échange automatique pour la validation
                if (!empty($commentaire)) {
                    $typeAuteur = ($verification['idDirecteur'] == $verification['idAgent']) ? 'Directeur' : 'Encadreur';
                    $queryEchange = "INSERT INTO echange_chapitre 
                                     (idchapitre_plan, type_auteur, \"idAuteur\", commentaire, date_echange, statut_lecture) 
                                     VALUES (?, ?, ?, ?, NOW(), 'Non lu')";
                    $stmtEchange = $pdo->prepare($queryEchange);
                    $stmtEchange->execute([$chapitreId, $typeAuteur, $verification['idAgent'], $commentaire]);
                }
                
                // Créer une notification pour l'étudiant
                $queryNotif = "INSERT INTO notification_plan 
                               (destinataire_id, type_destinataire, type_notification, titre, message, idchapitre_plan, date_notification) 
                               SELECT e.idetudiant, 'Etudiant', 
                                      CASE 
                                        WHEN ? = 'Terminé' THEN 'Chapitre validé'
                                        ELSE 'Chapitre en révision'
                                      END,
                                      CONCAT('Chapitre ', cp.numero_chapitre, ': ', cp.titre_chapitre),
                                      CONCAT('Le chapitre a été ', LOWER(?), 
                                             CASE WHEN ? != '' THEN CONCAT('. Commentaire: ', ?) ELSE '' END),
                                      ?, NOW()
                               FROM chapitre_plan cp
                               JOIN plan_travail pt ON cp.idplan_travail = pt.idplan_travail
                               JOIN sujets s ON pt.idsujets = s.idsujets 
                               JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant 
                               WHERE cp.idchapitre_plan = ?";
                $stmtNotif = $pdo->prepare($queryNotif);
                $stmtNotif->execute([$statut, $statut, $commentaire, $commentaire, $chapitreId, $chapitreId]);
                
                $message = ($statut === 'Terminé') ? 'Chapitre validé avec succès.' : 'Chapitre mis en révision.';
                $_SESSION['success'] = $message;
                break;
                
            case 'add_comment_chapitre':
                $chapitreId = intval($_POST['chapitre_id']);
                $typeAuteur = $_POST['type_auteur'];
                $idAuteur = intval($_POST['id_auteur']);
                $commentaire = $_POST['commentaire'];
                $annee = intval($_POST['annee']);
                $redirect = $_POST['redirect'] ?? 'recherche/projet.taches';
                
                // Vérifier que l'utilisateur est bien autorisé
                $queryVerif = "SELECT s.\"idDirecteur\", s.\"idEncadreur\", s.idsujets
                               FROM chapitre_plan cp 
                               JOIN plan_travail pt ON cp.idplan_travail = pt.idplan_travail
                               JOIN sujets s ON pt.idsujets = s.idsujets 
                               WHERE cp.idchapitre_plan = ? AND (s.\"idDirecteur\" = ? OR s.\"idEncadreur\" = ?)";
                $stmtVerif = $pdo->prepare($queryVerif);
                $stmtVerif->execute([$chapitreId, $idAuteur, $idAuteur]);
                $verification = $stmtVerif->fetch(PDO::FETCH_ASSOC);
                
                if (!$verification) {
                    $_SESSION['error'] = "Vous n'êtes pas autorisé à commenter ce chapitre.";
                    header("Location: ../index.php?view=$redirect&annee=$annee");
                    exit();
                }
                
                // Gérer l'upload de fichier si présent
                $fichierJoint = null;
                if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/echanges_chapitres/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = time() . '_' . basename($_FILES['fichier']['name']);
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadPath)) {
                        $fichierJoint = $fileName;
                    }
                }
                
                // Insérer l'échange
                $queryEchange = "INSERT INTO echange_chapitre 
                                 (idchapitre_plan, type_auteur, \"idAuteur\", commentaire, fichier_joint, date_echange, statut_lecture) 
                                 VALUES (?, ?, ?, ?, ?, NOW(), 'Non lu')";
                $stmtEchange = $pdo->prepare($queryEchange);
                $stmtEchange->execute([$chapitreId, $typeAuteur, $idAuteur, $commentaire, $fichierJoint]);
                
                // Créer une notification pour l'étudiant
                $queryNotif = "INSERT INTO notification_plan 
                               (destinataire_id, type_destinataire, type_notification, titre, message, idchapitre_plan, date_notification) 
                               SELECT e.idetudiant, 'Etudiant', 'Commentaire ajouté',
                                      CONCAT('Nouveau commentaire sur le chapitre ', cp.numero_chapitre),
                                      CONCAT('Un nouveau commentaire a été ajouté par le ', LOWER(?)),
                                      ?, NOW()
                               FROM chapitre_plan cp
                               JOIN plan_travail pt ON cp.idplan_travail = pt.idplan_travail
                               JOIN sujets s ON pt.idsujets = s.idsujets 
                               JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant 
                               WHERE cp.idchapitre_plan = ?";
                $stmtNotif = $pdo->prepare($queryNotif);
                $stmtNotif->execute([$typeAuteur, $chapitreId, $chapitreId]);
                
                $_SESSION['success'] = 'Commentaire ajouté avec succès.';
                break;
                
            default:
                $_SESSION['error'] = "Action non reconnue.";
                break;
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de l'opération : " . $e->getMessage();
    }
    
    // Redirection
    $redirect = $_POST['redirect'] ?? 'recherche/projet.taches';
    $annee = $_POST['annee'] ?? '';
    header("Location: ../index.php?view=$redirect" . ($annee ? "&annee=$annee" : ""));
    exit();
}

// Si ce n'est pas une requête POST, rediriger vers l'accueil
header("Location: ../index.php");
exit();
?>