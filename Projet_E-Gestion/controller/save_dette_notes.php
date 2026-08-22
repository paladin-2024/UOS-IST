<?php
session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

// Définir le header pour JSON
header('Content-Type: application/json');

// Vérification des droits d'accès
if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Récupération des données
$idDette = isset($_POST['id_dette']) ? intval($_POST['id_dette']) : 0;
$noteCC = isset($_POST['note_cc']) ? floatval($_POST['note_cc']) : null;
$noteEX = isset($_POST['note_ex']) ? floatval($_POST['note_ex']) : null;
$sessionId = isset($_POST['session']) ? intval($_POST['session']) : 0;
$anneeId = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
$userId = $_SESSION['id'];

// Validation des données
if (!$idDette || $noteCC === null || $noteEX === null || !$sessionId || !$anneeId) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

if ($noteCC < 0 || $noteCC > 20 || $noteEX < 0 || $noteEX > 20) {
    echo json_encode(['success' => false, 'message' => 'Les notes doivent être entre 0 et 20']);
    exit();
}

$db = Connexion::getInstance()->getPDO();

try {
    // Activer le mode d'erreur PDO pour avoir plus de détails
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->beginTransaction();
    
    // Vérifier que la dette existe et est en cours
    $sql = "SELECT * FROM dette_etudiant WHERE id_dette = :dette AND statut = 'En cours'";
    $stmt = $db->prepare($sql);
    $stmt->execute([':dette' => $idDette]);
    $dette = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        throw new Exception("Dette non trouvée ou déjà validée (ID: $idDette)");
    }
    
    error_log("Dette trouvée: " . json_encode($dette));
    
    // Supprimer les évaluations existantes pour cette dette
    $sql = "DELETE FROM dette_evaluation WHERE id_dette = :dette";
    $stmt = $db->prepare($sql);
    $stmt->execute([':dette' => $idDette]);
    
    // Enregistrer la note CC
    $sql = "INSERT INTO dette_evaluation (
                id_dette, type_evaluation, note, date_evaluation,
                session_idsession, annee_acad_idannee_acad, idUser
            ) VALUES (
                :dette, 'CC', :note, CURDATE(),
                :session, :annee, :user
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':dette' => $idDette,
        ':note' => $noteCC,
        ':session' => $sessionId,
        ':annee' => $anneeId,
        ':user' => $userId
    ]);
    
    // Enregistrer la note EX
    $sql = "INSERT INTO dette_evaluation (
                id_dette, type_evaluation, note, date_evaluation,
                session_idsession, annee_acad_idannee_acad, idUser
            ) VALUES (
                :dette, 'EX', :note, CURDATE(),
                :session, :annee, :user
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':dette' => $idDette,
        ':note' => $noteEX,
        ':session' => $sessionId,
        ':annee' => $anneeId,
        ':user' => $userId
    ]);
    
    // Calculer la moyenne finale
    // Récupérer les pondérations depuis la configuration
require_once '../models/Universite.php';
$universite = new Universite();
$ponderations = $universite->getPonderationsDefaut();
$moyenneFinale = ($noteCC * $ponderations['ponderation_cc']) + ($noteEX * $ponderations['ponderation_ex']);
    
    // Déterminer le statut
    $statut = $moyenneFinale >= 10 ? 'Validée' : 'En cours';
    
    // Mettre à jour la dette
    $sql = "UPDATE dette_etudiant 
            SET note_rachat = :note,
                session_rachat = :session,
                annee_rachat = :annee,
                statut = :statut,
                date_validation = :date_validation
            WHERE id_dette = :dette";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':note' => $moyenneFinale,
        ':session' => $sessionId,
        ':annee' => $anneeId,
        ':statut' => $statut,
        ':date_validation' => $statut == 'Validée' ? date('Y-m-d H:i:s') : null,
        ':dette' => $idDette
    ]);
    
    // Ajouter à l'historique
    $sql = "INSERT INTO dette_historique (id_dette, action, details, idUser)
            VALUES (:dette, :action, :details, :user)";
    
    $details = sprintf(
        "Notes de rachat enregistrées - CC: %.2f, EX: %.2f, MF: %.2f - Statut: %s",
        $noteCC, $noteEX, $moyenneFinale, $statut
    );
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':dette' => $idDette,
        ':action' => 'Enregistrement notes',
        ':details' => $details,
        ':user' => $userId
    ]);
    
    // Si la dette est validée, mettre à jour les cotes dans la table principale
    if ($statut == 'Validée') {
        // Récupérer les informations nécessaires
        $sql = "SELECT matricule, ECUE_idECUE FROM dette_etudiant WHERE id_dette = :dette";
        $stmt = $db->prepare($sql);
        $stmt->execute([':dette' => $idDette]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier si une entrée existe déjà dans cotes_grille
        $sql = "SELECT idpoints FROM cotes_grille 
                WHERE matricule = :matricule 
                AND ECUE_idECUE = :ecue 
                AND session_idsession = :session 
                AND annee_acad_id = :annee";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':matricule' => $info['matricule'],
            ':ecue' => $info['ECUE_idECUE'],
            ':session' => $sessionId,
            ':annee' => $anneeId
        ]);
        
        if ($stmt->rowCount() > 0) {
            // Mettre à jour
            $sql = "UPDATE cotes_grille 
                    SET CC = :cc, EX = :ex, MF = :mf, date_compilation = NOW(), idUser = :user
                    WHERE matricule = :matricule 
                    AND ECUE_idECUE = :ecue 
                    AND session_idsession = :session 
                    AND annee_acad_id = :annee";
        } else {
            // Insérer
            $sql = "INSERT INTO cotes_grille (CC, EX, MF, ECUE_idECUE, session_idsession, matricule, annee_acad_id, idUser)
                    VALUES (:cc, :ex, :mf, :ecue, :session, :matricule, :annee, :user)";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':cc' => $noteCC,
            ':ex' => $noteEX,
            ':mf' => $moyenneFinale,
            ':matricule' => $info['matricule'],
            ':ecue' => $info['ECUE_idECUE'],
            ':session' => $sessionId,
            ':annee' => $anneeId,
            ':user' => $userId
        ]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Notes enregistrées avec succès',
        'moyenne_finale' => $moyenneFinale,
        'statut' => $statut
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Erreur PDO enregistrement notes dette: " . $e->getMessage());
    error_log("Code erreur: " . $e->getCode());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Message d'erreur plus détaillé pour le débogage
    $message = "Erreur base de données: ";
    if (strpos($e->getMessage(), 'Column not found') !== false) {
        $message .= "Colonne manquante dans la base de données. ";
    } elseif (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        $message .= "Table manquante dans la base de données. ";
    } else {
        $message .= $e->getMessage();
    }
    
    echo json_encode(['success' => false, 'message' => $message, 'debug' => $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Erreur générale enregistrement notes dette: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}