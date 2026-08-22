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

// Récupérer l'ID de l'agent associé à l'utilisateur connecté
$userId = $_SESSION['id'];
$query = "SELECT a.\"idAgent\" FROM agent a 
          INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
          WHERE u.\"idUser\" = ? AND a.type_agent = 'Enseignant'";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$idAgent = $stmt->fetchColumn();

if (!$idAgent) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour effectuer cette action.";
    header('Location: ../index.php');
    exit();
}

// Récupérer l'action à effectuer
$action = $_POST['action'] ?? '';

// Traiter l'action en fonction de sa valeur
switch ($action) {
    case 'add_task':
        addTask($pdo, $idAgent);
        break;
    case 'validate_task':
        validateTask($pdo, $idAgent);
        break;
    case 'update_task':
        updateTask($pdo, $idAgent);
        break;
    case 'delete_task':
        deleteTask($pdo, $idAgent);
        break;
    default:
        $_SESSION['error'] = "Action non reconnue.";
        redirectBack();
        break;
}

/**
 * Ajoute une nouvelle tâche
 */
function addTask($pdo, $idAgent) {
    // Récupérer les données du formulaire
    $sujetId = isset($_POST['sujet_id']) ? intval($_POST['sujet_id']) : 0;
    $dateTache = isset($_POST['date_tache']) ? $_POST['date_tache'] : date('Y-m-d');
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $pourcentage = isset($_POST['pourcentage']) ? intval($_POST['pourcentage']) : 0;
    $annee = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'recherche/projet.taches';

    // Vérifier les données obligatoires
    if (empty($sujetId) || empty($description)) {
        $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'utilisateur est bien directeur du sujet
    $query = "SELECT * FROM sujets WHERE idsujets = ? AND \"idDirecteur\" = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sujetId, $idAgent]);
    $sujet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sujet) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à ajouter des tâches pour ce sujet.";
        redirectBack($annee, $redirect);
        return;
    }

    // Gérer le téléchargement du fichier
    $fichierTache = '';
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $filename = $_FILES['fichier']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($ext), $allowed) && $_FILES['fichier']['size'] <= 10000000) { // 10MB max
            $uploadDir = dirname(__DIR__) . '/uploads/taches/';
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = 'tache_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $destination)) {
                $fichierTache = $newFilename;
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
        // Insérer la tâche dans la base de données
        $query = "INSERT INTO taches (\"dateTache\", description, \"fichierTache\", validation, 
                  pourcentage_avancement, sujets_idsujets, \"idUser\") 
                  VALUES (?, ?, ?, 'En attente', ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$dateTache, $description, $fichierTache, $pourcentage, $sujetId, $_SESSION['id']]);

        if ($result) {
            $_SESSION['success'] = "La tâche a été ajoutée avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout de la tâche.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    redirectBack($annee, $redirect, $sujetId);
}

/**
 * Valide ou rejette une tâche
 */
function validateTask($pdo, $idAgent) {
    // Récupérer les données du formulaire
    $tacheId = isset($_POST['tache_id']) ? intval($_POST['tache_id']) : 0;
    $validation = isset($_POST['validation']) ? $_POST['validation'] : '';
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $pourcentage = isset($_POST['pourcentage']) ? intval($_POST['pourcentage']) : 0;
    $annee = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'recherche/projet.taches';

    // Vérifier que l'ID de la tâche est valide
    if (empty($tacheId) || empty($validation)) {
        $_SESSION['error'] = "Données requises manquantes.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'utilisateur a les droits sur cette tâche (directeur ou encadreur du sujet)
    $query = "SELECT s.idsujets FROM taches t
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE t.idtaches = ? AND (s.\"idDirecteur\" = ? OR s.\"idEncadreur\" = ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tacheId, $idAgent, $idAgent]);
    $sujetId = $stmt->fetchColumn();

    if (!$sujetId) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à valider cette tâche.";
        redirectBack($annee, $redirect);
        return;
    }

    try {
        // Mettre à jour le statut de la tâche
        $pdo->beginTransaction();

        // Mettre à jour la tâche
        $query = "UPDATE taches SET validation = ?, pourcentage_avancement = ?, 
                  date_validation = NOW(), commentaire_validation = ? WHERE idtaches = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$validation, $pourcentage, $commentaire, $tacheId]);

        // Ajouter un échange pour la validation si un commentaire est fourni
        if (!empty($commentaire)) {
            $role = ''; // Déterminer si l'agent est directeur ou encadreur
            $query = "SELECT \"idDirecteur\", \"idEncadreur\" FROM sujets WHERE idsujets = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$sujetId]);
            $sujetInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sujetInfo['idDirecteur'] == $idAgent) {
                $role = 'Directeur';
            } else if ($sujetInfo['idEncadreur'] == $idAgent) {
                $role = 'Encadreur';
            }

            $query = "INSERT INTO echanges_taches (\"dateEchange\", commentaire, taches_idtaches, type_auteur, \"idAuteur\") 
                      VALUES (NOW(), ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$commentaire, $tacheId, $role, $idAgent]);
        }

        $pdo->commit();
        $_SESSION['success'] = "La tâche a été " . strtolower($validation) . " avec succès.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    redirectBack($annee, $redirect, $sujetId);
}

