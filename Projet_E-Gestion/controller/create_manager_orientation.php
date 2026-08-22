<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if (isset($_POST['addManagerBtn'])) {
    $universite = new Universite();
    $structure = new Structure();
    
    // Récupération des données du formulaire
    $userId = $_POST['userId'];
    $fonction = $_POST['fonction'];
    $orientationId = $_POST['orientationId'];
    $anneeAcadId = $_POST['idAnnee'];
    $est_chef = isset($_POST['est_chef']) ? $_POST['est_chef'] : 0;
    $telephone = isset($_POST['telephone']) ? $_POST['telephone'] : null;
    $email = isset($_POST['email']) ? $_POST['email'] : null;
    $date_debut = isset($_POST['date_debut']) && !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = isset($_POST['date_fin']) && !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    
    // Récupération du nom de l'utilisateur
    $userStmt = $structure->getUserById($userId);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $noms = $user ? $user['nomUser'] : '';
    
    // Gestion de l'upload de la signature
    $signature = '';
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['signature']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['signature']['tmp_name'], $uploadFile)) {
            $signature = $fileName;
        } else {
            $_SESSION['error'] = "Erreur lors de l'upload de la signature.";
            header('Location: ../index.php?view=configuration/orientation');
            exit();
        }
    }
    
    try {
        $conn = Connexion::getInstance()->getPDO();
        $conn->beginTransaction();
        
        // Si ce responsable est défini comme chef, désactiver les autres chefs pour cette orientation et année
        if ($est_chef == 1) {
            $updateQuery = "UPDATE responsable_orientation 
                           SET est_chef = 0 
                           WHERE orientation_idorientation = :orientationId 
                           AND annee_acad_idannee_acad = :anneeAcadId";
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([
                ':orientationId' => $orientationId,
                ':anneeAcadId' => $anneeAcadId
            ]);
        }
        
        // Insertion du nouveau responsable
        $query = "INSERT INTO responsable_orientation 
                  (noms, fonction, signature, \"idUser\", orientation_idorientation, annee_acad_idannee_acad, 
                   est_chef, telephone, email, date_debut, date_fin) 
                  VALUES 
                  (:noms, :fonction, :signature, :idUser, :orientationId, :anneeAcadId, 
                   :est_chef, :telephone, :email, :date_debut, :date_fin)";
        
        $stmt = $conn->prepare($query);
        $result = $stmt->execute([
            ':noms' => $noms,
            ':fonction' => $fonction,
            ':signature' => $signature,
            ':idUser' => $userId,
            ':orientationId' => $orientationId,
            ':anneeAcadId' => $anneeAcadId,
            ':est_chef' => $est_chef,
            ':telephone' => $telephone,
            ':email' => $email,
            ':date_debut' => $date_debut,
            ':date_fin' => $date_fin
        ]);
        
        if ($result) {
            $conn->commit();
            $_SESSION['success'] = "Responsable ajouté avec succès.";
            
            // Message spécifique si c'est un chef
            if ($est_chef == 1) {
                $_SESSION['success'] = "Chef de département ajouté avec succès.";
            }
        } else {
            throw new Exception("Erreur lors de l'ajout du responsable.");
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Erreur : " . $e->getMessage();
    }
    
    header('Location: ../index.php?view=configuration/orientation');
    exit();
} else {
    header('Location: ../index.php?view=configuration/orientation');
    exit();
}
?>