<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    // Rediriger vers la page de connexion
    header('Location: ../index.php?view=login');
    exit();
}

// Récupérer la connexion à la base de données
$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'ID de l'utilisateur connecté
$userId = $_SESSION['id'];

// Récupérer l'action à effectuer
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Traiter l'action en fonction de sa valeur
switch ($action) {
    case 'add_comment':
        addComment($pdo, $userId);
        break;
    case 'update_comment':
        updateComment($pdo, $userId);
        break;
    case 'delete_comment':
        deleteComment($pdo, $userId);
        break;
    default:
        $_SESSION['error'] = "Action non reconnue.";
        redirectBack();
        break;
}

/**
 * Ajoute un nouveau commentaire à une tâche
 */
function addComment($pdo, $userId) {
    // Récupérer les données du formulaire
    $tacheId = isset($_POST['tache_id']) ? intval($_POST['tache_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $typeAuteur = isset($_POST['type_auteur']) ? $_POST['type_auteur'] : '';
    $idAuteur = isset($_POST['id_auteur']) ? intval($_POST['id_auteur']) : 0;
    $annee = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'recherche/projet.taches';

    // Vérifier les données obligatoires
    if (empty($tacheId) || empty($commentaire) || empty($typeAuteur) || empty($idAuteur)) {
        $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que la tâche existe
    $query = "SELECT t.*, s.idsujets, s.\"idDirecteur\", s.\"idEncadreur\", s.etudiant_idetudiant 
              FROM taches t
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE t.idtaches = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tacheId]);
    $tache = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tache) {
        $_SESSION['error'] = "Tâche introuvable.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'utilisateur a le droit d'ajouter un commentaire sur cette tâche
    $hasRight = false;
    $idEtudiant = null;
    $idEnseignant = null;

    // Vérifier si l'utilisateur est un enseignant (directeur ou encadreur)
    if ($typeAuteur == 'Directeur' || $typeAuteur == 'Encadreur') {
        $query = "SELECT a.\"idAgent\" 
                  FROM agent a 
                  INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
                  WHERE u.\"idUser\" = ? AND a.type_agent = 'Enseignant'";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $idEnseignant = $stmt->fetchColumn();

        if ($idEnseignant) {
            if (($typeAuteur == 'Directeur' && $tache['idDirecteur'] == $idEnseignant) || 
                ($typeAuteur == 'Encadreur' && $tache['idEncadreur'] == $idEnseignant)) {
                $hasRight = true;
            }
        }
    } 
    // Vérifier si l'utilisateur est l'étudiant concerné
    else if ($typeAuteur == 'Etudiant') {
        $query = "SELECT e.idetudiant 
                  FROM etudiant e 
                  INNER JOIN t_users u ON e.\"idUser\" = u.\"idUser\" 
                  WHERE u.\"idUser\" = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $idEtudiant = $stmt->fetchColumn();

        if ($idEtudiant && $tache['etudiant_idetudiant'] == $idEtudiant) {
            $hasRight = true;
        }
    }

    if (!$hasRight) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à ajouter des commentaires à cette tâche.";
        redirectBack($annee, $redirect);
        return;
    }

    // Gérer le téléchargement du fichier
    $fichierJoint = '';
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
        $filename = $_FILES['fichier']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($ext), $allowed) && $_FILES['fichier']['size'] <= 10000000) { // 10MB max
            $uploadDir = dirname(__DIR__) . '/uploads/echanges/';
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = 'echange_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $destination)) {
                $fichierJoint = $newFilename;
            } else {
                $_SESSION['error'] = "Erreur lors du téléchargement du fichier.";
                redirectBack($annee, $redirect);
                return;
            }
        } else {
            $_SESSION['error'] = "Format de fichier non autorisé ou fichier trop volumineux.";
            redirectBack($annee, $redirect);
            return;
        }
    }

    try {
        // Insérer le commentaire dans la base de données
        $query = "INSERT INTO echanges_taches (\"dateEchange\", commentaire, \"fichierJoint\", taches_idtaches, type_auteur, \"idAuteur\") 
                  VALUES (NOW(), ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$commentaire, $fichierJoint, $tacheId, $typeAuteur, $idAuteur]);

        if ($result) {
            $_SESSION['success'] = "Votre commentaire a été ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout du commentaire.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    redirectBack($annee, $redirect, $tache['idsujets']);
}

/**
 * Met à jour un commentaire existant
 */
