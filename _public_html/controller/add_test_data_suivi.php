<?php
session_start();
require_once '../models/Connexion.php';

// Vérifier l'authentification et les droits admin
if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action']) || $data['action'] !== 'add_test_data') {
    echo json_encode(['success' => false, 'message' => 'Action non valide']);
    exit;
}

$anneeAcadId = isset($data['annee_acad_id']) ? intval($data['annee_acad_id']) : 0;

if ($anneeAcadId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Année académique non valide']);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();
    
    // Récupérer quelques ECUE existants
    $queryEcue = "SELECT e.idECUE, e.designationECUE, p.idpromotion
                  FROM ecue e
                  JOIN ue u ON e.UE_idUE = u.idUE
                  JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  WHERE p.annee_acad_idannee_acad = :anneeId
                  AND e.estVisible = 1
                  LIMIT 10";
    
    $stmtEcue = $pdo->prepare($queryEcue);
    $stmtEcue->bindParam(':anneeId', $anneeAcadId);
    $stmtEcue->execute();
    $ecues = $stmtEcue->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($ecues)) {
        throw new Exception("Aucun ECUE trouvé pour cette année académique");
    }
    
    // Récupérer quelques enseignants
    $queryEnseignants = "SELECT idAgent, noms FROM agent WHERE type_agent = 'Enseignant' LIMIT 5";
    $stmtEnseignants = $pdo->prepare($queryEnseignants);
    $stmtEnseignants->execute();
    $enseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);
    
    // Types de cours
    $typesCours = ['CM', 'TD', 'TP', 'Evaluation'];
    
    // Salles
    $salles = ['A101', 'A102', 'B201', 'B202', 'C301', 'Labo Info 1', 'Amphi 1', 'Amphi 2'];
    
    // Matières enseignées (exemples)
    $matieres = [
        "Introduction générale\nHistorique et évolution\nConcepts fondamentaux",
        "Chapitre 1 : Les bases\n- Définitions\n- Principes généraux\n- Applications pratiques",
        "Chapitre 2 : Approfondissement\n- Théories avancées\n- Études de cas\n- Exercices pratiques",
        "Travaux pratiques\n- Manipulation des outils\n- Réalisation d'exercices\n- Correction collective",
        "Révisions générales\nPréparation à l'examen\nQuestions/Réponses",
        "Évaluation continue\n- Test écrit\n- Durée : 2h\n- Documents non autorisés"
    ];
    
    $count = 0;
    $currentDate = new DateTime();
    
    // Insérer des données de test
    foreach ($ecues as $ecue) {
        // Générer 3-5 séances par ECUE
        $nbSeances = rand(3, 5);
        
        for ($i = 0; $i < $nbSeances; $i++) {
            // Date aléatoire dans les 30 derniers jours
            $daysAgo = rand(1, 30);
            $dateSeance = clone $currentDate;
            $dateSeance->sub(new DateInterval("P{$daysAgo}D"));
            
            // Heure de début aléatoire (8h-16h)
            $heureDebut = rand(8, 16);
            $minuteDebut = rand(0, 1) * 30; // 00 ou 30
            
            // Durée aléatoire (1-3 heures)
            $duree = rand(1, 3);
            
            $heureDebutStr = sprintf("%02d:%02d:00", $heureDebut, $minuteDebut);
            $heureFin = $heureDebut + $duree;
            $heureFinStr = sprintf("%02d:%02d:00", $heureFin, $minuteDebut);
            
            // Sélectionner aléatoirement un enseignant
            $enseignant = !empty($enseignants) ? $enseignants[array_rand($enseignants)] : null;
            
            // Insérer l'enregistrement
            $insertQuery = "INSERT INTO suivi_enseignements 
                           (idECUE, enseignant_id, date_cours, heure_debut, heure_fin, 
                            type_cours, salle, commentaire, annee_acad_idannee_acad, 
                            idUser, date_creation)
                           VALUES 
                           (:idECUE, :enseignant_id, :date_cours, :heure_debut, :heure_fin,
                            :type_cours, :salle, :commentaire, :annee_acad_id,
                            :idUser, NOW())";
            
            $stmtInsert = $pdo->prepare($insertQuery);
            $stmtInsert->execute([
                ':idECUE' => $ecue['idECUE'],
                ':enseignant_id' => $enseignant ? $enseignant['idAgent'] : null,
                ':date_cours' => $dateSeance->format('Y-m-d'),
                ':heure_debut' => $heureDebutStr,
                ':heure_fin' => $heureFinStr,
                ':type_cours' => $typesCours[array_rand($typesCours)],
                ':salle' => $salles[array_rand($salles)],
                ':commentaire' => $matieres[array_rand($matieres)],
                ':annee_acad_id' => $anneeAcadId,
                ':idUser' => $_SESSION['id']
            ]);
            
            $count++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "$count enregistrements de test ont été ajoutés avec succès."
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'ajout des données : ' . $e->getMessage()
    ]);
}
?>