<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Dette.php';

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Récupérer les données du formulaire
$matricule = isset($_POST['matricule']) ? $_POST['matricule'] : '';
$anneeId = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
$sessionRachat = isset($_POST['session_rachat']) ? intval($_POST['session_rachat']) : 0;
$anneeRachat = isset($_POST['annee_rachat']) ? intval($_POST['annee_rachat']) : 0;
$dettes = isset($_POST['dettes']) ? $_POST['dettes'] : [];

if (!$matricule || !$anneeId || !$sessionRachat || !$anneeRachat || empty($dettes)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$dette = new Dette();
$userId = $_SESSION['id'];
$errors = [];
$success = 0;

try {
    // Traiter chaque dette
    foreach ($dettes as $detteId => $notes) {
        if (isset($notes['cc']) && isset($notes['ex']) && 
            is_numeric($notes['cc']) && is_numeric($notes['ex'])) {
            
            $noteCC = floatval($notes['cc']);
            $noteEX = floatval($notes['ex']);
            
            // Vérifier que les notes sont valides
            if ($noteCC < 0 || $noteCC > 20 || $noteEX < 0 || $noteEX > 20) {
                $errors[] = "Notes invalides pour la dette #$detteId";
                continue;
            }
            
            // Enregistrer les notes de rachat
            $result = $dette->enregistrerNoteRachat(
                $detteId, 
                $noteCC, 
                $noteEX, 
                $sessionRachat, 
                $anneeRachat, 
                $userId
            );
            
            if ($result) {
                $success++;
                
                // Si la dette est validée, mettre à jour les notes dans cotes_grille
                // Récupérer les pondérations depuis la configuration
require_once '../models/Universite.php';
$universite = new Universite();
$ponderations = $universite->getPonderationsDefaut();
$moyenneFinale = ($noteCC * $ponderations['ponderation_cc']) + ($noteEX * $ponderations['ponderation_ex']);
                if ($moyenneFinale >= 10) {
                    // Récupérer les informations de la dette
                    $db = Connexion::getInstance()->getPDO();
                    $stmt = $db->prepare("
                        SELECT d.*, e.matricule, e.\"ECUE_idECUE\" 
                        FROM dette_etudiant d
                        WHERE d.id_dette = :dette
                    ");
                    $stmt->execute(['dette' => $detteId]);
                    $detteInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($detteInfo) {
                        // Mettre à jour ou insérer dans cotes_grille
                        $stmt = $db->prepare("
                            INSERT INTO cotes_grille (
                                \"CC\", \"EX\", \"MF\", \"ECUE_idECUE\", session_idsession,
                                matricule, annee_acad_id, \"idUser\"
                            ) VALUES (
                                :cc, :ex, :mf, :ecue, :session,
                                :matricule, :annee, :user
                            ) ON CONFLICT (\"ECUE_idECUE\", session_idsession, matricule, annee_acad_id) DO UPDATE
                                SET \"CC\" = EXCLUDED.\"CC\",
                                    \"EX\" = EXCLUDED.\"EX\",
                                    \"MF\" = EXCLUDED.\"MF\",
                                    \"idUser\" = EXCLUDED.\"idUser\"
                        ");
                        
                        $stmt->execute([
                            'cc' => $noteCC,
                            'ex' => $noteEX,
                            'mf' => $moyenneFinale,
                            'ecue' => $detteInfo['ECUE_idECUE'],
                            'session' => $sessionRachat,
                            'matricule' => $detteInfo['matricule'],
                            'annee' => $detteInfo['annee_acad_idannee_acad'],
                            'user' => $userId
                        ]);
                    }
                }
            } else {
                $errors[] = "Erreur lors de l'enregistrement de la dette #$detteId";
            }
        }
    }
    
    if ($success > 0) {
        $message = "$success note(s) enregistrée(s) avec succès";
        if (!empty($errors)) {
            $message .= ". Erreurs: " . implode(', ', $errors);
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucune note enregistrée. ' . implode(', ', $errors)]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}