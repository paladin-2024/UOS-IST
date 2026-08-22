<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if (isset($_POST['updateManagerBtn'])) {
    $universite = new Universite();
    $structure = new Structure();
    
    // Récupération des données du formulaire
    $managerId = $_POST['editManagerId'];
    $userId = $_POST['editUserId'];
    $fonction = $_POST['editFonction'];
    $anneeAcadId = $_POST['idAnnee'];
    $est_chef = isset($_POST['editEstChef']) ? $_POST['editEstChef'] : 0;
    $telephone = isset($_POST['editTelephone']) ? $_POST['editTelephone'] : null;
    $email = isset($_POST['editEmail']) ? $_POST['editEmail'] : null;
    $date_debut = isset($_POST['editDateDebut']) && !empty($_POST['editDateDebut']) ? $_POST['editDateDebut'] : null;
    $date_fin = isset($_POST['editDateFin']) && !empty($_POST['editDateFin']) ? $_POST['editDateFin'] : null;
    
    // Récupération du nom de l'utilisateur
    $userStmt = $structure->getUserById($userId);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $noms = $user ? $user['nomUser'] : '';
    
    // Gestion de l'upload de la nouvelle signature
    $signature = null;
    if (isset($_FILES['editSignature']) && $_FILES['editSignature']['error'] == 0) {
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['editSignature']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['editSignature']['tmp_name'], $uploadFile)) {
            $signature = $fileName;
        }
    }
    
    try {
        $conn = Connexion::getInstance()->getPDO();
        $conn->beginTransaction();
        
        // Récupérer l'orientation_id du responsable actuel
        $getOrientationQuery = "SELECT orientation_idorientation FROM responsable_orientation WHERE idresponsable_orientation = :managerId";
        $stmt = $conn->prepare($getOrientationQuery);
        $stmt->execute([':managerId' => $managerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $orientationId = $result['orientation_idorientation'];
        
        // Si ce responsable est défini comme chef, désactiver les autres chefs pour cette orientation et année
        if ($est_chef == 1) {
            $updateQuery = "UPDATE responsable_orientation 
                           SET est_chef = 0 
                           WHERE orientation_idorientation = :orientationId 
                           AND annee_acad_idannee_acad = :anneeAcadId
                           AND idresponsable_orientation != :managerId";
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([
                ':orientationId' => $orientationId,
                ':anneeAcadId' => $anneeAcadId,
                ':managerId' => $managerId
            ]);
        }
        
        // Mise à jour du responsable
        $query = "UPDATE responsable_orientation 
                  SET noms = :noms, 
                      fonction = :fonction, 
                      idUser = :idUser, 
                      annee_acad_idannee_acad = :anneeAcadId,
                      est_chef = :est_chef,
                      telephone = :telephone,
                      email = :email,
                      date_debut = :date_debut,
                      date_fin = :date_fin";
        
        // Ajouter la signature seulement si une nouvelle a été uploadée
        if ($signature !== null) {
            $query .= ", signature = :signature";
        }
        
        $query .= " WHERE idresponsable_orientation = :managerId";
        
        $stmt = $conn->prepare($query);
        
        $params = [
            ':noms' => $noms,
            ':fonction' => $fonction,
            ':idUser' => $userId,
            ':anneeAcadId' => $anneeAcadId,
            ':est_chef' => $est_chef,
            ':telephone' => $telephone,
            ':email' => $email,
            ':date_debut' => $date_debut,
            ':date_fin' => $date_fin,
            ':managerId' => $managerId
        ];
        
        if ($signature !== null) {
            $params[':signature'] = $signature;
        }
        
        $result = $stmt->execute($params);
        
        if ($result) {
            $conn->commit();
            $_SESSION['success'] = "Responsable modifié avec succès.";
            
            // Message spécifique si c'est un chef
            if ($est_chef == 1) {
                $_SESSION['success'] = "Chef de département modifié avec succès.";
            }
        } else {
            throw new Exception("Erreur lors de la modification du responsable.");
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