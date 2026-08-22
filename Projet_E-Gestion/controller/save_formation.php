<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données du formulaire
$idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
$formationId = isset($_POST['formation_id']) ? intval($_POST['formation_id']) : 0;
$niveau = isset($_POST['niveau']) ? trim($_POST['niveau']) : '';
$etablissement = isset($_POST['etablissement']) ? trim($_POST['etablissement']) : '';
$filiere = isset($_POST['filiere']) ? trim($_POST['filiere']) : '';
$anneeObtention = isset($_POST['annee_obtention']) ? intval($_POST['annee_obtention']) : null;
$diplomeActuel = isset($_POST['diplome_fichier_actuel']) ? trim($_POST['diplome_fichier_actuel']) : '';
$idUser = $_SESSION['id']; // ID de l'utilisateur connecté

// Validation des données
if (empty($idAgent)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de l\'agent non spécifié']);
    exit;
}

if (empty($niveau) || empty($etablissement)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires']);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();

    // Traitement du fichier diplôme
    $diplomeFichier = $diplomeActuel;
    if (isset($_FILES['diplome_fichier']) && $_FILES['diplome_fichier']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/diplomes/';
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Générer un nom de fichier unique
        $fileExtension = pathinfo($_FILES['diplome_fichier']['name'], PATHINFO_EXTENSION);
        $newFileName = 'diplome_' . $idAgent . '_' . time() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $newFileName;
        
        // Déplacer le fichier téléchargé
        if (move_uploaded_file($_FILES['diplome_fichier']['tmp_name'], $uploadFile)) {
            $diplomeFichier = 'uploads/diplomes/' . $newFileName;
            
            // Supprimer l'ancien fichier si nécessaire
            if (!empty($diplomeActuel) && file_exists(dirname(__DIR__) . '/' . $diplomeActuel)) {
                unlink(dirname(__DIR__) . '/' . $diplomeActuel);
            }
        } else {
            throw new Exception('Erreur lors du téléchargement du fichier');
        }
    }

    // Enregistrer ou mettre à jour la formation
    if ($formationId > 0) {
        // Mise à jour d'une formation existante
        $query = "UPDATE formation_agent 
                  SET niveau = :niveau, 
                      etablissement = :etablissement, 
                      filiere = :filiere, 
                      annee_obtention = :annee_obtention, 
                      diplome_fichier = :diplome_fichier,
                      idUser = :idUser
                  WHERE idformation = :idformation AND idAgent = :idAgent";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':niveau', $niveau, PDO::PARAM_STR);
        $stmt->bindParam(':etablissement', $etablissement, PDO::PARAM_STR);
        $stmt->bindParam(':filiere', $filiere, PDO::PARAM_STR);
        $stmt->bindParam(':annee_obtention', $anneeObtention, PDO::PARAM_INT);
        $stmt->bindParam(':diplome_fichier', $diplomeFichier, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':idformation', $formationId, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        
        $stmt->execute();
        $message = 'Formation mise à jour avec succès';
    } else {
        // Ajout d'une nouvelle formation
        $query = "INSERT INTO formation_agent 
                  (idAgent, niveau, etablissement, filiere, annee_obtention, diplome_fichier, idUser) 
                  VALUES 
                  (:idAgent, :niveau, :etablissement, :filiere, :annee_obtention, :diplome_fichier, :idUser)";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':niveau', $niveau, PDO::PARAM_STR);
        $stmt->bindParam(':etablissement', $etablissement, PDO::PARAM_STR);
        $stmt->bindParam(':filiere', $filiere, PDO::PARAM_STR);
        $stmt->bindParam(':annee_obtention', $anneeObtention, PDO::PARAM_INT);
        $stmt->bindParam(':diplome_fichier', $diplomeFichier, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        
        $stmt->execute();
        $message = 'Formation ajoutée avec succès';
    }

    // Valider la transaction
    $pdo->commit();
    
    // Répondre avec le résultat
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    exit;
}
