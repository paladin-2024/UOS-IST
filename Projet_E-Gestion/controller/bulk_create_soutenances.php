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
    if (!isset($_POST['sujet_ids']) || !isset($_POST['date_soutenance']) || !isset($_POST['lieu_soutenance'])) {
        throw new Exception('Données manquantes pour la programmation.');
    }

    $sujetIds = json_decode($_POST['sujet_ids'], true);
    $dateDebutSoutenance = $_POST['date_soutenance'];
    $dureeSoutenance = isset($_POST['duree_soutenance']) ? intval($_POST['duree_soutenance']) : 30;
    $lieuSoutenance = trim($_POST['lieu_soutenance']);
    $lecteur1Id = !empty($_POST['lecteur1_id']) ? intval($_POST['lecteur1_id']) : null;
    $lecteur2Id = !empty($_POST['lecteur2_id']) ? intval($_POST['lecteur2_id']) : null;
    $juryId = !empty($_POST['jury_id']) ? intval($_POST['jury_id']) : null;

    if (!is_array($sujetIds) || empty($sujetIds)) {
        throw new Exception('Aucun sujet sélectionné.');
    }

    if (empty($dateDebutSoutenance) || empty($lieuSoutenance)) {
        throw new Exception('La date et le lieu de soutenance sont obligatoires.');
    }
    
    // Convertir la date de début en timestamp
    $startTimestamp = strtotime($dateDebutSoutenance);
    if ($startTimestamp === false) {
        throw new Exception('Format de date invalide.');
    }

    // Vérifier que les lecteurs sont différents
    if ($lecteur1Id && $lecteur2Id && $lecteur1Id === $lecteur2Id) {
        throw new Exception('Les deux lecteurs doivent être différents.');
    }

    $connexion = Connexion::getInstance()->getPDO();
    $connexion->beginTransaction();

    $createdCount = 0;
    $errors = [];
    $index = 0;

    foreach ($sujetIds as $sujetId) {
        $sujetId = intval($sujetId);
        
        // Calculer l'heure de soutenance pour cet étudiant (échelonnée)
        $soutenanceTimestamp = $startTimestamp + ($index * $dureeSoutenance * 60);
        $dateSoutenance = date('Y-m-d H:i:s', $soutenanceTimestamp);
        
        try {
            // Vérifier que le sujet existe et n'a pas déjà de soutenance
            $stmt = $connexion->prepare("
                SELECT sj.idsujets, sj.intitule, e.noms as etudiant_nom,
                       (SELECT COUNT(*) FROM soutenance WHERE sujets_idsujets = sj.idsujets) as has_soutenance
                FROM sujets sj
                JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                WHERE sj.idsujets = :sujetId
            ");
            $stmt->execute(['sujetId' => $sujetId]);
            $sujet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sujet) {
                $errors[] = "Sujet ID $sujetId introuvable.";
                continue;
            }

            if ($sujet['has_soutenance'] > 0) {
                $errors[] = "Le sujet '{$sujet['intitule']}' a déjà une soutenance programmée.";
                continue;
            }

            // Créer la soutenance (avec jury si spécifié)
            if ($juryId) {
                $stmt = $connexion->prepare("
                    INSERT INTO soutenance (sujets_idsujets, date_soutenance, lieu, statut, jury_id)
                    VALUES (:sujetId, :dateSoutenance, :lieu, 'Programmée', :juryId)
                ");
                $stmt->execute([
                    'sujetId' => $sujetId,
                    'dateSoutenance' => $dateSoutenance,
                    'lieu' => $lieuSoutenance,
                    'juryId' => $juryId
                ]);
            } else {
                $stmt = $connexion->prepare("
                    INSERT INTO soutenance (sujets_idsujets, date_soutenance, lieu, statut)
                    VALUES (:sujetId, :dateSoutenance, :lieu, 'Programmée')
                ");
                $stmt->execute([
                    'sujetId' => $sujetId,
                    'dateSoutenance' => $dateSoutenance,
                    'lieu' => $lieuSoutenance
                ]);
            }

            $soutenanceId = $connexion->lastInsertId();

            // Ajouter les lecteurs si définis
            if ($lecteur1Id) {
                $stmt = $connexion->prepare("
                    INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur)
                    VALUES (:soutenanceId, :enseignantId, 1)
                ");
                $stmt->execute([
                    'soutenanceId' => $soutenanceId,
                    'enseignantId' => $lecteur1Id
                ]);
            }

            if ($lecteur2Id) {
                $stmt = $connexion->prepare("
                    INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur)
                    VALUES (:soutenanceId, :enseignantId, 0)
                ");
                $stmt->execute([
                    'soutenanceId' => $soutenanceId,
                    'enseignantId' => $lecteur2Id
                ]);
            }

            $createdCount++;
            $index++;

        } catch (Exception $e) {
            $errors[] = "Erreur pour le sujet ID $sujetId: " . $e->getMessage();
            $index++;
        }
    }

    if ($createdCount > 0) {
        $connexion->commit();
        
        $message = "$createdCount soutenance(s) créée(s) avec succès.";
        if (!empty($errors)) {
            $message .= "<br><br><strong>Avertissements:</strong><br>" . implode("<br>", $errors);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'created' => $createdCount,
            'errors' => $errors
        ]);
    } else {
        $connexion->rollBack();
        throw new Exception("Aucune soutenance n'a pu être créée. " . implode(" ", $errors));
    }

} catch (Exception $e) {
    if (isset($connexion) && $connexion->inTransaction()) {
        $connexion->rollBack();
    }
    
    error_log("Erreur bulk_create_soutenances: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