function updateComment($pdo, $userId) {
    // Récupérer les données du formulaire
    $echangeId = isset($_POST['echange_id']) ? intval($_POST['echange_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $annee = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'recherche/projet.taches';

    // Vérifier les données obligatoires
    if (empty($echangeId) || empty($commentaire)) {
        $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'échange existe et appartient à l'utilisateur
    $query = "SELECT e.*, t.sujets_idsujets 
              FROM echanges_taches e
              INNER JOIN taches t ON e.taches_idtaches = t.idtaches
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE e.idechange = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$echangeId]);
    $echange = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$echange) {
        $_SESSION['error'] = "Commentaire introuvable.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'utilisateur est l'auteur du commentaire
    $hasRight = false;

    if ($echange['type_auteur'] == 'Directeur' || $echange['type_auteur'] == 'Encadreur') {
        $query = "SELECT a.\"idAgent\" 
                  FROM agent a 
                  INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
                  WHERE u.\"idUser\" = ? AND a.\"idAgent\" = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $echange['idAuteur']]);
        if ($stmt->fetchColumn()) {
            $hasRight = true;
        }
    } else if ($echange['type_auteur'] == 'Etudiant') {
        $query = "SELECT e.idetudiant 
                  FROM etudiant e 
                  INNER JOIN t_users u ON e.\"idUser\" = u.\"idUser\" 
                  WHERE u.\"idUser\" = ? AND e.idetudiant = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $echange['idAuteur']]);
        if ($stmt->fetchColumn()) {
            $hasRight = true;
        }
    }

    if (!$hasRight) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à modifier ce commentaire.";
        redirectBack($annee, $redirect);
        return;
    }

    // Gérer le téléchargement du fichier
    $fichierJoint = null;
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
        $filename = $_FILES['fichier']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($ext), $allowed) && $_FILES['fichier']['size'] <= 10000000) { // 10MB max
            $uploadDir = dirname(__DIR__) . '/uploads/echanges/';
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = 'echange_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $destination)) {
                $fichierJoint = $newFilename;
                
                // Supprimer l'ancien fichier si nécessaire
                if ($echange['fichierJoint'] && file_exists($uploadDir . $echange['fichierJoint'])) {
                    unlink($uploadDir . $echange['fichierJoint']);
                }
            } else {
                $_SESSION['error'] = "Erreur lors du téléchargement du fichier.";
                redirectBack($annee, $redirect);
                return;
            }
        } else {
            $_SESSION['error'] = "Format de fichier non autorisé ou fichier trop volumineux.";
            redirectBack($annee, $redirect);
            return;
        }
    }

    try {
        // Mettre à jour le commentaire
        if ($fichierJoint !== null) {
            $query = "UPDATE echanges_taches SET commentaire = ?, \"fichierJoint\" = ?, \"dateEchange\" = NOW() WHERE idechange = ?";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$commentaire, $fichierJoint, $echangeId]);
        } else {
            $query = "UPDATE echanges_taches SET commentaire = ?, \"dateEchange\" = NOW() WHERE idechange = ?";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$commentaire, $echangeId]);
        }

        if ($result) {
            $_SESSION['success'] = "Votre commentaire a été mis à jour avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour du commentaire.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    redirectBack($annee, $redirect, $echange['sujets_idsujets']);
}

/**
 * Supprime un commentaire
 */
function deleteComment($pdo, $userId) {
    // Récupérer les données du formulaire
        $echangeId = isset($_POST['echange_id']) ? intval($_POST['echange_id']) : 0;
    $annee = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'recherche/projet.taches';

    // Vérifier que l'ID est valide
    if (empty($echangeId)) {
        $_SESSION['error'] = "ID de commentaire invalide.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'échange existe et appartient à l'utilisateur
    $query = "SELECT e.*, t.sujets_idsujets 
              FROM echanges_taches e
              INNER JOIN taches t ON e.taches_idtaches = t.idtaches
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE e.idechange = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$echangeId]);
    $echange = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$echange) {
        $_SESSION['error'] = "Commentaire introuvable.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'utilisateur est l'auteur du commentaire ou le directeur du sujet
    $hasRight = false;

    // Si l'utilisateur est l'auteur du commentaire
    if ($echange['type_auteur'] == 'Directeur' || $echange['type_auteur'] == 'Encadreur') {
        $query = "SELECT a.\"idAgent\" 
                  FROM agent a 
                  INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
                  WHERE u.\"idUser\" = ? AND a.\"idAgent\" = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $echange['idAuteur']]);
        if ($stmt->fetchColumn()) {
            $hasRight = true;
        }
    } else if ($echange['type_auteur'] == 'Etudiant') {
        $query = "SELECT e.idetudiant 
                  FROM etudiant e 
                  INNER JOIN t_users u ON e.\"idUser\" = u.\"idUser\" 
                  WHERE u.\"idUser\" = ? AND e.idetudiant = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $echange['idAuteur']]);
        if ($stmt->fetchColumn()) {
            $hasRight = true;
        }
    }

    // Si l'utilisateur est le directeur du sujet (peut supprimer tous les commentaires)
    if (!$hasRight) {
        $query = "SELECT a.\"idAgent\" 
                  FROM agent a 
                  INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
                  INNER JOIN sujets s ON s.\"idDirecteur\" = a.\"idAgent\"
                  INNER JOIN taches t ON t.sujets_idsujets = s.idsujets
                  WHERE u.\"idUser\" = ? AND t.idtaches = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $echange['taches_idtaches']]);
        if ($stmt->fetchColumn()) {
            $hasRight = true;
        }
    }

    if (!$hasRight) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à supprimer ce commentaire.";
        redirectBack($annee, $redirect);
        return;
    }

    try {
        // Récupérer le nom du fichier joint pour le supprimer
        $fichierJoint = $echange['fichierJoint'];

        // Supprimer le commentaire
        $query = "DELETE FROM echanges_taches WHERE idechange = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$echangeId]);

        if ($result) {
            // Supprimer le fichier physique si nécessaire
            if ($fichierJoint && file_exists(dirname(__DIR__) . '/uploads/echanges/' . $fichierJoint)) {
                unlink(dirname(__DIR__) . '/uploads/echanges/' . $fichierJoint);
            }
            $_SESSION['success'] = "Le commentaire a été supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du commentaire.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    redirectBack($annee, $redirect, $echange['sujets_idsujets']);
}

/**
 * Redirige l'utilisateur vers la page précédente ou spécifiée
 */
function redirectBack($annee = 0, $redirect = 'recherche/projet.taches', $sujetId = null) {
    $url = "../index.php?view=" . $redirect;
    
    // Ajouter l'année si disponible
    if ($annee > 0) {
        $url .= "&annee=" . $annee;
    }
    
    // Ajouter l'ID du sujet si disponible (pour l'ancre)
    if ($sujetId) {
        $url .= "#" . $sujetId;
    }
    
    header("Location: " . $url);
    exit();
}
?>