/**
 * Met à jour une tâche existante
 */
function updateTask($pdo, $idAgent) {
    // Récupérer les données du formulaire
    $tacheId = isset($_POST['tache_id']) ? intval($_POST['tache_id']) : 0;
    $dateTache = isset($_POST['date_tache']) ? $_POST['date_tache'] : date('Y-m-d');
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $pourcentage = isset($_POST['pourcentage']) ? intval($_POST['pourcentage']) : 0;
    $annee = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'recherche/projet.taches';

    // Vérifier les données obligatoires
    if (empty($tacheId) || empty($description)) {
        $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'utilisateur a les droits sur cette tâche
    $query = "SELECT s.idsujets, s.\"idDirecteur\" FROM taches t
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE t.idtaches = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tacheId]);
    $sujetInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sujetInfo || $sujetInfo['idDirecteur'] != $idAgent) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à modifier cette tâche.";
        redirectBack($annee, $redirect);
        return;
    }

    $sujetId = $sujetInfo['idsujets'];

    // Gérer le téléchargement du fichier
    $fichierTache = null;
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $filename = $_FILES['fichier']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($ext), $allowed) && $_FILES['fichier']['size'] <= 10000000) { // 10MB max
            $uploadDir = dirname(__DIR__) . '/uploads/taches/';
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = 'tache_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $destination)) {
                $fichierTache = $newFilename;
                
                // Récupérer l'ancien fichier pour le supprimer
                $query = "SELECT \"fichierTache\" FROM taches WHERE idtaches = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$tacheId]);
                $oldFile = $stmt->fetchColumn();
                
                if ($oldFile && file_exists($uploadDir . $oldFile)) {
                    unlink($uploadDir . $oldFile);
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
        // Préparer la requête SQL en fonction de si un nouveau fichier a été téléchargé
        if ($fichierTache !== null) {
            $query = "UPDATE taches SET \"dateTache\" = ?, description = ?, \"fichierTache\" = ?, 
                      pourcentage_avancement = ? WHERE idtaches = ?";
                        $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$dateTache, $description, $fichierTache, $pourcentage, $tacheId]);
        } else {
            $query = "UPDATE taches SET \"dateTache\" = ?, description = ?,
                      pourcentage_avancement = ? WHERE idtaches = ?";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$dateTache, $description, $pourcentage, $tacheId]);
        }

        if ($result) {
            $_SESSION['success'] = "La tâche a été mise à jour avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour de la tâche.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    redirectBack($annee, $redirect, $sujetId);
}

/**
 * Supprime une tâche
 */
function deleteTask($pdo, $idAgent) {
    // Récupérer les données du formulaire
    $tacheId = isset($_POST['tache_id']) ? intval($_POST['tache_id']) : 0;
    $annee = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'recherche/projet.taches';

    // Vérifier que l'ID de la tâche est valide
    if (empty($tacheId)) {
        $_SESSION['error'] = "ID de tâche invalide.";
        redirectBack($annee, $redirect);
        return;
    }

    // Vérifier que l'utilisateur est directeur du sujet associé à cette tâche
    $query = "SELECT s.idsujets, s.\"idDirecteur\" FROM taches t
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE t.idtaches = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tacheId]);
    $sujetInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sujetInfo || $sujetInfo['idDirecteur'] != $idAgent) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à supprimer cette tâche.";
        redirectBack($annee, $redirect);
        return;
    }

    $sujetId = $sujetInfo['idsujets'];

    try {
        $pdo->beginTransaction();

        // Récupérer le nom du fichier pour le supprimer
        $query = "SELECT \"fichierTache\" FROM taches WHERE idtaches = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tacheId]);
        $fichierTache = $stmt->fetchColumn();

        // Supprimer les échanges liés à la tâche
        $query = "DELETE FROM echanges_taches WHERE taches_idtaches = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tacheId]);

        // Supprimer la tâche
        $query = "DELETE FROM taches WHERE idtaches = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$tacheId]);

        $pdo->commit();

        // Supprimer le fichier physique si nécessaire
        if ($fichierTache && file_exists(dirname(__DIR__) . '/uploads/taches/' . $fichierTache)) {
            unlink(dirname(__DIR__) . '/uploads/taches/' . $fichierTache);
        }

        $_SESSION['success'] = "La tâche a été supprimée avec succès.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    redirectBack($annee, $redirect, $sujetId);
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
