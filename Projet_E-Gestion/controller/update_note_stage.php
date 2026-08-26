<?php
session_start();
require_once '../config/Connexion.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$pdo = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Récupérer l'ID de l'agent (enseignant)
$query = "SELECT a.\"idAgent\" FROM agent a
          INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\"
          WHERE u.\"idUser\" = ? AND a.type_agent = 'Enseignant'";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$idEnseignant = $stmt->fetchColumn();

if (!$idEnseignant) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être enseignant pour effectuer cette action']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_note_encadreur':
            $idstage = intval($_POST['idstage']);
            $note = floatval($_POST['note']);
            
            // Vérifier que la note est valide
            if ($note < 0 || $note > 20) {
                echo json_encode(['success' => false, 'message' => 'La note doit être entre 0 et 20']);
                exit();
            }
            
            // Vérifier que l'enseignant est bien l'encadreur de cet étudiant
            $query = "SELECT idstage FROM stage_assignments WHERE idstage = ? AND idencadreur = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$idstage, $idEnseignant]);
            
            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas l\'encadreur de cet étudiant']);
                exit();
            }
            
            // Mettre à jour la note
            $query = "UPDATE stage_assignments SET cote_entreprise = ? WHERE idstage = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$note, $idstage]);
            
            echo json_encode(['success' => true, 'message' => 'Note enregistrée avec succès']);
            break;
            
        case 'update_note_lecteur':
            $idstage = intval($_POST['idstage']);
            $note = floatval($_POST['note']);
            
            // Vérifier que la note est valide
            if ($note < 0 || $note > 20) {
                echo json_encode(['success' => false, 'message' => 'La note doit être entre 0 et 20']);
                exit();
            }
            
            // Vérifier que l'enseignant est bien le lecteur de cet étudiant
            $query = "SELECT idstage FROM stage_assignments WHERE idstage = ? AND idlecteur = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$idstage, $idEnseignant]);
            
            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas le lecteur de cet étudiant']);
                exit();
            }
            
            // Mettre à jour la note
            $query = "UPDATE stage_assignments SET cote_lecteur = ? WHERE idstage = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$note, $idstage]);
            
            echo json_encode(['success' => true, 'message' => 'Note enregistrée avec succès']);
            break;
            
        case 'bulk_add_points':
            $points = floatval($_POST['points']);
            $anneeId = intval($_POST['annee']);
            
            // Vérifier que les points sont valides
            if ($points < 0 || $points > 20) {
                echo json_encode(['success' => false, 'message' => 'Les points doivent être entre 0 et 20']);
                exit();
            }
            
            // Récupérer tous les stages où l'enseignant est encadreur pour l'année sélectionnée
            $query = "SELECT sa.idstage, sa.cote_entreprise
                      FROM stage_assignments sa
                      INNER JOIN etudiant e ON sa.idetudiant = e.idetudiant
                      WHERE sa.idencadreur = ?
                      AND e.annee_acad_idannee_acad = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$idEnseignant, $anneeId]);
            $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($stages as $stage) {
                $currentNote = $stage['cote_entreprise'] ?? 0;
                $newNote = min(20, $currentNote + $points); // Plafonner à 20
                
                $updateQuery = "UPDATE stage_assignments SET cote_entreprise = ? WHERE idstage = ?";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([$newNote, $stage['idstage']]);
                $updated++;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "$updated étudiant(s) ont reçu les points supplémentaires"
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch (Exception $e) {
    error_log("Erreur update_note_stage: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue: ' . $e->getMessage()]);
}
?>
