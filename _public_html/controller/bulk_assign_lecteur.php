<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

try {
    session_start();
    
    if (!isset($_SESSION['id'])) {
        throw new Exception('Session expirée. Veuillez vous reconnecter.');
    }

    $userId = $_SESSION['id'];
    $userRole = $_SESSION['idRole'] ?? 0;
    
    // Vérifier les données requises
    if (!isset($_POST['sujet_ids']) || !isset($_POST['lecteur_id']) || !isset($_POST['position'])) {
        throw new Exception('Données manquantes pour l\'assignation.');
    }

    $sujetIds = json_decode($_POST['sujet_ids'], true);
    $lecteurId = intval($_POST['lecteur_id']);
    $position = intval($_POST['position']); // 1 = premier lecteur, 2 = deuxième lecteur

    if (!is_array($sujetIds) || empty($sujetIds)) {
        throw new Exception('Aucun sujet sélectionné.');
    }

    if ($lecteurId <= 0) {
        throw new Exception('Lecteur invalide.');
    }

    if ($position !== 1 && $position !== 2) {
        throw new Exception('Position invalide. Doit être 1 (premier) ou 2 (deuxième).');
    }

    $estPremierLecteur = ($position === 1) ? 1 : 0;

    $connexion = Connexion::getInstance()->getPDO();
    $connexion->beginTransaction();

    $assignedCount = 0;
    $createdSoutenances = 0;
    $replacedCount = 0;
    $errors = [];

    foreach ($sujetIds as $sujetId) {
        $sujetId = intval($sujetId);
        
        try {
            // Vérifier que le sujet existe et récupérer l'année académique
            $stmt = $connexion->prepare("
                SELECT sj.idsujets, sj.intitule, sj.annee_acad_idannee_acad,
                       e.noms as etudiant_nom,
                       s.idsoutenance
                FROM sujets sj
                JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                LEFT JOIN soutenance s ON sj.idsujets = s.sujets_idsujets
                WHERE sj.idsujets = :sujetId
            ");
            $stmt->execute(['sujetId' => $sujetId]);
            $sujet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sujet) {
                $errors[] = "Sujet ID $sujetId introuvable.";
                continue;
            }

            $soutenanceId = $sujet['idsoutenance'];
            $anneeAcadId = $sujet['annee_acad_idannee_acad'];

            // Si pas de soutenance, en créer une
            if (empty($soutenanceId)) {
                $stmt = $connexion->prepare("
                    INSERT INTO soutenance (sujets_idsujets, annee_acad_idannee_acad, statut)
                    VALUES (:sujetId, :anneeId, 'Non programmée')
                ");
                $stmt->execute([
                    'sujetId' => $sujetId,
                    'anneeId' => $anneeAcadId
                ]);
                $soutenanceId = $connexion->lastInsertId();
                $createdSoutenances++;
            }

            // Vérifier si un lecteur existe déjà à cette position
            $stmt = $connexion->prepare("
                SELECT COUNT(*) as cnt FROM lecteurs_soutenance 
                WHERE idsoutenance = :soutenanceId AND est_premier_lecteur = :estPremier
            ");
            $stmt->execute([
                'soutenanceId' => $soutenanceId,
                'estPremier' => $estPremierLecteur
            ]);
            $existingLecteur = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingLecteur && $existingLecteur['cnt'] > 0) {
                // Supprimer l'ancien lecteur à cette position
                $stmt = $connexion->prepare("
                    DELETE FROM lecteurs_soutenance 
                    WHERE idsoutenance = :soutenanceId AND est_premier_lecteur = :estPremier
                ");
                $stmt->execute([
                    'soutenanceId' => $soutenanceId,
                    'estPremier' => $estPremierLecteur
                ]);
                $replacedCount++;
            }
            
            // Insérer le nouveau lecteur
            $stmt = $connexion->prepare("
                INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur)
                VALUES (:soutenanceId, :lecteurId, :estPremier)
            ");
            $stmt->execute([
                'soutenanceId' => $soutenanceId,
                'lecteurId' => $lecteurId,
                'estPremier' => $estPremierLecteur
            ]);

            $assignedCount++;

        } catch (Exception $e) {
            $errors[] = "Erreur pour '{$sujet['etudiant_nom']}': " . $e->getMessage();
        }
    }

    if ($assignedCount > 0) {
        $connexion->commit();
        
        $positionText = $position === 1 ? 'premier lecteur' : 'deuxième lecteur';
        $message = "Lecteur assigné comme <strong>$positionText</strong> à <strong>$assignedCount</strong> étudiant(s).";
        
        if ($createdSoutenances > 0) {
            $message .= "<br>$createdSoutenances soutenance(s) créée(s) automatiquement.";
        }
        if ($replacedCount > 0) {
            $message .= "<br>$replacedCount lecteur(s) remplacé(s).";
        }
        if (!empty($errors)) {
            $message .= "<br><br><strong>Avertissements:</strong><br>" . implode("<br>", $errors);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'assigned' => $assignedCount,
            'created_soutenances' => $createdSoutenances,
            'replaced' => $replacedCount,
            'errors' => $errors
        ]);
    } else {
        $connexion->rollBack();
        throw new Exception("Aucun lecteur n'a pu être assigné. " . implode(" ", $errors));
    }

} catch (Exception $e) {
    if (isset($connexion) && $connexion->inTransaction()) {
        $connexion->rollBack();
    }
    
    error_log("Erreur bulk_assign_lecteur: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
